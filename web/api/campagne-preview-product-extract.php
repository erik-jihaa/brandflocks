<?php

declare(strict_types=1);

require_once __DIR__ . '/hygiene-scrape-repository.php';
require_once __DIR__ . '/hba-model-config.php';
require_once __DIR__ . '/hba-v2-repository.php';

const CE_CAMPAGNE_PRODUCT_EXTRACT_PROMPT_VERSION = 'product_extract_v0.2';

function ce_campagne_product_extract_system_prompt(): string
{
    return <<<'PROMPT'
Je leest één productpagina van een merk. Haal er letterlijk uit:
1. wat: wat het product is en voor wie (één zin);
2. anders: wat dit product anders maakt dan vergelijkbare producten in de branche. Alleen wat er staat, met citaat. Zet erbij of het branchetaal is volgens de categorietaal en hygiënefactoren van de merkarchitectuur;
3. bewijs: concrete gegevens, cijfers, namen, keuzes die de bewering onderbouwen, met citaat;
4. ontbreekt: wat een post over dit product nodig heeft maar niet op de pagina staat (bijvoorbeeld waarom het product bestaat, of bewijs).
Geen aannames. Staat iets er niet, zeg dat.

branchetaal = true als het citaat valt onder de categorietaal of hygiënefactoren (wat iedereen in de branche zegt of moet hebben). Anders false.

Antwoord als JSON:
{
  "wat": "string",
  "anders": [{"citaat": "string", "branchetaal": true}],
  "bewijs": [{"citaat": "string"}],
  "ontbreekt": ["string"]
}
PROMPT;
}

/**
 * @param array<string, mixed> $architecture
 */
function ce_campagne_product_extract_architecture_block(array $architecture): string
{
    $categorietaal = trim((string) ($architecture['categorietaal'] ?? ''));
    $hygiene = trim((string) ($architecture['hygienefactoren'] ?? ''));
    $nee = trim((string) ($architecture['wat_bewust_niet'] ?? ''));
    $regels = trim((string) ($architecture['regels'] ?? ''));
    $doel = trim((string) ($architecture['doel'] ?? ''));
    $foe = trim((string) ($architecture['foe'] ?? ''));
    $branche = trim((string) ($architecture['branche_label'] ?? ''));

    $lines = ['Merkarchitectuur (gebruik dit voor het oordeel branchetaal):'];
    $lines[] = 'Branche: ' . ($branche !== '' ? $branche : '(onbekend)');
    $lines[] = 'Categorietaal: ' . ($categorietaal !== '' ? $categorietaal : '(niet ingevuld)');
    $lines[] = 'Hygiënefactoren: ' . ($hygiene !== '' ? $hygiene : '(niet ingevuld)');
    if ($doel !== '') {
        $lines[] = 'Doel: ' . $doel;
    }
    if ($foe !== '') {
        $lines[] = 'Tegenkracht: ' . $foe;
    }
    if ($nee !== '') {
        $lines[] = 'Waar het merk nee tegen zegt: ' . $nee;
    }
    if ($regels !== '') {
        $lines[] = 'Geloofwaardigheidstest: ' . $regels;
    }

    return implode("\n", $lines);
}

/** @return array<string, mixed> */
function ce_campagne_product_extract_architecture_for_client(PDO $pdo, string $clientId): array
{
    $ctx = ce_hba_v2_architecture_context($pdo, $clientId);
    $hygiene = [];
    $code = trim((string) ($ctx['branche_code'] ?? ''));
    if (
        $code !== ''
        && ce_table_exists($pdo, 'branche_library')
        && ce_table_has_column($pdo, 'branche_library', 'hygienefactoren')
    ) {
        $stmt = $pdo->prepare(
            'SELECT hygienefactoren FROM branche_library WHERE branche_code = :code LIMIT 1'
        );
        $stmt->execute(['code' => $code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $hygiene = ce_hba_decode_json_list($row['hygienefactoren'] ?? []);
        }
    }
    $ctx['hygienefactoren'] = implode('; ', $hygiene);

    return $ctx;
}

/** Alleen /fixtures/*.html onder web/, voor preview-acceptatie zonder SSRF naar localhost. */
function ce_campagne_product_extract_local_fixture_path(string $url): ?string
{
    $path = parse_url($url, PHP_URL_PATH);
    if (!is_string($path) || !preg_match('#^/fixtures/([a-z0-9-]+\.html)$#', $path, $m)) {
        return null;
    }
    $file = dirname(__DIR__) . '/fixtures/' . $m[1];

    return is_readable($file) ? $file : null;
}

function ce_campagne_product_extract_normalize_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        throw new InvalidArgumentException('productPageUrl is verplicht');
    }
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        throw new InvalidArgumentException('Ongeldige productPageUrl');
    }

    return $url;
}

/** @return array{wat: string, anders: list<array{citaat: string, branchetaal: bool}>, bewijs: list<array{citaat: string}>, ontbreekt: list<string>} */
function ce_campagne_product_extract_normalize(mixed $raw): array
{
    if (!is_array($raw)) {
        throw new InvalidArgumentException('Extract moet een JSON-object zijn');
    }

    $wat = trim((string) ($raw['wat'] ?? ''));
    $anders = [];
    foreach ($raw['anders'] ?? [] as $item) {
        if (!is_array($item)) {
            continue;
        }
        $citaat = trim((string) ($item['citaat'] ?? ''));
        if ($citaat === '') {
            continue;
        }
        $anders[] = [
            'citaat' => $citaat,
            'branchetaal' => filter_var($item['branchetaal'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    $bewijs = [];
    foreach ($raw['bewijs'] ?? [] as $item) {
        if (!is_array($item)) {
            continue;
        }
        $citaat = trim((string) ($item['citaat'] ?? ''));
        if ($citaat === '') {
            continue;
        }
        $bewijs[] = ['citaat' => $citaat];
    }

    $ontbreekt = [];
    foreach ($raw['ontbreekt'] ?? [] as $line) {
        $t = trim((string) $line);
        if ($t !== '') {
            $ontbreekt[] = $t;
        }
    }

    return [
        'wat' => $wat,
        'anders' => $anders,
        'bewijs' => $bewijs,
        'ontbreekt' => $ontbreekt,
    ];
}

/**
 * @param array<string, mixed> $architecture
 * @return array{ok: bool, extract?: array<string, mixed>, model?: string, promptVersion?: string, pageUrl?: string, error?: string}
 */
function ce_campagne_product_extract_from_url(string $url, array $architecture = []): array
{
    try {
        $url = ce_campagne_product_extract_normalize_url($url);
    } catch (InvalidArgumentException $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }

    $text = '';
    $localPath = ce_campagne_product_extract_local_fixture_path($url);
    if ($localPath !== null) {
        $html = (string) file_get_contents($localPath);
        $text = trim(ce_hygiene_html_to_text($html));
    } else {
        $page = ce_hygiene_fetch_page_text($url);
        if (($page['ok'] ?? false) !== true) {
            return [
                'ok' => false,
                'error' => (string) ($page['message'] ?? 'Pagina niet leesbaar'),
                'pageUrl' => $url,
            ];
        }
        $text = trim((string) ($page['text'] ?? ''));
    }
    if (mb_strlen($text) < 80) {
        return [
            'ok' => false,
            'error' => 'Te weinig tekst op pagina',
            'pageUrl' => $url,
        ];
    }
    $archBlock = ce_campagne_product_extract_architecture_block($architecture);
    $userPrompt = $archBlock . "\n\nURL: {$url}\n\nPaginatekst:\n" . $text;

    $llm = ce_hba_v2_call_json_for(
        CE_HBA_FN_CAMPAGNE_PRODUCT_EXTRACT,
        ce_campagne_product_extract_system_prompt(),
        $userPrompt,
        0.1,
        4096,
    );

    if (($llm['ok'] ?? false) !== true) {
        return ['ok' => false, 'error' => (string) ($llm['error'] ?? 'Extract mislukt')];
    }

    try {
        $extract = ce_campagne_product_extract_normalize($llm['data'] ?? []);
    } catch (InvalidArgumentException $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }

    return [
        'ok' => true,
        'extract' => $extract,
        'model' => (string) ($llm['model'] ?? ce_hba_model_for(CE_HBA_FN_CAMPAGNE_PRODUCT_EXTRACT)),
        'promptVersion' => CE_CAMPAGNE_PRODUCT_EXTRACT_PROMPT_VERSION,
        'pageUrl' => $url,
    ];
}

/** @param array{wat: string, anders: list<array{citaat: string, branchetaal: bool}>, bewijs: list<array{citaat: string}>, ontbreekt: list<string>} $extract */
function ce_campagne_product_extract_snippet_for_fase(string $faseKey, array $extract): string
{
    $parts = [];
    switch ($faseKey) {
        case 'aankondiging':
            if ($extract['wat'] !== '') {
                $parts[] = 'Product (van pagina): ' . $extract['wat'];
            }
            break;
        case 'waarom':
        case 'hoe_werkt':
        case 'bewust_niet':
            if ($extract['anders'] !== []) {
                $lines = [];
                foreach (array_slice($extract['anders'], 0, 2) as $item) {
                    $flag = ($item['branchetaal'] ?? false) ? ' [branchetaal]' : '';
                    $lines[] = '“' . $item['citaat'] . '”' . $flag;
                }
                $parts[] = 'Anders dan branche (citeer pagina): ' . implode(' ', $lines);
            }
            break;
        case 'bewijs':
        case 'actie':
            if ($extract['bewijs'] !== []) {
                $lines = [];
                foreach (array_slice($extract['bewijs'], 0, 2) as $item) {
                    $lines[] = '“' . $item['citaat'] . '”';
                }
                $parts[] = 'Bewijs van pagina: ' . implode(' ', $lines);
            }
            break;
        default:
            break;
    }

    return implode("\n", $parts);
}

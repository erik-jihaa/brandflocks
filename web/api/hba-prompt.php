<?php

declare(strict_types=1);

/** HBA systeemprompt — server-side only. Niet in frontend bundles. */
/** Ronde 7 spoor 1: foe_vervaging alleen bij doen/aanprijzen van de tegenkracht. */
const CE_HBA_PROMPT_VERSION = 'v1.13';

/** @var list<string> */
const CE_HBA_HOOFDOORDEEL_VALUES = [
    'regelbreuk',
    'tegenkracht',
    'categorietaal',
    'op_merk',
    'neutraal',
];

/** Gedeelde definitie regelbreuk — ook gebruikt door CE-03 stap D en CE-04 (via scripts/prompt_fragments.py). */
function ce_hba_regelbreuk_definitie(): string
{
    static $text = null;
    if ($text !== null) {
        return $text;
    }
    $path = __DIR__ . '/prompts/hba-regelbreuk-definitie.txt';
    $text = is_readable($path) ? trim((string) file_get_contents($path)) : '';

    return $text;
}

/** Gedeelde definitie merkbewering — CE-03 stap D (ronde 6 stap 3a). */
function ce_hba_merkbewering_definitie(): string
{
    static $text = null;
    if ($text !== null) {
        return $text;
    }
    $path = __DIR__ . '/prompts/hba-merkbewering-definitie.txt';
    $text = is_readable($path) ? trim((string) file_get_contents($path)) : '';

    return $text;
}

/** Gedeelde flag-definities — scoreprompt (zie scripts/prompt_fragments.py). */
function ce_hba_flags_definitie(): string
{
    static $text = null;
    if ($text !== null) {
        return $text;
    }
    $path = __DIR__ . '/prompts/hba-flags-definitie.txt';
    $text = is_readable($path) ? trim((string) file_get_contents($path)) : '';

    return $text;
}

function ce_hba_hoofdoordeel_definitie(): string
{
    static $text = null;
    if ($text !== null) {
        return $text;
    }
    $path = __DIR__ . '/prompts/hba-hoofdoordeel-definitie.txt';
    $text = is_readable($path) ? trim((string) file_get_contents($path)) : '';

    return $text;
}

function ce_hba_regel_letterlijk_eis(): string
{
    static $text = null;
    if ($text !== null) {
        return $text;
    }
    $path = __DIR__ . '/prompts/hba-regel-letterlijk.txt';
    $text = is_readable($path) ? trim((string) file_get_contents($path)) : '';

    return $text;
}

function ce_hba_text_uses_derived_boundary(string $text): bool
{
    return (bool) preg_match(
        '/\b(nadert|naderen|naderde|naderend)\b|\bbijna\s+(zes|6)\b|\brond\s+(de\s+)?(zes|6)\b|grens van zes weken/iu',
        $text
    );
}

/** @param array<string, mixed> $data */
function ce_hba_v2_hint_uses_derived_boundary(array $data): bool
{
    $blob = implode("\n", [
        (string) ($data['aanwijzing'] ?? ''),
        (string) ($data['regel_overtreden'] ?? ''),
        (string) ($data['passage_na'] ?? ''),
        (string) ($data['headline_na'] ?? ''),
        (string) ($data['cta_na'] ?? ''),
    ]);

    return ce_hba_text_uses_derived_boundary($blob);
}

function ce_hba_verbeteradvies_definitie(): string
{
    static $text = null;
    if ($text !== null) {
        return $text;
    }
    $path = __DIR__ . '/prompts/hba-verbeteradvies-definitie.txt';
    $text = is_readable($path) ? trim((string) file_get_contents($path)) : '';
    $letterlijk = ce_hba_regel_letterlijk_eis();
    if ($letterlijk !== '' && $text !== '') {
        $text .= "\n\n" . $letterlijk;
    }

    return $text;
}

/** Contentrollen waarbij een lage merkbijdrage tot neutraal leidt. */
function ce_hba_neutrale_contentrollen(): array
{
    return ['service', 'educatie', 'employer_culture', 'activatie', 'reactief'];
}

/**
 * v1.11: merkbijdrage &lt; 6 én neutrale contentrol → neutraal.
 * Anders op_merk. Grens 6 sluit aan op de bestaande early-cap.
 */
function ce_hba_op_merk_of_neutraal(array $tool): string
{
    $rol = trim((string) ($tool['contentrol'] ?? ''));
    $scores = is_array($tool['scores'] ?? null) ? $tool['scores'] : [];
    $bc = is_array($scores['brand_contribution'] ?? null) ? $scores['brand_contribution'] : [];
    $score = isset($bc['score']) ? (float) $bc['score'] : 0.0;
    if (in_array($rol, ce_hba_neutrale_contentrollen(), true) && $score < 6.0) {
        return 'neutraal';
    }

    return 'op_merk';
}

/** @param list<string> $flags */
function ce_hba_flags_from_tool(array $tool): array
{
    $scores = is_array($tool['scores'] ?? null) ? $tool['scores'] : [];
    if (!is_array($scores['architecture_risk']['flags'] ?? null)) {
        return [];
    }

    return array_values(array_map('strval', $scores['architecture_risk']['flags']));
}

/** v1.12 — default ja als het veld ontbreekt (legacy scores vóór merkbewering). */
function ce_hba_parse_merkbewering(array $tool): bool
{
    if (!array_key_exists('merkbewering', $tool) || $tool['merkbewering'] === null || $tool['merkbewering'] === '') {
        return true;
    }
    $raw = $tool['merkbewering'];
    if (is_bool($raw)) {
        return $raw;
    }
    $s = mb_strtolower(trim((string) $raw), 'UTF-8');

    return in_array($s, ['ja', 'yes', 'true', '1'], true);
}

/**
 * Alleen het modelveld (geen category_fallback-fallback).
 * null/leeg telt als nee zodra merkbewering = ja.
 */
function ce_hba_parse_model_kern_categorietaal(array $tool): bool
{
    if (!array_key_exists('kern_categorietaal', $tool) || $tool['kern_categorietaal'] === null || $tool['kern_categorietaal'] === '') {
        return false;
    }
    $raw = $tool['kern_categorietaal'];
    if (is_bool($raw)) {
        return $raw;
    }
    $s = mb_strtolower(trim((string) $raw), 'UTF-8');
    if (in_array($s, ['ja', 'yes', 'true', '1'], true)) {
        return true;
    }
    if (in_array($s, ['nee', 'no', 'false', '0'], true)) {
        return false;
    }

    return false;
}

/** Model levert ja/nee; legacy zonder merkbewering: category_fallback als fallback. */
function ce_hba_parse_kern_categorietaal(array $tool): bool
{
    if (!ce_hba_parse_merkbewering($tool)) {
        return false;
    }
    if (array_key_exists('kern_categorietaal', $tool) && $tool['kern_categorietaal'] !== null && $tool['kern_categorietaal'] !== '') {
        return ce_hba_parse_model_kern_categorietaal($tool);
    }

    return in_array('category_fallback', ce_hba_flags_from_tool($tool), true);
}

/** @param list<string> $flags */
function ce_hba_hoofdoordeel_from_flags(array $flags): ?string
{
    if (in_array('constraint_breach', $flags, true)) {
        return 'regelbreuk';
    }
    if (in_array('foe_vervaging', $flags, true)) {
        return 'tegenkracht';
    }
    if (in_array('category_fallback', $flags, true)) {
        return 'categorietaal';
    }

    return null;
}

/** De kernzin zet zich af tégen de tegenkracht (T10), i.p.v. die over te nemen. */
function ce_hba_kern_keert_zich_tegen_tegenkracht(string $citaat): bool
{
    $t = mb_strtolower($citaat, 'UTF-8');
    if ($t === '') {
        return false;
    }

    return (bool) preg_match(
        '/\\bgeen\\b.+\\b(maar|die)\\b|\\bniet\\b.+\\bmaar\\b|\\bin plaats van\\b/u',
        $t,
    );
}

/** @param array<string, mixed> $tool */
function ce_hba_category_and_foe_share_kern_citation(array $tool): bool
{
    $citaten = is_array($tool['flag_citaten'] ?? null) ? $tool['flag_citaten'] : [];
    $cat = trim((string) ($citaten['category_fallback'] ?? ''));
    $foe = trim((string) ($citaten['foe_vervaging'] ?? ''));
    if ($cat === '' || $foe === '') {
        return false;
    }

    return $cat === $foe;
}

function ce_hba_hoofdoordeel_valid_for_flags(string $hoofdoordeel, array $flags): bool
{
    if (in_array('constraint_breach', $flags, true) && $hoofdoordeel !== 'regelbreuk') {
        return false;
    }
    if ($hoofdoordeel === 'regelbreuk' && !in_array('constraint_breach', $flags, true)) {
        return false;
    }
    if ($hoofdoordeel === 'tegenkracht' && !in_array('foe_vervaging', $flags, true)) {
        return false;
    }
    if ($hoofdoordeel === 'categorietaal') {
        return true;
    }

    return in_array($hoofdoordeel, CE_HBA_HOOFDOORDEEL_VALUES, true);
}

/**
 * v1.13 — hoofdoordeel uit flags, merkbewering, kern_categorietaal, merkbijdrage/contentrol.
 * Trede 2 blijft foe_vervaging + foe_on_kern, zonder && !kern_categorietaal.
 * Het model mag geen hoofdoordeel meer leveren; een eventueel veld wordt genegeerd.
 *
 * @param array<string, mixed> $tool
 * @param array{foe_on_kern?: bool, keert_zich_tegen_tegenkracht?: bool} $context
 */
function ce_hba_resolve_hoofdoordeel(array $tool, array $context = []): string
{
    $flags = ce_hba_flags_from_tool($tool);
    $foeOnKern = !empty($context['foe_on_kern']);
    $merkBew = ce_hba_parse_merkbewering($tool);

    if (in_array('constraint_breach', $flags, true)) {
        return 'regelbreuk';
    }
    // Trede 2 zonder kern_categorietaal-voorwaarde: besluit Rogier 22-10-2026, replay v1.13 19/19 met en zonder.
    if (in_array('foe_vervaging', $flags, true) && $foeOnKern) {
        return 'tegenkracht';
    }
    if (!$merkBew) {
        return ce_hba_op_merk_of_neutraal($tool);
    }
    if (ce_hba_parse_kern_categorietaal($tool)) {
        return 'categorietaal';
    }

    return ce_hba_op_merk_of_neutraal($tool);
}

/**
 * @param array<string, mixed> $tool
 * @return array{tool: array<string, mixed>, flags_verwijderd: list<array{flag: string, reason: string}>}
 */
function ce_hba_drop_foe_vervaging_tegen_tegenkracht(array $tool): array
{
    $flags = ce_hba_flags_from_tool($tool);
    if (!in_array('foe_vervaging', $flags, true)) {
        return ['tool' => $tool, 'flags_verwijderd' => []];
    }
    $citaten = is_array($tool['flag_citaten'] ?? null) ? $tool['flag_citaten'] : [];
    $foeCitaat = trim((string) ($citaten['foe_vervaging'] ?? ''));
    if ($foeCitaat === '' || !ce_hba_kern_keert_zich_tegen_tegenkracht($foeCitaat)) {
        return ['tool' => $tool, 'flags_verwijderd' => []];
    }
    $scores = is_array($tool['scores'] ?? null) ? $tool['scores'] : [];
    $risk = is_array($scores['architecture_risk'] ?? null) ? $scores['architecture_risk'] : [];
    $risk['flags'] = array_values(array_filter(
        $flags,
        static fn (string $f): bool => $f !== 'foe_vervaging',
    ));
    $scores['architecture_risk'] = $risk;
    $tool['scores'] = $scores;

    return [
        'tool' => $tool,
        'flags_verwijderd' => [[
            'flag' => 'foe_vervaging',
            'reason' => 'citaat keert zich tegen de tegenkracht',
        ]],
    ];
}

/**
 * @param array<string, mixed> $tool
 * @param array{foe_on_kern?: bool, keert_zich_tegen_tegenkracht?: bool} $context
 * @return array{tool: array<string, mixed>, hoofdoordeel_gecorrigeerd: ?string, hoofdoordeel_volgt_uit_flags: bool}
 */
function ce_hba_apply_hoofdoordeel_filter(array $tool, array $context = []): array
{
    $merkBew = ce_hba_parse_merkbewering($tool);
    $tool['merkbewering'] = $merkBew ? 'ja' : 'nee';
    if ($merkBew) {
        $tool['kern_categorietaal'] = ce_hba_parse_model_kern_categorietaal($tool)
            ? 'ja'
            : (array_key_exists('kern_categorietaal', $tool) && $tool['kern_categorietaal'] !== null && $tool['kern_categorietaal'] !== ''
                ? 'nee'
                : (in_array('category_fallback', ce_hba_flags_from_tool($tool), true) ? 'ja' : 'nee'));
    } else {
        $tool['kern_categorietaal'] = null;
    }
    $resolved = ce_hba_resolve_hoofdoordeel($tool, $context);
    $tool['hoofdoordeel'] = $resolved;
    unset($tool['hoofdoordeel_model']);

    return [
        'tool' => $tool,
        'hoofdoordeel_gecorrigeerd' => null,
        'hoofdoordeel_volgt_uit_flags' => true,
    ];
}

/**
 * Controle: opgeslagen hoofdoordeel is exact wat de code uit flags zou maken.
 *
 * @param array<string, mixed> $tool
 * @param array{foe_on_kern?: bool} $context
 */
function ce_hba_hoofdoordeel_volgt_uit_flags(array $tool, array $context = []): bool
{
    $stored = trim((string) ($tool['hoofdoordeel'] ?? ''));

    return $stored !== '' && $stored === ce_hba_resolve_hoofdoordeel($tool, $context);
}

/** Klantlabel — intern blijft de waarde (tegenkracht). Eén functie, geen verspreide vertaling. */
function ce_hba_hoofdoordeel_klantlabel(string $hoofdoordeel): string
{
    return match ($hoofdoordeel) {
        'regelbreuk' => 'Regelbreuk',
        'tegenkracht' => 'Positiebreuk',
        'categorietaal' => 'Categorietaal',
        'op_merk' => 'Op merk',
        'neutraal' => 'Neutraal',
        default => '',
    };
}

function ce_hba_hoofdoordeel_klantzin(string $hoofdoordeel): string
{
    return match ($hoofdoordeel) {
        'regelbreuk' => 'Je overschrijdt hier een grens die je zelf hebt gezet.',
        'tegenkracht' => 'Hier redeneer je zoals je tegenstander.',
        'categorietaal' => 'Dit kan iedere concurrent zo zeggen.',
        'op_merk' => 'Dit is herkenbaar jij.',
        'neutraal' => 'Prima voor dit doel; het bouwt niet aan je merk, en dat hoeft ook niet.',
        default => '',
    };
}

/** @return array{hoofdoordeel: ?string, hoofdoordeelGecorrigeerd: ?string, hoofdoordeelVolgtUitFlags: ?bool, kernCategorietaal: ?string, constraintBreachVerificatie: ?array<string, mixed>} */
function ce_hba_hoofdoordeel_from_raw(mixed $raw): array
{
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        $raw = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($raw)) {
        return [
            'hoofdoordeel' => null,
            'hoofdoordeelGecorrigeerd' => null,
            'hoofdoordeelVolgtUitFlags' => null,
            'kernCategorietaal' => null,
            'constraintBreachVerificatie' => null,
        ];
    }

    $volgt = array_key_exists('hoofdoordeel_volgt_uit_flags', $raw)
        ? filter_var($raw['hoofdoordeel_volgt_uit_flags'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
        : null;

    return [
        'hoofdoordeel' => isset($raw['hoofdoordeel']) ? (string) $raw['hoofdoordeel'] : null,
        'hoofdoordeelGecorrigeerd' => isset($raw['hoofdoordeel_gecorrigeerd'])
            ? (string) $raw['hoofdoordeel_gecorrigeerd']
            : null,
        'hoofdoordeelVolgtUitFlags' => $volgt,
        'kernCategorietaal' => isset($raw['kern_categorietaal']) ? (string) $raw['kern_categorietaal'] : null,
        'constraintBreachVerificatie' => isset($raw['constraint_breach_verificatie']) && is_array($raw['constraint_breach_verificatie'])
            ? $raw['constraint_breach_verificatie']
            : null,
    ];
}

function ce_hba_normalize_text_for_citation_match(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    $text = str_replace(['"', "'", '«', '»', '“', '”', '‘', '’'], '', $text);
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

    return trim($text);
}

/** @return array{tool: array<string, mixed>, flags_verwijderd: list<array{flag: string, reason: string}>} */
function ce_hba_apply_flag_citation_filter(array $tool, string $postText): array
{
    $removed = [];
    $scores = is_array($tool['scores'] ?? null) ? $tool['scores'] : null;
    if ($scores === null) {
        return ['tool' => $tool, 'flags_verwijderd' => []];
    }
    $risk = is_array($scores['architecture_risk'] ?? null) ? $scores['architecture_risk'] : null;
    if ($risk === null) {
        return ['tool' => $tool, 'flags_verwijderd' => []];
    }
    $flags = is_array($risk['flags'] ?? null) ? $risk['flags'] : [];
    if ($flags === []) {
        return ['tool' => $tool, 'flags_verwijderd' => []];
    }

    $citaten = is_array($tool['flag_citaten'] ?? null) ? $tool['flag_citaten'] : [];
    $postNorm = ce_hba_normalize_text_for_citation_match($postText);
    $kept = [];

    foreach ($flags as $flag) {
        $flagKey = (string) $flag;
        $citaat = trim((string) ($citaten[$flagKey] ?? ''));
        if ($citaat === '') {
            $removed[] = ['flag' => $flagKey, 'reason' => 'geen citaat in flag_citaten'];
            continue;
        }
        $citaatNorm = ce_hba_normalize_text_for_citation_match($citaat);
        if ($citaatNorm === '' || !str_contains($postNorm, $citaatNorm)) {
            $removed[] = ['flag' => $flagKey, 'reason' => 'citaat niet in posttekst'];
            continue;
        }
        $kept[] = $flagKey;
    }

    $risk['flags'] = $kept;
    $scores['architecture_risk'] = $risk;
    $tool['scores'] = $scores;

    return ['tool' => $tool, 'flags_verwijderd' => $removed];
}

/**
 * v1.9: constraint_breach zonder ingevulde uitleg (handeling) vervalt.
 *
 * @return array{tool: array<string, mixed>, flags_verwijderd: list<array{flag: string, reason: string}>}
 */
function ce_hba_apply_constraint_breach_uitleg_filter(array $tool): array
{
    $scores = is_array($tool['scores'] ?? null) ? $tool['scores'] : null;
    if ($scores === null) {
        return ['tool' => $tool, 'flags_verwijderd' => []];
    }
    $risk = is_array($scores['architecture_risk'] ?? null) ? $scores['architecture_risk'] : null;
    if ($risk === null) {
        return ['tool' => $tool, 'flags_verwijderd' => []];
    }
    $flags = is_array($risk['flags'] ?? null) ? $risk['flags'] : [];
    if (!in_array('constraint_breach', $flags, true)) {
        return ['tool' => $tool, 'flags_verwijderd' => []];
    }

    $citaten = is_array($tool['flag_citaten'] ?? null) ? $tool['flag_citaten'] : [];
    $uitleg = trim((string) ($citaten['constraint_breach_uitleg'] ?? ''));
    if ($uitleg !== '') {
        return ['tool' => $tool, 'flags_verwijderd' => []];
    }

    $risk['flags'] = array_values(array_filter(
        $flags,
        static fn ($flag): bool => (string) $flag !== 'constraint_breach',
    ));
    $scores['architecture_risk'] = $risk;
    $tool['scores'] = $scores;

    return [
        'tool' => $tool,
        'flags_verwijderd' => [[
            'flag' => 'constraint_breach',
            'reason' => 'geen constraint_breach_uitleg (handeling ontbreekt)',
        ]],
    ];
}

function ce_hba_constraint_breach_verification_prompt(
    string $geloofwaardigheidstest,
    string $citaat,
    string $uitleg,
    string $verbodenLogica = '',
): string {
    $verbodenBlock = trim($verbodenLogica) !== ''
        ? "\n\nVERBODEN LOGICA (zelfde grenzen als bij scoring):\n{$verbodenLogica}\n"
        : '';

    return <<<PROMPT
Je verifieert of een gemelde regelbreuk standhoudt. Geen scoring, geen herschrijven.

REGEL (geloofwaardigheidstest):
{$geloofwaardigheidstest}
{$verbodenBlock}
GECITEERDE ZIN:
{$citaat}

GEMELDE UITLEG (welke handeling uit de regel het merk volgens die zin toch zou doen):
{$uitleg}

VRAAG: Doet het merk volgens deze zin aantoonbaar wat de regel verbiedt?

JA alleen als de zin een handeling van het merk beschrijft: iets wat het merk doet, aanbiedt, tekent of als werkwijze heeft, en wat de geloofwaardigheidstest of de verboden logica verbiedt.

NEE als het alleen een woord of een claim is. "Partner", "snel", "efficiënt" of "betrouwbaar" breken geen regel.
NEE als de zin categorietaal is zonder die handeling. Voorbeeld: "Wij zijn uw betrouwbare partner" meldt geen partnerschap met een leverancier — dat is geen regelbreuk.
NEE als de zin de logica van de tegenkracht overneemt zonder een verboden handeling. Voorbeeld: "Dankzij slimmere routes zijn onze zorgverleners minder onderweg" — het merk doet niets wat de regel verbiedt.
JA als de zin een verboden handeling van het merk is. Voorbeeld: "Wij zijn trots op ons nieuwe partnerschap met [ERP-leverancier]" — het merk gaat een partnerschap aan.

Antwoord via de tool. ja = true of false. reden = één zin.
PROMPT;
}

/** @return array<string, mixed> */
function ce_hba_constraint_breach_verification_tool_schema(): array
{
    return [
        'name' => 'constraint_breach_verificatie',
        'description' => 'Ja of nee: doet de geciteerde zin aantoonbaar wat de regel verbiedt?',
        'input_schema' => [
            'type' => 'object',
            'required' => ['ja', 'reden'],
            'properties' => [
                'ja' => ['type' => 'boolean'],
                'reden' => ['type' => 'string'],
            ],
        ],
    ];
}

/**
 * @param array<string, mixed> $tool
 * @return array{ja: bool, reden: string}|null
 */
function ce_hba_parse_constraint_breach_verification_tool(array $tool): ?array
{
    if (!array_key_exists('ja', $tool)) {
        return null;
    }
    $ja = $tool['ja'];
    if (is_string($ja)) {
        $lower = strtolower(trim($ja));
        $ja = in_array($lower, ['ja', 'true', '1', 'yes'], true);
    }
    if (!is_bool($ja)) {
        return null;
    }
    $reden = trim((string) ($tool['reden'] ?? ''));

    return ['ja' => $ja, 'reden' => $reden];
}

/**
 * @param array<string, mixed> $tool
 * @return array<string, mixed>
 */
function ce_hba_drop_constraint_breach_flag(array $tool): array
{
    $scores = is_array($tool['scores'] ?? null) ? $tool['scores'] : [];
    $risk = is_array($scores['architecture_risk'] ?? null) ? $scores['architecture_risk'] : [];
    $flags = is_array($risk['flags'] ?? null) ? $risk['flags'] : [];
    $risk['flags'] = array_values(array_filter(
        $flags,
        static fn ($flag): bool => (string) $flag !== 'constraint_breach',
    ));
    $scores['architecture_risk'] = $risk;
    $tool['scores'] = $scores;

    return $tool;
}

/** @param array<string, mixed> $narrative */
function ce_hba_archetype_market_match_block(PDO $pdo, array $narrative): string
{
    require_once __DIR__ . '/schema-helper.php';
    require_once __DIR__ . '/hba-v2-repository.php';
    $archetype = ce_hba_v2_map_archetype(trim((string) ($narrative['archetype'] ?? '')));
    if ($archetype === '') {
        return '';
    }
    $brancheCode = trim((string) ($narrative['branche_code'] ?? ''));
    if ($brancheCode === '' || !ce_table_exists($pdo, 'branche_library')) {
        return '';
    }
    $stmt = $pdo->prepare('SELECT markt_archetype FROM branche_library WHERE branche_code = :code LIMIT 1');
    $stmt->execute(['code' => $brancheCode]);
    $markt = ce_hba_v2_map_archetype(trim((string) ($stmt->fetchColumn() ?: '')));
    if ($markt === '' || mb_strtolower($archetype, 'UTF-8') !== mb_strtolower($markt, 'UTF-8')) {
        return '';
    }

    return "Het archetype van dit merk ({$archetype}) is ook het gangbare archetype van de "
        . "branche. Passen bij het archetype maakt een tekst hier dus niet onderscheidend. "
        . "Geef distinctiviteit boven 2 alleen als de tekst een concreet element van het "
        . "mechanisme of de geloofwaardigheidstest laat zien: een handeling, een getal of "
        . "een keuze die geld of groei kost. Een zorgzame, warme of betrokken toon zonder "
        . "zo'n element is categorietaal: distinctiviteit hoogstens 2, en category_fallback.";
}

/** @return array{ja: bool, reden: string}|null */
function ce_hba_constraint_breach_verificatie_from_raw(mixed $raw): ?array
{
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        $raw = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($raw)) {
        return null;
    }
    $block = $raw['constraint_breach_verificatie'] ?? null;
    if (!is_array($block)) {
        return null;
    }
    if (!array_key_exists('ja', $block)) {
        return null;
    }
    $ja = $block['ja'];
    if (is_string($ja)) {
        $lower = strtolower(trim($ja));
        $ja = in_array($lower, ['ja', 'true', '1', 'yes'], true);
    }
    if (!is_bool($ja)) {
        return null;
    }
    $reden = trim((string) ($block['reden'] ?? ''));

    return ['ja' => $ja, 'reden' => $reden];
}

/** @return list<array{flag: string, reason: string}> */
function ce_hba_flags_verwijderd_from_raw(mixed $raw): array
{
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        $raw = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($raw)) {
        return [];
    }
    $items = $raw['flags_verwijderd'] ?? [];
    if (!is_array($items)) {
        return [];
    }
    $out = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $flag = trim((string) ($item['flag'] ?? ''));
        if ($flag === '') {
            continue;
        }
        $out[] = [
            'flag' => $flag,
            'reason' => trim((string) ($item['reason'] ?? '')),
        ];
    }

    return $out;
}

/** @return array<string, string> flag_key → citaat */
function ce_hba_flag_citaten_from_raw(mixed $raw): array
{
    if (is_string($raw)) {
        $decoded = json_decode($raw, true);
        $raw = is_array($decoded) ? $decoded : [];
    }
    if (!is_array($raw)) {
        return [];
    }
    $citaten = $raw['flag_citaten'] ?? [];
    if (!is_array($citaten)) {
        return [];
    }
    $out = [];
    foreach ($citaten as $flag => $citaat) {
        $key = trim((string) $flag);
        $text = trim((string) $citaat);
        if ($key !== '' && $text !== '') {
            $out[$key] = $text;
        }
    }

    return $out;
}

/** @return list<string> */
function ce_hba_forbidden_terms(): array
{
    return [
        "hero's transformation",
        'de klant als hero',
        'guide',
        'storytelling',
        'probleem-oplossing',
        'on brand',
        'off brand',
        'brand purpose',
        'brand mission',
        'tone of voice',
        'authentiek',
        ' authentiek',
        ' echt ',
        ' puur ',
    ];
}

/**
 * V5 — vaktermen die niet in verbeteradvies (klantzichtbaar) mogen.
 *
 * @return list<string>
 */
function ce_hba_verbeteradvies_forbidden_terms(): array
{
    return [
        'mechanisme',
        'tegenkracht',
        'geloofwaardigheidstest',
        'archetype',
        'merklogica',
        'branchenorm',
        'categorietaal',
        'hoofdoordeel',
    ];
}

function ce_hba_format_list(mixed $value): string
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            $value = $decoded;
        } else {
            return trim($value);
        }
    }
    if (!is_array($value)) {
        return '';
    }
    $items = array_values(array_filter(array_map(static fn ($v): string => trim((string) $v), $value)));
    if ($items === []) {
        return '';
    }

    return '- ' . implode("\n- ", $items);
}

/** @param array<string, mixed> $narrative @param array{version: int, observed: list<string>, categoryConditions: list<string>}|null $hygieneContext */
function ce_hba_build_system_prompt(
    array $narrative,
    string $platform,
    string $format,
    string $postText,
    ?array $hygieneContext = null,
    ?PDO $pdo = null,
): string
{
    $template = <<<'PROMPT'
# ROL EN OPDRACHT
Je bent de diagnostische laag van de Hero Brand Approach (HBA). Je beoordeelt of een contentstuk bijdraagt aan de merkarchitectuur van het merk dat hieronder is gedefinieerd.
Je bent GEEN tekstschrijver, geen tone-of-voice-bot en geen StoryBrand-assistent.

# KERNREGEL
Identiteit (archetype) bepaalt welk doel logisch is. Het doel maakt een specifieke Foe noodzakelijk. De Foe maakt opnieuw zichtbaar waarom juist dit merk nodig is. Wanneer deze causale keten ontbreekt, ontstaat geen positionering maar uitleg. Beoordeel content tegen deze keten, niet tegen toon of stijl.

# BRAND NARRATIVE
Archetype: {{archetype}}
Doel: {{doel}}
Tegenkracht (Foe): {{foe}}
Spanning (een zin): {{spanning}}
Mechanisme: {{mechanisme}}
Geloofwaardigheidstest: {{geloofwaardigheidstest}}
Verboden logica voor dit merk:
{{verboden_logica}}
Bewijsvelden:
{{bewijsvelden}}
Tone of behaviour: {{tone_of_behaviour}}
Woorden die passen:
{{woorden_die_passen}}
Woorden die niet passen:
{{woorden_die_niet_passen}}
Goede voorbeelden:
{{goede_voorbeelden}}
Zwakke/generieke voorbeelden:
{{zwakke_voorbeelden}}
Kernformuleringen (terugkerende zinnen uit de merkarchitectuur):
{{kernformuleringen}}
Contextprofiel: {{contextprofiel}}
{{hygiene_block}}

# CONTENT POST
Platform: {{platform}}
Format: {{format}}
Tekst:
{{post_tekst}}

# WERKWIJZE - VOLG IN DEZE VOLGORDE
1. Controleer of de brand narrative compleet en logisch sluit. Verplichte velden zijn: archetype, doel, Foe, spanning, mechanisme, geloofwaardigheidstest, bewijsvelden, verboden_logica, tone_of_behaviour, woorden_die_passen, woorden_die_niet_passen, goede_voorbeelden, zwakke_voorbeelden en contextprofiel. Als een verplicht veld ontbreekt of leeg is: retourneer JSON met status narrative_incomplete, benoem alle ontbrekende of inconsistente velden en score niet.
2. Controleer de causale keten: archetype -> doel -> Foe -> mechanisme -> grens. Als archetype, doel en Foe wel gevuld zijn maar intern niet logisch uit elkaar volgen, retourneer narrative_incomplete met missing_or_inconsistent op de conflicterende velden. Vul niets zelf in vanuit categoriekennis, Jungiaanse theorie of StoryBrand.
3. Gebruik goede_voorbeelden en zwakke_voorbeelden als kalibratie-ankers. Kopieer ze niet in de output.
4. Bepaal de contentrol van deze post. Kies een van: positionering, bewijs, educatie, activatie, community, service, employer_culture, reactief.
5. Scoor op Brand Contribution (0-10), Brand Consistency (0-10), Architecture Risk (0-10, laag = goed).
   Bij distinctiviteit: gebruik de categorie-hygiëne hierboven als referentie. Formuleringen uit waargenomen categorietaal of categorievoorwaarden die het merk als onderscheid presenteert, scoren lager op distinctiviteit — noem dit in de diagnose zonder de lijst te citeren.
   Zet architecture_risk.flags volgens # FLAGS; constraint_breach alleen volgens # REGELBREUK.
   Vul flag_citaten: per gemelde flag de letterlijke zin uit de posttekst.
   Bij foe_vervaging: zet ook flag_citaten.foe_vervaging_uitleg (één zin: welk deel van de tegenkracht die zin overneemt).
   Bij constraint_breach: zet ook flag_citaten.constraint_breach_uitleg (één zin: welke handeling uit de regel het merk volgens die zin toch doet). Zonder die uitleg geen constraint_breach.
   Beoordeel functionele onderdelen niet (zie # FLAGS).
   Merkbijdrage bij niet-onderbouwde kern:
   - proof_gap of foe_vervaging op kop of openingszin: brand_contribution.score hoogstens 5.
   - proof_gap op een vroege kernzin (typisch eerste alinea: samenwerking, ervaring of resultaat
     waar de post op draait, niet op een korte ondersteunende bijzin): hoogstens 6.
   - proof_gap alleen op een ondersteunende bijzin terwijl de lijn verder klopt: geen harde cap,
     wel lagere merkbijdrage dan zonder proof_gap.
   Lever geen hoofdoordeel. Zet merkbewering op ja of nee en kern_categorietaal alleen bij merkbewering = ja (zie # HOOFDDOORDEEL).
6. Bereken long_term_value en short_term_value (beide 0-5).
7. Pas de meetlat aan op de contentrol.
8. Schrijf diagnose (2-4 zinnen, HBO-nederlands) en verbeteradvies volgens # VERBETERADVIES.
   Diagnose en verbeteradvies zijn de toelichting in het zijpaneel; noemen ze de regel,
   dan letterlijk zoals in de geloofwaardigheidstest (zie REGEL LETTERLIJK).
9. Bepaal kernformulering_hit: 1-based index als een kernformulering herkenbaar in de post voorkomt (eigen woorden of letterlijk), anders null. Geen lagere score als null — dit is waarneming, geen norm per post. Geef kort kernformulering_motivation.

# {{regelbreuk_definitie}}

# {{flags_definitie}}

# {{hoofdoordeel_definitie}}

# {{verbeteradvies_definitie}}

{{archetype_bewijslast_block}}

# VERBODEN TAAL - HARDE REGEL
Gebruik niet: Hero's transformation, de klant als hero, guide, storytelling (algemeen), probleem-oplossing, on brand/off brand, brand purpose/mission, tone of voice als hoofdcriterium, authentiek/echt/puur als kwalificatie.
In verbeteradvies bovendien nooit: mechanisme, tegenkracht, geloofwaardigheidstest, archetype, merklogica, branchenorm, categorietaal, hoofdoordeel.

# OUTPUT
Gebruik de tool hba_score. Geen tekst buiten de tool.
PROMPT;

    $hygieneBlock = '';
    if (is_array($hygieneContext)) {
        $observed = $hygieneContext['observed'] ?? [];
        $category = $hygieneContext['categoryConditions'] ?? [];
        if ($observed !== [] || $category !== []) {
            $hygieneBlock = "\n# CATEGORIE-HYGIËNE (referentie voor distinctiviteit — niet tonen aan gebruiker)\n";
            if ($observed !== []) {
                $hygieneBlock .= "Waargenomen categorietaal (meerdere partijen in deze markt):\n"
                    . ce_hba_format_list($observed) . "\n";
            }
            if ($category !== []) {
                $hygieneBlock .= "Categorievoorwaarden (koper verwacht dit — uitspreken voegt niets toe):\n"
                    . ce_hba_format_list($category) . "\n";
            }
        }
    }

    $foeText = (string) ($narrative['foe'] ?? '');
    // flags_definitie bevat {{foe}}; invullen vóór insert, anders blijft de placeholder staan.
    $flagsDefinitie = str_replace(
        ['{{foe}}', '{foe}'],
        $foeText,
        ce_hba_flags_definitie()
    );

    $replacements = [
        '{{archetype}}' => (string) ($narrative['archetype'] ?? ''),
        '{{doel}}' => (string) ($narrative['doel'] ?? ''),
        '{{foe}}' => $foeText,
        '{{spanning}}' => (string) ($narrative['spanning'] ?? ''),
        '{{mechanisme}}' => (string) ($narrative['mechanisme'] ?? ''),
        '{{geloofwaardigheidstest}}' => (string) ($narrative['geloofwaardigheidstest'] ?? ''),
        '{{verboden_logica}}' => ce_hba_format_list($narrative['verboden_logica'] ?? []),
        '{{bewijsvelden}}' => ce_hba_format_list($narrative['bewijsvelden'] ?? []),
        '{{tone_of_behaviour}}' => (string) ($narrative['tone_of_behaviour'] ?? ''),
        '{{woorden_die_passen}}' => ce_hba_format_list($narrative['woorden_die_passen'] ?? []),
        '{{woorden_die_niet_passen}}' => ce_hba_format_list($narrative['woorden_die_niet_passen'] ?? []),
        '{{goede_voorbeelden}}' => ce_hba_format_list($narrative['goede_voorbeelden'] ?? []),
        '{{zwakke_voorbeelden}}' => ce_hba_format_list($narrative['zwakke_voorbeelden'] ?? []),
        '{{kernformuleringen}}' => ce_hba_format_list($narrative['kernformuleringen'] ?? []),
        '{{contextprofiel}}' => (string) ($narrative['contextprofiel'] ?? ''),
        '{{hygiene_block}}' => $hygieneBlock,
        '{{regelbreuk_definitie}}' => ce_hba_regelbreuk_definitie(),
        '{{flags_definitie}}' => $flagsDefinitie,
        '{{hoofdoordeel_definitie}}' => ce_hba_hoofdoordeel_definitie(),
        '{{verbeteradvies_definitie}}' => ce_hba_verbeteradvies_definitie(),
        '{{archetype_bewijslast_block}}' => ($pdo instanceof PDO)
            ? ce_hba_archetype_market_match_block($pdo, $narrative)
            : '',
        '{{platform}}' => $platform !== '' ? $platform : 'website_blog',
        '{{format}}' => $format !== '' ? $format : 'article',
        '{{post_tekst}}' => $postText,
    ];

    return str_replace(array_keys($replacements), array_values($replacements), $template);
}

/** @return array<string, mixed> Anthropic tool definition */
function ce_hba_score_tool_schema(): array
{
    $dimensionSchema = [
        'type' => 'object',
        'properties' => [
            'archetype_fit' => ['type' => 'number', 'minimum' => 0, 'maximum' => 5],
            'doel_fit' => ['type' => 'number', 'minimum' => 0, 'maximum' => 5],
            'tegenkracht_fit' => ['type' => 'number', 'minimum' => 0, 'maximum' => 5],
            'spanning' => ['type' => 'number', 'minimum' => 0, 'maximum' => 5],
            'mechanisme_proof' => ['type' => 'number', 'minimum' => 0, 'maximum' => 5],
            'distinctiviteit' => ['type' => 'number', 'minimum' => 0, 'maximum' => 5],
        ],
    ];

    return [
        'name' => 'hba_score',
        'description' => 'HBA scoring result for a content post',
        'input_schema' => [
            'type' => 'object',
            'required' => ['status', 'narrative_check'],
            'properties' => [
                'status' => ['type' => 'string', 'enum' => ['scored', 'narrative_incomplete']],
                'narrative_check' => [
                    'type' => 'object',
                    'properties' => [
                        'complete' => ['type' => 'boolean'],
                        'missing_or_inconsistent' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                    ],
                ],
                'contentrol' => [
                    'type' => ['string', 'null'],
                    'enum' => ['positionering', 'bewijs', 'educatie', 'activatie', 'community', 'service', 'employer_culture', 'reactief', null],
                ],
                'contentrol_motivation' => ['type' => ['string', 'null']],
                'kernformulering_hit' => ['type' => ['integer', 'null']],
                'kernformulering_motivation' => ['type' => ['string', 'null']],
                'scores' => [
                    'type' => ['object', 'null'],
                    'properties' => [
                        'brand_contribution' => [
                            'type' => 'object',
                            'properties' => [
                                'score' => ['type' => 'number', 'minimum' => 0, 'maximum' => 10],
                                'dimensions' => $dimensionSchema,
                            ],
                            'required' => ['score', 'dimensions'],
                        ],
                        'brand_consistency' => [
                            'type' => 'object',
                            'properties' => [
                                'score' => ['type' => 'number', 'minimum' => 0, 'maximum' => 10],
                                'notes' => ['type' => 'string'],
                            ],
                            'required' => ['score'],
                        ],
                        'architecture_risk' => [
                            'type' => 'object',
                            'properties' => [
                                'score' => ['type' => 'number', 'minimum' => 0, 'maximum' => 10],
                                'flags' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'string',
                                        'enum' => [
                                            'category_fallback',
                                            'archetype_drift',
                                            'proof_gap',
                                            'constraint_breach',
                                            'opportunistische_trendcontent',
                                            'foe_vervaging',
                                        ],
                                    ],
                                ],
                            ],
                            'required' => ['score', 'flags'],
                        ],
                        'long_term_value' => ['type' => 'number', 'minimum' => 0, 'maximum' => 5],
                        'short_term_value' => ['type' => 'number', 'minimum' => 0, 'maximum' => 5],
                    ],
                ],
                'diagnose' => ['type' => ['string', 'null']],
                'verbeteradvies' => ['type' => ['string', 'null']],
                'flag_citaten' => [
                    'type' => 'object',
                    'description' => 'Per flag de letterlijke zin; bij foe_vervaging ook foe_vervaging_uitleg; bij constraint_breach ook constraint_breach_uitleg (welke handeling uit de regel).',
                    'additionalProperties' => ['type' => 'string'],
                ],
                'merkbewering' => [
                    'type' => 'string',
                    'enum' => ['ja', 'nee'],
                    'description' => 'ja als de tekst iets beweert over wat het merk is, doet of belooft; nee bij tips, vacatures, service en aankondigingen zonder merkclaim.',
                ],
                'kern_categorietaal' => [
                    'type' => ['string', 'null'],
                    'enum' => ['ja', 'nee'],
                    'description' => 'Alleen invullen als merkbewering = ja: ja als de kern categorietaal is. Anders null.',
                ],
            ],
        ],
    ];
}

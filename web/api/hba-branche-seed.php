<?php

declare(strict_types=1);

require_once __DIR__ . '/schema-helper.php';

/** Rogier HBA branche-bibliotheek startset — briefing/spec/hba-input/Branchebibliotheek-voor-Erik-2nov.zip */
function ce_hba_branche_seed_rows(): array
{
    return [
        [
            'code' => 'zakelijke_dienstverlening',
            'label' => 'Zakelijke dienstverlening / consultancy',
            'markt_archetype' => 'Wijze',
            'hygienefactoren' => ['Deskundigheid', 'betrouwbaarheid', 'discretie', 'aantoonbare resultaten', 'senioriteit van het team'],
            'categorietaal' => ['Wij ontzorgen', 'strategische partner', 'op maat', 'thought leadership', 'wij denken met u mee', 'bewezen aanpak', 'resultaatgericht'],
        ],
        [
            'code' => 'supply_chain_logistiek',
            'label' => 'Supply chain / logistiek',
            'markt_archetype' => 'Verzorger',
            'hygienefactoren' => ['Betrouwbaarheid', 'tracking', 'veiligheid', 'leverbetrouwbaarheid', 'schaalbaarheid'],
            'categorietaal' => ['End-to-end', 'seamless', 'realtime inzicht', 'wij ontzorgen uw keten', 'innovatieve oplossingen', 'uw betrouwbare partner'],
        ],
        [
            'code' => 'bouw_techniek',
            'label' => 'Bouw / techniek / installatie',
            'markt_archetype' => 'Maker',
            'hygienefactoren' => ['Vakmanschap', 'veiligheid', 'betrouwbaarheid', 'oplevering op tijd', 'garantie'],
            'categorietaal' => ['Kwaliteit staat voorop', 'vakmanschap', 'van A tot Z', 'de klant centraal', 'no-nonsense', 'afspraak is afspraak'],
        ],
        [
            'code' => 'zorg_welzijn',
            'label' => 'Zorg / welzijn',
            'markt_archetype' => 'Verzorger',
            'hygienefactoren' => ['Deskundigheid', 'veiligheid', 'empathie', 'betrouwbaarheid', 'aandacht voor de cliënt'],
            'categorietaal' => ['De mens centraal', 'persoonlijke aandacht', 'samen sterker', 'kwaliteit van leven', 'met hart en ziel'],
        ],
        [
            'code' => 'tech_saas',
            'label' => 'Technologie / software / SaaS',
            'markt_archetype' => 'Magier',
            'hygienefactoren' => ['Betrouwbaarheid', 'veiligheid', 'schaalbaarheid', 'gebruiksgemak', 'support'],
            'categorietaal' => ['Innovatief', 'next-gen', 'empowering', 'seamless', 'wij maken het makkelijk', 'de toekomst van...', 'game-changer'],
        ],
        [
            'code' => 'retail',
            'label' => 'Retail / consumentenmerken',
            'markt_archetype' => 'Buur',
            'hygienefactoren' => [
                'Prijs-kwaliteitverhouding',
                'beschikbaarheid',
                'service',
                'betrouwbaarheid van levering',
                'branddatum op de verpakking',
            ],
            'categorietaal' => ['Beste prijs', 'voor ieder wat wils', 'kwaliteit voor iedereen', 'wij begrijpen jou', 'jouw partner in...'],
        ],
        [
            'code' => 'koffie',
            'label' => 'Koffiebranderij / specialty coffee',
            'markt_archetype' => 'Maker',
            'hygienefactoren' => [
                'vers gebrand',
                'duurzaam',
                'fairtrade',
                'branddatum op de verpakking',
                'lokaal gebrand',
            ],
            'categorietaal' => [
                'versgebrande koffie',
                'passie voor kwaliteit',
                'duurzame koffiebranderij',
                'de lekkerste koffie',
            ],
        ],
    ];
}

function ce_hba_seed_branche_library(PDO $pdo): void
{
    if (!ce_table_exists($pdo, 'branche_library')) {
        return;
    }
    $count = (int) ($pdo->query('SELECT COUNT(*) FROM branche_library')->fetchColumn() ?: 0);
    if ($count > 0) {
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO branche_library (id, branche_code, branche_label, markt_archetype, hygienefactoren, categorietaal, bron)
         VALUES (:id, :code, :label, :markt_archetype, :hygienefactoren, :categorietaal, :bron)'
    );

    foreach (ce_hba_branche_seed_rows() as $row) {
        $id = sprintf(
            '%s-%s-%s-%s-%s',
            bin2hex(random_bytes(4)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(2)),
            bin2hex(random_bytes(6)),
        );
        $stmt->execute([
            'id' => $id,
            'code' => $row['code'],
            'label' => $row['label'],
            'markt_archetype' => $row['markt_archetype'],
            'hygienefactoren' => json_encode($row['hygienefactoren'], JSON_UNESCAPED_UNICODE),
            'categorietaal' => json_encode($row['categorietaal'], JSON_UNESCAPED_UNICODE),
            'bron' => 'gecureerd',
        ]);
    }
}

<?php

declare(strict_types=1);

require_once __DIR__ . '/../web/api/hba-hint-verificatie.php';

$brandpunt = 'Wij verkopen geen koffie die langer dan zes weken geleden is gebrand, ook niet met korting. Wat over is, gaat naar de buurtvoedselbank of wordt weggegooid.';

$parafrase = 'je geen korting geeft op oudere voorraad';
$literal = ce_hba_resolve_literal_brand_regel($parafrase, $brandpunt, []);
if ($literal !== $brandpunt) {
    fwrite(STDERR, "FAIL resolve parafrase → letterlijk\n");
    exit(1);
}

$comment = ce_hba_ce04_regelbreuk_comment($brandpunt, 'Black Friday: alle zakken van twee maanden geleden voor de halve prijs!');
if (!str_starts_with($comment, 'Dit stuk gaat in tegen je regel: "')) {
    fwrite(STDERR, "FAIL comment prefix\n");
    exit(1);
}
if (!str_contains($comment, 'weggegooid."')) {
    fwrite(STDERR, "FAIL comment: punt binnen aanhalingstekens\n");
    exit(1);
}
if (str_contains($comment, '". In je')) {
    fwrite(STDERR, "FAIL comment: punt buiten aanhalingstekens\n");
    exit(1);
}

$duinroosRegels = 'Een cliënt krijgt nooit meer dan vier verschillende zorgverleners per maand over de vloer, ook als we daarvoor een nieuwe cliënt moeten doorverwijzen.';
$wrongVerboden = 'wachtlijsten weggewerkt door extra inzet van invalkrachten';
$resolvedDuinroos = ce_hba_resolve_literal_brand_regel($wrongVerboden, $duinroosRegels, [$wrongVerboden]);
if ($resolvedDuinroos === $wrongVerboden) {
    fwrite(STDERR, "FAIL Duinroos: mag verboden_logica niet als regel citeren\n");
    exit(1);
}
if (!ce_hba_regel_literally_in_jullie_regels($duinroosRegels, $duinroosRegels)) {
    fwrite(STDERR, "FAIL jullie regels check\n");
    exit(1);
}
if (!str_contains($comment, $brandpunt)) {
    fwrite(STDERR, "FAIL comment bevat regel\n");
    exit(1);
}
if (!ce_ce04_comment_is_regelbreuk($comment)) {
    fwrite(STDERR, "FAIL regelbreuk detector\n");
    exit(1);
}

echo "OK ce04-regel-letterlijk\n";

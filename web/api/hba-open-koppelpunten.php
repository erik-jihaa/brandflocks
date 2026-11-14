<?php

declare(strict_types=1);

/**
 * OPEN KOPPELPUNTEN — drempels, drempelwaarden en toon-definities (bouwaddendum §0).
 *
 * Alleen Rogier vult concrete waarden in (besluit B6). Tot die tijst: null hier, nergens
 * elders in de codebase "redelijk lijkende" drempels zetten.
 *
 * @return array<string, null|int|float|string|bool>
 */
function ce_hba_open_koppelpunten_catalog(): array
{
    return [
        // OPEN KOPPELPUNT — oppakbaarheid / pickup power (addendum §4, besluit B3–B5)
        'pickup_power_low' => null,
        'pickup_power_medium' => null,
        'pickup_power_high' => null,
        'pickup_maar_drift_driftsignaal' => null,

        // OPEN KOPPELPUNT — architectuurcheck na wizard (HBA check-addendum)
        'architectuur_signaal_waarschuwing' => null,
        'architectuur_signaal_kritiek' => null,

        // Uit drempels-thema-onderbouwing.md (§1–3): min. 2 fragmenten = normaal onderbouwd.
        'thema_min_bruikbare_bronnen' => 2,
        'thema_licht_onderbouwd_bronnen' => 2,

        // OPEN KOPPELPUNT — voorrangsregel copy-modus vs kanaaltabel bij CTA (besluit B7)
        'cta_voorrang_copy_modus_vs_kanaal' => null,
    ];
}

/** @param array-key-of-return-array-of-ce_hba_open_koppelpunten_catalog $key */
function ce_hba_open_koppelpunt(string $key): null|int|float|string|bool
{
    $catalog = ce_hba_open_koppelpunten_catalog();

    return array_key_exists($key, $catalog) ? $catalog[$key] : null;
}

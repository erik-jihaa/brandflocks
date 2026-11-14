/**
 * Drempels thema-onderbouwing — `briefing/spec/hba-input/drempels-thema-onderbouwing.md`.
 * Aantal thema's = floor(bruikbare bronfragmenten / 2), max 6.
 */

export const THEME_PROPOSAL_MAX = 6

/** Aantal voorgestelde thema's bij gegeven bruikbare bronfragmenten. */
export function themeProposalCountFromFragments(usableFragments: number): number {
  const n = Math.max(0, Math.floor(usableFragments))
  return Math.min(THEME_PROPOSAL_MAX, Math.floor(n / 2))
}

/** Thema met minder dan 2 bronfragmenten → label "licht onderbouwd". */
export function isLichtOnderbouwdTheme(usableFragments: number): boolean {
  return Math.max(0, Math.floor(usableFragments)) < 2
}

export function lichtOnderbouwdLabel(usableFragments: number): string | null {
  const n = Math.max(0, Math.floor(usableFragments))
  if (n >= 2) return null
  if (n === 1) {
    return 'licht onderbouwd — gebaseerd op 1 bron, nog niet elders bevestigd'
  }
  return 'licht onderbouwd thema'
}

export const THEME_THIN_WARNING =
  'Dit levert waarschijnlijk een dun thema op — eerst bronnen toevoegen bij Bronnen?'

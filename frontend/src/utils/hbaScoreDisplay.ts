export type HbaScoreDisplayMode = 'decimal' | 'band'

/** briefing/11-scoreweergave-implementatie.md — zwaarste eerst */
export const HBA_FLAG_SEVERITY: readonly string[] = [
  'constraint_breach',
  'foe_vervaging',
  'archetype_drift',
  'category_fallback',
  'proof_gap',
  'opportunistische_trendcontent',
]

/** flag_key → zin in de tweede persoon. Alleen presentatie; scoringprompt blijft onaangetast. */
export const HBA_FLAG_SENTENCES: Record<string, string> = {
  category_fallback: 'Dit kan iedere concurrent zo zeggen.',
  archetype_drift: 'Je stem wijkt hier af van hoe jij je merk neerzet.',
  proof_gap: 'Je claimt iets dat dit stuk niet hard maakt.',
  constraint_breach: 'Je overschrijdt hier een grens die je zelf hebt gezet.',
  opportunistische_trendcontent: 'Je haakt op een trend zonder eigen positie.',
  foe_vervaging: 'Hier redeneer je zoals je tegenstander.',
}

export type HbaFindingCauseLink = {
  href: string
  label: string
}

export type HbaFinding = {
  text: string
  cause?: HbaFindingCauseLink
}

export function hbaScoreBandLabel(value: number | null | undefined): string {
  if (value == null || Number.isNaN(value)) return '—'
  if (value <= 4) return 'Laag'
  if (value < 7) return 'Middel'
  return 'Hoog'
}

export function formatHbaScoreValue(
  value: number | null | undefined,
  mode: HbaScoreDisplayMode = 'band',
): string {
  if (value == null || Number.isNaN(value)) return '—'
  if (mode === 'decimal') return value.toFixed(1)
  return hbaScoreBandLabel(value)
}

export function hbaScoreValueHint(mode: HbaScoreDisplayMode): string | undefined {
  if (mode === 'decimal') return undefined
  return 'Drie niveaus — determinismemeting nog niet geslaagd'
}

/**
 * Presentatielaag: integrity = 10 − risk. Ruwe risicowaarde blijft in de database.
 * Zie briefing/11-scoreweergave-implementatie.md §2.
 */
export function architectureIntegrityFromRisk(
  risk: number | null | undefined,
  integrity?: number | null,
): number | null {
  if (integrity != null && !Number.isNaN(integrity)) return integrity
  if (risk == null || Number.isNaN(risk)) return null
  return Math.max(0, Math.min(10, 10 - risk))
}

export function hbaFlagSentence(flag: string): string {
  return HBA_FLAG_SENTENCES[flag] ?? flag.replace(/_/g, ' ')
}

/** Klantlabel — intern blijft de waarde (tegenkracht → Positiebreuk). Spiegel van ce_hba_hoofdoordeel_klantlabel. */
export const HBA_HOOFDOORDEEL_KLANTLABEL: Record<string, string> = {
  regelbreuk: 'Regelbreuk',
  tegenkracht: 'Positiebreuk',
  categorietaal: 'Categorietaal',
  op_merk: 'Op merk',
  neutraal: 'Neutraal',
}

/** Klantweergave — één zin per hoofdoordeel. Spiegel van ce_hba_hoofdoordeel_klantzin. */
export const HBA_HOOFDOORDEEL_KLANTZIN: Record<string, string> = {
  regelbreuk: 'Je overschrijdt hier een grens die je zelf hebt gezet.',
  tegenkracht: 'Hier redeneer je zoals je tegenstander.',
  categorietaal: 'Dit kan iedere concurrent zo zeggen.',
  op_merk: 'Dit is herkenbaar jij.',
  neutraal: 'Prima voor dit doel; het bouwt niet aan je merk, en dat hoeft ook niet.',
}

export function hbaHoofdoordeelKlantlabel(hoofdoordeel: string | null | undefined): string {
  const key = (hoofdoordeel ?? '').trim()
  if (!key) return ''
  return HBA_HOOFDOORDEEL_KLANTLABEL[key] ?? ''
}

export function hbaHoofdoordeelKlantzin(hoofdoordeel: string | null | undefined): string {
  const key = (hoofdoordeel ?? '').trim()
  if (!key) return ''
  return HBA_HOOFDOORDEEL_KLANTZIN[key] ?? ''
}

export function rankHbaFlags(flags: string[] | null | undefined): string[] {
  const unique = [...new Set((flags ?? []).map((flag) => flag.trim()).filter(Boolean))]
  return unique.sort((a, b) => {
    const ia = HBA_FLAG_SEVERITY.indexOf(a)
    const ib = HBA_FLAG_SEVERITY.indexOf(b)
    return (ia === -1 ? 99 : ia) - (ib === -1 ? 99 : ib)
  })
}

/** Pilot A1: category_fallback blijft in data, maar niet als categorietaal-waarschuwing bij neutraal. */
export const HBA_NEUTRAAL_MERK_ADVIES =
  'Deze post zegt niets over je merk. Dat is prima voor een tip of aankondiging.'

export function hbaArchitectureFlagsForDisplay(
  hoofdoordeel: string | null | undefined,
  flags: string[] | null | undefined,
): string[] {
  const ranked = rankHbaFlags(flags)
  if ((hoofdoordeel ?? '').trim() !== 'neutraal') {
    return ranked
  }
  return ranked.filter((flag) => flag !== 'category_fallback')
}

export function hbaNeutralMerkAdvies(
  hoofdoordeel: string | null | undefined,
  flags: string[] | null | undefined,
): string | null {
  if ((hoofdoordeel ?? '').trim() !== 'neutraal') {
    return null
  }
  const hasFallback = (flags ?? []).some((flag) => flag.trim() === 'category_fallback')
  return hasFallback ? HBA_NEUTRAAL_MERK_ADVIES : null
}

export function hbaFlagCauseLink(flag: string | undefined): HbaFindingCauseLink | undefined {
  if (flag === 'opportunistische_trendcontent') {
    return { href: '/theme', label: 'Open Thema\'s' }
  }
  if (
    flag === 'category_fallback' ||
    flag === 'constraint_breach' ||
    flag === 'foe_vervaging' ||
    flag === 'archetype_drift'
  ) {
    return { href: '/brandarchitecture', label: 'Open Merkarchitectuur' }
  }
  return undefined
}

function firstFindingSentences(text: string): string {
  const cleaned = text.trim()
  if (cleaned === '') return ''
  const parts = cleaned.split(/(?<=[.!?])\s+/).filter(Boolean)
  if (parts.length === 0) return cleaned
  if (parts[0].length < 40 && parts[1]) return `${parts[0]} ${parts[1]}`
  return parts[0]
}

export function buildHbaFinding(input: {
  hoofdoordeel?: string | null
  diagnose?: string | null
  flags?: string[] | null
  brandContribution?: number | null
  brandConsistency?: number | null
  architectureIntegrity?: number | null
}): HbaFinding {
  const hoofd = (input.hoofdoordeel ?? '').trim()
  if (hoofd === 'neutraal') {
    return { text: hbaHoofdoordeelKlantzin('neutraal') }
  }

  const ranked = hbaArchitectureFlagsForDisplay(hoofd, input.flags)
  const heaviest = ranked[0]
  const bc = input.brandContribution
  const cons = input.brandConsistency
  const integrity = input.architectureIntegrity
  const scoresGood = (bc ?? 0) >= 7 && (cons ?? 0) >= 7 && (integrity ?? 0) >= 7
  const scoresWeak = (bc ?? 10) <= 4 || (cons ?? 10) <= 4 || (integrity ?? 10) <= 4

  if (heaviest && scoresWeak) {
    return { text: hbaFlagSentence(heaviest), cause: hbaFlagCauseLink(heaviest) }
  }

  const diagnose = firstFindingSentences(input.diagnose ?? '')
  if (diagnose !== '') {
    return { text: diagnose, cause: hbaFlagCauseLink(heaviest) }
  }

  if (heaviest) {
    return { text: hbaFlagSentence(heaviest), cause: hbaFlagCauseLink(heaviest) }
  }

  if (scoresGood) {
    return { text: 'Dit stuk draagt bij, blijft consistent en houdt de architectuur staande.' }
  }

  return { text: 'Dit stuk zit in het midden: het past, maar de bijdrage kan scherper.' }
}

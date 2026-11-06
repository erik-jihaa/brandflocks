/** Broninspiratie invalshoek — klantlabels (geen systeemsleutels in UI). */
const INSPIRATION_INVALSHOEK_LABELS: Record<string, string> = {
  tegenkracht: 'Waar je tegen vecht',
  vakgenoot: 'Scherpe stem in de sector',
  aanpalend: 'Uit een andere sector',
  data: 'Cijfers en onderzoek',
  praktijk: 'Uit de praktijk',
  cultuur: 'Cultureel verschijnsel',
  buitenland: 'Stem van buitenland',
}

export function inspirationInvalshoekLabel(key?: string | null): string | null {
  const normalized = (key ?? '').trim()
  if (normalized === '') return null
  return INSPIRATION_INVALSHOEK_LABELS[normalized] ?? null
}

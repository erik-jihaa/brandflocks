/** CE-02 invalshoek — klantlabels (geen systeemsleutels in UI). */
const INVALSHOEK_LABELS: Record<string, string> = {
  tegenkracht: 'Waar je tegen vecht',
  bewijs: 'Bewijs',
  contra_intuitief: 'Tegendraads',
  aanpalend: 'Uit een andere sector',
  praktijk: 'Uit de praktijk',
  tijd: 'Toen en straks',
  systeem: 'Het grotere geheel',
}

export function researchInvalshoekLabel(key?: string | null): string | null {
  const normalized = (key ?? '').trim()
  if (normalized === '') return null
  return INVALSHOEK_LABELS[normalized] ?? null
}

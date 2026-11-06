export type ToneStyleEntry = {
  id: string
  name: string
  description: string
}

export function toneStylesToEntries(record: Record<string, string> | undefined): ToneStyleEntry[] {
  const keys = Object.keys(record ?? {})
  if (keys.length === 0) {
    return [{ id: crypto.randomUUID(), name: '', description: '' }]
  }
  return keys.map((name, index) => ({
    id: `tone-${index}-${name}`,
    name,
    description: record?.[name] ?? '',
  }))
}

export function entriesToToneStyles(entries: ToneStyleEntry[]): Record<string, string> {
  const out: Record<string, string> = {}
  for (const entry of entries) {
    const name = entry.name.trim()
    if (name === '') continue
    out[name] = entry.description.trim()
  }
  return out
}

/** Alleen namen uit schrijfwijzer (Strategy) of expliciete API-lijst — geen vaste fallback. */
const LEGACY_TONE_LABELS: Record<string, string> = {
  'b to c': 'Consumenten',
  'b to b': 'Zakelijk',
  personal: 'Persoonlijk (ik-vorm)',
  internal: 'Intern',
  partners: 'Partners',
}

/** Klanttaal voor tone/doelgroep (A6); onbekende waarden ongewijzigd. */
export function toneDisplayLabel(name: string): string {
  const key = name.trim().toLowerCase()
  return LEGACY_TONE_LABELS[key] ?? name
}

export function isLegacyToneKey(name: string): boolean {
  const key = name.trim().toLowerCase()
  return key in LEGACY_TONE_LABELS
}

export function toneOptionsFromStyles(
  toneStyles: Record<string, string> | undefined,
  explicitOptions?: string[],
): string[] {
  if (explicitOptions?.length) {
    return explicitOptions
  }
  return Object.keys(toneStyles ?? {})
}

export function toneStyleEntriesEqual(a: ToneStyleEntry[], b: ToneStyleEntry[]): boolean {
  if (a.length !== b.length) return false
  return a.every(
    (entry, index) =>
      entry.name === b[index]?.name && entry.description === b[index]?.description,
  )
}

export type EditorStyleEntry = {
  id: string
  name: string
  description: string
}

export function editorStylesToEntries(record: Record<string, string> | undefined): EditorStyleEntry[] {
  const keys = Object.keys(record ?? {})
  if (keys.length === 0) {
    return [{ id: 'editor-new', name: '', description: '' }]
  }
  return keys.map((name, index) => ({
    id: `editor-${index}-${name}`,
    name,
    description: record?.[name] ?? '',
  }))
}

export function entriesToEditorStyles(entries: EditorStyleEntry[]): Record<string, string> {
  const out: Record<string, string> = {}
  for (const entry of entries) {
    const name = entry.name.trim()
    if (name === '') continue
    out[name] = entry.description.trim()
  }
  return out
}

export function editorFirstName(name: string | null | undefined): string {
  const trimmed = (name ?? '').trim()
  if (!trimmed) return ''
  return trimmed.split(/\s+/)[0] ?? trimmed
}

export function editorOptionsFromStyles(
  editorStyles: Record<string, string> | undefined,
  fallbackEditor?: string | null,
  explicitOptions?: string[],
): string[] {
  const labels = explicitOptions?.length
    ? explicitOptions
    : Object.keys(editorStyles ?? {})
  if (fallbackEditor && !labels.includes(fallbackEditor)) {
    return [...labels, fallbackEditor]
  }
  return labels
}

export function editorStyleEntriesEqual(a: EditorStyleEntry[], b: EditorStyleEntry[]): boolean {
  if (a.length !== b.length) return false
  return a.every(
    (entry, index) =>
      entry.name === b[index]?.name && entry.description === b[index]?.description,
  )
}

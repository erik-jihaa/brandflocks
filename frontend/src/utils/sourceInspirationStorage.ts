import type { SourceInspirationSuggestion } from '../types/sources'

const STORAGE_KEY = 'ce-source-inspiration'

export type StoredSourceInspiration = {
  open: boolean
  suggestions: SourceInspirationSuggestion[]
  searchScope: number
}

export function loadSourceInspiration(): StoredSourceInspiration | null {
  try {
    const raw = sessionStorage.getItem(STORAGE_KEY)
    if (!raw) return null
    const parsed = JSON.parse(raw) as StoredSourceInspiration
    if (!parsed || !Array.isArray(parsed.suggestions)) return null
    return {
      open: Boolean(parsed.open),
      suggestions: parsed.suggestions,
      searchScope: typeof parsed.searchScope === 'number' ? parsed.searchScope : 65,
    }
  } catch {
    return null
  }
}

export function saveSourceInspiration(data: StoredSourceInspiration): void {
  try {
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(data))
  } catch {
    // ignore quota / private mode
  }
}

export function clearSourceInspiration(): void {
  try {
    sessionStorage.removeItem(STORAGE_KEY)
  } catch {
    // ignore
  }
}

import { HBA_ARCHETYPES } from '../constants/hba'

/** Cached LLM-toelichting noemt een ander archetype dan de huidige merkarchitectuur. */
export function themeVerdictConflictsWithArchetype(
  toelichting: string | null | undefined,
  positioningArchetype: string | null | undefined,
): boolean {
  const text = (toelichting ?? '').trim()
  const current = (positioningArchetype ?? '').trim()
  if (text === '' || current === '') {
    return false
  }
  for (const archetype of HBA_ARCHETYPES) {
    if (archetype.localeCompare(current, 'nl', { sensitivity: 'accent' }) === 0) {
      continue
    }
    if (text.includes(archetype) || text.includes(`${archetype}-archetype`)) {
      return true
    }
  }
  return false
}

/** A6 — geen HBA-patroon of archetype-copy in klant-UI. */
function themeVerdictKlantUnsafe(text: string): boolean {
  if (/hba-patroon/i.test(text)) {
    return true
  }
  if (/De [A-Za-zÀ-ÿ]+ legt uit/i.test(text)) {
    return true
  }
  return false
}

/** Klanttaal: intern `verwatert` wordt `botst`. */
export function themeVerdictKlantLabel(label: string | null | undefined): string | null {
  const value = (label ?? '').trim().toLowerCase()
  if (value === '') {
    return null
  }
  if (value === 'verwatert' || value === 'botst') {
    return 'botst'
  }
  if (value === 'versterkt' || value === 'neutraal') {
    return value
  }
  return value
}

export function themeVerdictToelichtingForDisplay(
  toelichting: string | null | undefined,
  positioningArchetype: string | null | undefined,
): string | null {
  if (!toelichting?.trim()) {
    return null
  }
  if (themeVerdictKlantUnsafe(toelichting)) {
    return null
  }
  if (themeVerdictConflictsWithArchetype(toelichting, positioningArchetype)) {
    return null
  }
  return toelichting.replace(/\bfasecyclus\b/gi, 'weekritme')
}

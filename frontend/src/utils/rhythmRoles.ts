import type { ContentRhythm } from '../api/contentRhythm'
import type { AgendaRowDto } from '../types/api'

export type RhythmColumn = {
  key: string
  label: string
}

export const DEFAULT_RHYTHM_COLUMNS: RhythmColumn[] = [
  { key: 'autoriteit', label: 'Autoriteit' },
  { key: 'inzicht', label: 'Inzicht' },
  { key: 'product-of-dienst', label: 'Product of dienst' },
]

export function normalizeRhythmRoleKey(role: string | null | undefined): string {
  const key = (role ?? '').trim().toLowerCase()
  if (key === 'product' || key === 'product-of-dienst') return 'product-of-dienst'
  return key
}

/** Klantlabels op Planning/Agenda — zelfde rollen als Overzicht en Rapport. */
export function rhythmRoleKlantLabel(role: string | null | undefined): string {
  const key = normalizeRhythmRoleKey(role)
  if (key === 'autoriteit') return 'Positionering'
  if (key === 'inzicht') return 'Kennis delen'
  if (key === 'product-of-dienst') return 'Bewijs'
  return rhythmRoleLabel(role)
}

export function rhythmRoleLabel(role: string | null | undefined): string {
  const key = normalizeRhythmRoleKey(role)
  if (key === 'autoriteit') return 'Autoriteit'
  if (key === 'inzicht') return 'Inzicht'
  if (key === 'product-of-dienst') return 'Product of dienst'
  if (key === '') return ''
  return role ?? ''
}

/** Subtiele accenten — linkerrand + labelkleur, geen vlakken. */
export function rhythmRoleAccent(role: string | null | undefined, fallbackIndex = 0): {
  borderClass: string
  labelClass: string
} {
  const key = normalizeRhythmRoleKey(role)
  if (key === 'autoriteit') {
    return { borderClass: 'border-l-ce-blue', labelClass: 'text-ce-blue' }
  }
  if (key === 'inzicht') {
    return { borderClass: 'border-l-ce-grey', labelClass: 'text-ce-grey' }
  }
  if (key === 'product-of-dienst') {
    return { borderClass: 'border-l-ce-beige', labelClass: 'text-ce-beige' }
  }
  const fallbacks = [
    { borderClass: 'border-l-ce-blue', labelClass: 'text-ce-blue' },
    { borderClass: 'border-l-ce-grey', labelClass: 'text-ce-grey' },
    { borderClass: 'border-l-ce-beige', labelClass: 'text-ce-beige' },
  ] as const
  return fallbacks[fallbackIndex % fallbacks.length]
}

export function rhythmColumnsFromRhythm(rhythm: ContentRhythm | null | undefined): RhythmColumn[] {
  if (!rhythm?.roles?.length) {
    return DEFAULT_RHYTHM_COLUMNS
  }
  const count = Math.max(1, rhythm.postsPerWeek ?? rhythm.roles.length)
  return rhythm.roles.slice(0, count).map((role) => {
    const key = normalizeRhythmRoleKey(role.role_name)
    return {
      key,
      label: rhythmRoleKlantLabel(key) || role.role_name,
    }
  })
}

/** Vaste kolomvolgorde: één slot per ritmrol per week. */
export function assignRowsToRhythmColumns(
  rows: AgendaRowDto[],
  columns: RhythmColumn[],
): (AgendaRowDto | null)[] {
  const slots: (AgendaRowDto | null)[] = columns.map(() => null)
  const unplaced: AgendaRowDto[] = []

  for (const row of rows) {
    const key = normalizeRhythmRoleKey(row.rhythmRole)
    const colIdx = columns.findIndex((col) => normalizeRhythmRoleKey(col.key) === key)
    if (colIdx >= 0 && slots[colIdx] === null) {
      slots[colIdx] = row
    } else {
      unplaced.push(row)
    }
  }

  for (const row of unplaced) {
    const emptyIdx = slots.findIndex((slot) => slot === null)
    if (emptyIdx >= 0) {
      slots[emptyIdx] = row
    }
  }

  return slots
}

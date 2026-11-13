import type { AgendaGroupDto, AgendaRowDto, CalendarPhase } from '../types/api'
import { formatWeekRange, getIsoWeekParts, isoWeekRange } from './agendaDates'
import { resolveAgendaPhase, resolveAgendaStatus } from './agendaActions'

const DAY_LABELS = ['Zo', 'Ma', 'Di', 'Wo', 'Do', 'Vr', 'Za'] as const
const MONTH_LABELS = [
  'Jan',
  'Feb',
  'Mrt',
  'Apr',
  'Mei',
  'Jun',
  'Jul',
  'Aug',
  'Sept',
  'Okt',
  'Nov',
  'Dec',
] as const

export function toIsoDate(date: Date): string {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}

export function parseIsoDate(iso: string): Date | null {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(iso)) return null
  const date = new Date(`${iso}T12:00:00`)
  return Number.isNaN(date.getTime()) ? null : date
}

export function addDaysIso(iso: string, days: number): string {
  const date = parseIsoDate(iso)
  if (!date) return iso
  date.setDate(date.getDate() + days)
  return toIsoDate(date)
}

/** Maandag van de ISO-week die `iso` bevat. */
export function mondayOfIso(iso: string): string | null {
  const date = parseIsoDate(iso)
  if (!date) return null
  const day = date.getDay()
  const offset = day === 0 ? -6 : 1 - day
  date.setDate(date.getDate() + offset)
  return toIsoDate(date)
}

export function upcomingMondaysFrom(mondayIso: string, weeks: number): string[] {
  const count = Math.max(1, weeks)
  return Array.from({ length: count }, (_, index) => addDaysIso(mondayIso, index * 7))
}

export function emptyWeekGroup(mondayIso: string, rows: AgendaRowDto[] = []): AgendaGroupDto {
  const date = parseIsoDate(mondayIso)
  const { week, year } = getIsoWeekParts(date ?? new Date())
  return {
    week: `Week ${week}`,
    weekNumber: week,
    weekRange: formatWeekRange(week, year),
    cycleWeek: mondayIso,
    rows,
  }
}

export function weekMondayIso(group: AgendaGroupDto): string | null {
  if (group.cycleWeek && /^\d{4}-\d{2}-\d{2}$/.test(group.cycleWeek)) {
    return group.cycleWeek
  }
  if (group.weekNumber != null && group.weekNumber > 0) {
    return toIsoDate(isoWeekRange(group.weekNumber).start)
  }
  return null
}

/** Artboard 21: `Ma 1 Sept` / `Wo 1 Okt` */
export function formatArtboardDayLabel(iso: string): string {
  const date = parseIsoDate(iso)
  if (!date) return iso
  return `${DAY_LABELS[date.getDay()]} ${date.getDate()} ${MONTH_LABELS[date.getMonth()]}`
}

export function daysOfWeek(mondayIso: string): string[] {
  const monday = parseIsoDate(mondayIso)
  if (!monday) return []
  return Array.from({ length: 7 }, (_, index) => {
    const day = new Date(monday)
    day.setDate(monday.getDate() + index)
    return toIsoDate(day)
  })
}

/** Zelfde offsets als PHP `ce_agenda_default_day_offsets` (2 posts = Di + Vr). */
export function defaultDayOffsets(postsPerWeek: number): number[] {
  const n = Math.max(1, postsPerWeek)
  if (n === 1) return [2]
  if (n === 2) return [1, 4]
  if (n === 3) return [1, 3, 4]
  if (n === 4) return [0, 1, 3, 4]
  return Array.from({ length: Math.min(7, n) }, (_, i) => i)
}

export function defaultPublishDate(mondayIso: string, index: number, postsPerWeek: number): string | null {
  const monday = parseIsoDate(mondayIso)
  if (!monday) return null
  const offsets = defaultDayOffsets(postsPerWeek)
  const offset = offsets[index] ?? Math.min(6, index % 7)
  monday.setDate(monday.getDate() + offset)
  return toIsoDate(monday)
}

export function rowPublishDate(
  row: AgendaRowDto,
  mondayIso: string,
  index: number,
  postsPerWeek: number,
): string | null {
  const explicit = row.publishDate?.trim() ?? ''
  if (/^\d{4}-\d{2}-\d{2}$/.test(explicit)) return explicit
  return defaultPublishDate(mondayIso, index, postsPerWeek)
}

export function resolveCalendarPhase(row: AgendaRowDto): CalendarPhase {
  if (row.calendarPhase) return row.calendarPhase
  if (row.publicationStatus === 'mislukt') return 'failed'
  const status = row.status || resolveAgendaStatus(row.articleStatus, row.slotStatus)
  if (row.publicationStatus === 'gepubliceerd' || status === 'published') return 'published'
  if (status === 'approved') return 'publish'
  const phase = resolveAgendaPhase(row)
  if (phase === 'theme' || phase === 'research') return 'research'
  return 'article'
}

export function calendarPhaseLabel(phase: CalendarPhase): string {
  switch (phase) {
    case 'theme':
      return 'Thema'
    case 'research':
      return 'Onderzoek'
    case 'article':
      return 'Artikel'
    case 'publish':
    case 'published':
      return 'Publiceer'
    case 'failed':
      return 'Mislukt'
  }
}

export function calendarPhaseClass(phase: CalendarPhase, isCampaign = false): string {
  if (phase === 'theme') return 'text-ce-beige'
  if (phase === 'failed') return 'text-ce-orange'
  if (isCampaign) return 'text-ce-green-text'
  return 'text-ce-blue'
}

/** 4 pipeline-stippen: voltooid beige, huidig blauw, later 1px beige outline. */
export function pipelineDotStates(phase: CalendarPhase): Array<'done' | 'current' | 'later' | 'failed'> {
  const currentIndex =
    phase === 'theme' ? -1 : phase === 'research' ? 1 : phase === 'article' ? 2 : 3
  return [0, 1, 2, 3].map((index) => {
    if (index < currentIndex) return 'done'
    if (index === currentIndex) return phase === 'failed' ? 'failed' : 'current'
    return 'later'
  })
}

export type AgendaDayCell = {
  date: string
  label: string
  rows: AgendaRowDto[]
}

export function buildWeekDayCells(
  group: AgendaGroupDto,
  allRows: AgendaRowDto[],
  postsPerWeek: number,
): AgendaDayCell[] {
  const monday = weekMondayIso(group)
  if (!monday) return []
  const dates = daysOfWeek(monday)
  const dateSet = new Set(dates)
  const byDate = new Map<string, AgendaRowDto[]>()
  for (const iso of dates) {
    byDate.set(iso, [])
  }

  for (const row of allRows) {
    const explicit = row.publishDate?.trim() ?? ''
    if (/^\d{4}-\d{2}-\d{2}$/.test(explicit) && dateSet.has(explicit)) {
      byDate.get(explicit)?.push(row)
    }
  }

  group.rows.forEach((row, index) => {
    const explicit = row.publishDate?.trim() ?? ''
    if (/^\d{4}-\d{2}-\d{2}$/.test(explicit)) return
    const iso = defaultPublishDate(monday, index, postsPerWeek)
    if (iso && dateSet.has(iso)) {
      byDate.get(iso)?.push(row)
    }
  })

  return dates.map((date) => ({
    date,
    label: formatArtboardDayLabel(date),
    rows: byDate.get(date) ?? [],
  }))
}

export function flattenAgendaRows(groups: AgendaGroupDto[]): AgendaRowDto[] {
  return groups.flatMap((group) => group.rows)
}

export function monthKeyForGroup(group: AgendaGroupDto): string | null {
  if (group.cycleWeek && /^\d{4}-\d{2}-\d{2}$/.test(group.cycleWeek)) {
    const d = new Date(`${group.cycleWeek}T12:00:00`)
    return `${d.getFullYear()}-${d.getMonth()}`
  }
  if (group.weekNumber != null && group.weekNumber > 0) {
    const { start } = isoWeekRange(group.weekNumber)
    return `${start.getFullYear()}-${start.getMonth()}`
  }
  return null
}

export function monthLabelForGroup(group: AgendaGroupDto): string | null {
  if (group.cycleWeek && /^\d{4}-\d{2}-\d{2}$/.test(group.cycleWeek)) {
    const d = new Date(`${group.cycleWeek}T12:00:00`)
    return d.toLocaleDateString('nl-NL', { month: 'long', year: 'numeric' })
  }
  if (group.weekNumber != null && group.weekNumber > 0) {
    const { start } = isoWeekRange(group.weekNumber)
    return start.toLocaleDateString('nl-NL', { month: 'long', year: 'numeric' })
  }
  return null
}

export type AgendaMonthBlock = {
  monthLabel: string | null
  monthKey: string | null
  groups: AgendaGroupDto[]
}

/** Groepeer opeenvolgende weken onder maandkoppen (lijstweergave). */
export function groupAgendaByMonth(groups: AgendaGroupDto[]): AgendaMonthBlock[] {
  const blocks: AgendaMonthBlock[] = []
  let current: AgendaMonthBlock | null = null

  for (const group of groups) {
    const monthKey = monthKeyForGroup(group)
    const monthLabel = monthLabelForGroup(group)
    if (!current || current.monthKey !== monthKey) {
      current = { monthKey, monthLabel, groups: [group] }
      blocks.push(current)
    } else {
      current.groups.push(group)
    }
  }

  return blocks
}

export const CALENDAR_SCROLL_BATCH = 4
export const CALENDAR_MAX_AHEAD = 52

function sortGroupsByMonday(groups: AgendaGroupDto[]): AgendaGroupDto[] {
  return [...groups].sort((a, b) => {
    const ma = weekMondayIso(a) ?? ''
    const mb = weekMondayIso(b) ?? ''
    return ma.localeCompare(mb)
  })
}

/**
 * Lijst: planning-horizon (lege weken toegestaan) + weken mét items daarbuiten.
 * Geen lege tussenweken tot een ver-vooruit gepland item.
 */
export function listAgendaGroups(
  groups: AgendaGroupDto[],
  horizonMondays: string[],
): AgendaGroupDto[] {
  const horizonSet = new Set(horizonMondays)
  const lastHorizon = horizonMondays[horizonMondays.length - 1] ?? ''
  const farByMonday = new Map<string, AgendaRowDto[]>()
  const farSlotIds = new Set<string>()

  for (const row of flattenAgendaRows(groups)) {
    const publishDate = row.publishDate?.trim() ?? ''
    if (!/^\d{4}-\d{2}-\d{2}$/.test(publishDate)) continue
    const monday = mondayOfIso(publishDate)
    if (!monday || !lastHorizon || monday <= lastHorizon) continue
    const bucket = farByMonday.get(monday) ?? []
    bucket.push(row)
    farByMonday.set(monday, bucket)
    farSlotIds.add(row.slotId)
  }

  const result: AgendaGroupDto[] = []
  for (const group of groups) {
    const monday = weekMondayIso(group)
    const rows = group.rows.filter((row) => !farSlotIds.has(row.slotId))
    const inHorizon = monday != null && horizonSet.has(monday)
    if (!inHorizon && rows.length === 0) continue
    result.push({ ...group, rows })
  }

  for (const [monday, rows] of [...farByMonday.entries()].sort(([a], [b]) => a.localeCompare(b))) {
    const existing = result.find((group) => weekMondayIso(group) === monday)
    if (existing) {
      existing.rows = [...existing.rows, ...rows]
    } else {
      result.push(emptyWeekGroup(monday, rows))
    }
  }

  return sortGroupsByMonday(result)
}

/**
 * Kalender: verleden weken mét content (geen lege tussenweken), daarna
 * aaneengesloten weken vanaf de huidige maandag (horizon + extra bij scroll).
 */
export function calendarAgendaGroups(
  groups: AgendaGroupDto[],
  currentMonday: string,
  horizonWeeks: number,
  extraWeeks: number,
): AgendaGroupDto[] {
  const byMonday = new Map<string, AgendaGroupDto>()
  for (const group of groups) {
    const monday = weekMondayIso(group)
    if (monday) byMonday.set(monday, group)
  }

  const past = sortGroupsByMonday(
    groups.filter((group) => {
      const monday = weekMondayIso(group)
      return monday != null && monday < currentMonday && group.rows.length > 0
    }),
  )

  const count = Math.max(1, horizonWeeks) + Math.max(0, extraWeeks)
  const forward: AgendaGroupDto[] = []
  for (let index = 0; index < count; index++) {
    const monday = addDaysIso(currentMonday, index * 7)
    forward.push(byMonday.get(monday) ?? emptyWeekGroup(monday))
  }

  return [...past, ...forward]
}

import {
  ceDisplayTimezone,
  formatDayMonthYearLong,
  formatWeekRangeCompact,
} from './localeDates'

function ceTimezone(): string {
  return ceDisplayTimezone()
}

export function getIsoWeekParts(date = new Date()): { week: number; year: number } {
  const utc = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()))
  const day = utc.getUTCDay() || 7
  utc.setUTCDate(utc.getUTCDate() + 4 - day)
  const yearStart = new Date(Date.UTC(utc.getUTCFullYear(), 0, 1))
  const week = Math.ceil(((utc.getTime() - yearStart.getTime()) / 86_400_000 + 1) / 7)

  return { week, year: utc.getUTCFullYear() }
}

export function resolveIsoWeekYear(weekNum: number, ref = new Date()): number {
  const { week: currentWeek, year: currentYear } = getIsoWeekParts(ref)

  if (weekNum > currentWeek + 26) return currentYear - 1
  if (weekNum < currentWeek - 26) return currentYear + 1
  return currentYear
}

export function isoWeekRange(weekNum: number, isoYear?: number): { start: Date; end: Date } {
  const year = isoYear ?? resolveIsoWeekYear(weekNum)
  const jan4 = new Date(year, 0, 4)
  const jan4Day = jan4.getDay() || 7
  const mondayWeek1 = new Date(jan4)
  mondayWeek1.setDate(jan4.getDate() - jan4Day + 1)
  const start = new Date(mondayWeek1)
  start.setDate(mondayWeek1.getDate() + (weekNum - 1) * 7)
  const end = new Date(start)
  end.setDate(start.getDate() + 6)

  return { start, end }
}

export function formatWeekRange(weekNum: number, isoYear?: number): string {
  const { start, end } = isoWeekRange(weekNum, isoYear)
  return formatWeekRangeCompact(start, end, ceTimezone())
}

export function formatTodayAgendaLabel(date = new Date()): string {
  return formatDayMonthYearLong(date, ceTimezone())
}

export function parseWeekNumber(weekLabel: string): number | null {
  const match = weekLabel.match(/Week\s+(\d+)/i)
  if (!match) return null
  const n = parseInt(match[1], 10)
  return Number.isNaN(n) ? null : n
}

export function getAgendaDateMeta(date = new Date()) {
  const { week: currentWeek } = getIsoWeekParts(date)

  return {
    currentWeek,
    todayLabel: formatTodayAgendaLabel(date),
  }
}

/** Kalenderdag in Europe/Amsterdam (`YYYY-MM-DD`). */
export function todayIso(date = new Date()): string {
  return date.toLocaleDateString('en-CA', { timeZone: ceTimezone() })
}

export type AgendaDayTone = 'past' | 'today' | 'future'

export function agendaDayTone(iso: string, today: string): AgendaDayTone {
  if (iso < today) return 'past'
  if (iso === today) return 'today'
  return 'future'
}

export function isPastAgendaWeek(weekNumber: number | null | undefined, currentWeek: number): boolean {
  if (weekNumber == null || weekNumber <= 0) return false
  if (weekNumber < currentWeek) return true
  // ISO-week jaarovergang: huidige week 2, vorig jaar week 52 hoort verleden te zijn
  if (weekNumber > currentWeek + 26) return true
  return false
}

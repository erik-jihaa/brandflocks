/** Nederlandse datums voor klant-UI (onafhankelijk van browser-locale quirks). */

const NL_MONTHS_LONG = [
  'januari',
  'februari',
  'maart',
  'april',
  'mei',
  'juni',
  'juli',
  'augustus',
  'september',
  'oktober',
  'november',
  'december',
] as const

const NL_MONTHS_SHORT = [
  'jan',
  'feb',
  'mrt',
  'apr',
  'mei',
  'jun',
  'jul',
  'aug',
  'sep',
  'okt',
  'nov',
  'dec',
] as const

export function ceDisplayTimezone(): string {
  return 'Europe/Amsterdam'
}

/** Kalender-onderdelen in Amsterdam-tijdzone. */
export function datePartsInTimezone(
  date: Date,
  timeZone = ceDisplayTimezone(),
): { day: number; month: number; year: number } {
  const fmt = new Intl.DateTimeFormat('nl-NL', {
    day: 'numeric',
    month: 'numeric',
    year: 'numeric',
    timeZone,
  })
  const parts = fmt.formatToParts(date)
  const pick = (type: Intl.DateTimeFormatPartTypes) =>
    Number(parts.find((p) => p.type === type)?.value ?? '0')
  return { day: pick('day'), month: pick('month'), year: pick('year') }
}

export function formatDayMonthLong(date: Date, timeZone = ceDisplayTimezone()): string {
  const { day, month } = datePartsInTimezone(date, timeZone)
  const name = NL_MONTHS_LONG[month - 1]
  return name ? `${day} ${name}` : `${day}`
}

export function formatDayMonthYearLong(date: Date, timeZone = ceDisplayTimezone()): string {
  const { day, month, year } = datePartsInTimezone(date, timeZone)
  const name = NL_MONTHS_LONG[month - 1]
  return name ? `${day} ${name} ${year}` : `${day} ${year}`
}

export function formatDayMonthShort(date: Date, timeZone = ceDisplayTimezone()): string {
  const { day, month } = datePartsInTimezone(date, timeZone)
  const name = NL_MONTHS_SHORT[month - 1]
  return name ? `${day} ${name}` : `${day}`
}

/** Weekrange: `5 - 11 oktober` of `26 oktober - 1 november`. */
export function formatWeekRangeCompact(start: Date, end: Date, timeZone = ceDisplayTimezone()): string {
  const a = datePartsInTimezone(start, timeZone)
  const b = datePartsInTimezone(end, timeZone)
  if (a.month === b.month && a.year === b.year) {
    const month = NL_MONTHS_LONG[a.month - 1] ?? ''
    return `${a.day} - ${b.day} ${month}`.trim()
  }
  return `${formatDayMonthLong(start, timeZone)} - ${formatDayMonthLong(end, timeZone)}`
}

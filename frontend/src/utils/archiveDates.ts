import { formatDayMonthYearLong } from './localeDates'

/** Archive search + detail — nl-NL, bijv. 6 jun 2022 */
export function formatArchiveSearchDate(value: string | null | undefined): string {
  if (!value) return ''
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''
  return formatDayMonthYearLong(date)
}

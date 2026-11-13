import type { AgendaGroupDto, AgendaRowDto } from '../types/api'
import { formatDayMonthShort } from './localeDates'
import {
  daysOfWeek,
  parseIsoDate,
  weekMondayIso,
} from './agendaCalendarGrid'

export type AgendaGroupMode = 'campaign' | 'date'

export const AGENDA_CHANNEL_ALL = 'all'
export const AGENDA_MONTH_ALL = 'all'

const FIXED_CHANNEL_KEYS = ['linkedin', 'instagram'] as const

export function agendaPlatformKey(platform: string | null | undefined): string {
  return (platform ?? '').trim().toLowerCase()
}

export function agendaMonthKeyFromIso(iso: string | null | undefined): string | null {
  const value = (iso ?? '').trim()
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return null
  return value.slice(0, 7)
}

export function agendaMonthLabel(monthKey: string): string {
  const date = parseIsoDate(`${monthKey}-15`)
  if (!date) return monthKey
  return date.toLocaleDateString('nl-NL', { month: 'long', year: 'numeric' })
}

export function agendaRowMatchesChannel(row: AgendaRowDto, channel: string): boolean {
  if (channel === AGENDA_CHANNEL_ALL || channel === '') return true
  return agendaPlatformKey(row.platform) === channel
}

export function agendaRowMatchesMonth(
  row: AgendaRowDto,
  monthKey: string,
  weekMonday?: string | null,
): boolean {
  if (monthKey === AGENDA_MONTH_ALL || monthKey === '') return true
  const fromPublish = agendaMonthKeyFromIso(row.publishDate)
  if (fromPublish) return fromPublish === monthKey
  if (weekMonday) {
    return daysOfWeek(weekMonday).some((iso) => agendaMonthKeyFromIso(iso) === monthKey)
  }
  return false
}

export function weekOverlapsMonth(mondayIso: string, monthKey: string): boolean {
  if (monthKey === AGENDA_MONTH_ALL || monthKey === '') return true
  return daysOfWeek(mondayIso).some((iso) => agendaMonthKeyFromIso(iso) === monthKey)
}

export function filterAgendaRows(
  rows: AgendaRowDto[],
  channel: string,
  monthKey: string,
  weekMonday?: string | null,
): AgendaRowDto[] {
  return rows.filter(
    (row) =>
      agendaRowMatchesChannel(row, channel) && agendaRowMatchesMonth(row, monthKey, weekMonday),
  )
}

/** Op campagne: alleen campagneposts. Losse artikelen horen bij Op datum. */
export function rowsForAgendaGroupMode(rows: AgendaRowDto[], groupMode: AgendaGroupMode): AgendaRowDto[] {
  if (groupMode !== 'campaign') return rows
  return rows.filter((row) => (row.campaignId ?? '').trim() !== '')
}

export function filterAgendaWeekGroups(
  groups: AgendaGroupDto[],
  channel: string,
  monthKey: string,
  options: { keepEmptyWeeks?: boolean } = {},
): AgendaGroupDto[] {
  const keepEmpty = options.keepEmptyWeeks === true
  const next: AgendaGroupDto[] = []
  for (const group of groups) {
    const monday = weekMondayIso(group)
    if (monday && !weekOverlapsMonth(monday, monthKey)) continue
    const rows = filterAgendaRows(group.rows, channel, monthKey, monday)
    if (rows.length === 0 && !keepEmpty) continue
    next.push({ ...group, rows })
  }
  return next
}

export type AgendaChannelOption = { key: string; label: string }

/** §9 — vaste set in periode-stappen (geen dynamische kanalen). */
export function periodChannelOptions(): AgendaChannelOption[] {
  return [
    { key: AGENDA_CHANNEL_ALL, label: 'Alle kanalen' },
    { key: 'linkedin', label: 'LinkedIn' },
    { key: 'instagram', label: 'Instagram' },
  ]
}

export function matchesChannelKey(
  channel: string,
  platform: string | null | undefined,
  platformKey?: string | null,
): boolean {
  if (channel === AGENDA_CHANNEL_ALL || channel === '') return true
  const key = agendaPlatformKey(platformKey ?? platform)
  return key === channel
}

function rowPublishSortKey(row: AgendaRowDto): string {
  return `${row.publishDate ?? '9999-12-31'} ${row.publishTime ?? ''} ${row.slotId}`
}

export function agendaRowsInIsoRange(
  rows: AgendaRowDto[],
  startIso: string,
  endIso: string,
): AgendaRowDto[] {
  const start = startIso.trim()
  const end = endIso.trim()
  if (!/^\d{4}-\d{2}-\d{2}$/.test(start) || !/^\d{4}-\d{2}-\d{2}$/.test(end)) return []
  const lo = start <= end ? start : end
  const hi = start <= end ? end : start
  return rows
    .filter((row) => {
      const d = (row.publishDate ?? '').trim()
      return /^\d{4}-\d{2}-\d{2}$/.test(d) && d >= lo && d <= hi
    })
    .sort((a, b) => rowPublishSortKey(a).localeCompare(rowPublishSortKey(b)))
}

export function agendaChannelOptions(rows: AgendaRowDto[]): AgendaChannelOption[] {
  const seen = new Set<string>()
  const extras: AgendaChannelOption[] = []
  for (const row of rows) {
    const key = agendaPlatformKey(row.platform)
    if (key === '' || seen.has(key)) continue
    seen.add(key)
    extras.push({ key, label: (row.platform ?? key).trim() })
  }
  const fixed: AgendaChannelOption[] = FIXED_CHANNEL_KEYS.map((key) => ({
    key,
    label: key === 'linkedin' ? 'LinkedIn' : 'Instagram',
  }))
  const extraOnly = extras.filter((item) => !FIXED_CHANNEL_KEYS.includes(item.key as (typeof FIXED_CHANNEL_KEYS)[number]))
  extraOnly.sort((a, b) => a.label.localeCompare(b.label, 'nl'))
  return [{ key: AGENDA_CHANNEL_ALL, label: 'Alle kanalen' }, ...fixed, ...extraOnly]
}

export type AgendaMonthOption = { key: string; label: string }

export function agendaMonthOptions(groups: AgendaGroupDto[]): AgendaMonthOption[] {
  const keys = new Set<string>()
  for (const group of groups) {
    const monday = weekMondayIso(group)
    if (monday) {
      for (const iso of daysOfWeek(monday)) {
        const key = agendaMonthKeyFromIso(iso)
        if (key) keys.add(key)
      }
    }
    for (const row of group.rows) {
      const key = agendaMonthKeyFromIso(row.publishDate)
      if (key) keys.add(key)
    }
  }
  return [
    { key: AGENDA_MONTH_ALL, label: 'Alle maanden' },
    ...[...keys].sort().map((key) => ({ key, label: agendaMonthLabel(key) })),
  ]
}

export type AgendaCampaignListGroup = {
  key: string
  campaignId: string | null
  title: string
  kind: 'campaign' | 'theme'
  rows: AgendaRowDto[]
  rangeLabel: string
  colorIndex: number
}

function rowSortTime(row: AgendaRowDto): string {
  return `${row.publishDate ?? '9999-12-31'} ${row.publishTime ?? ''} ${row.slotId}`
}

function campaignRangeLabel(rows: AgendaRowDto[]): string {
  const dates = rows
    .map((row) => row.publishDate?.trim() ?? '')
    .filter((iso) => /^\d{4}-\d{2}-\d{2}$/.test(iso))
    .sort()
  if (dates.length === 0) return `${rows.length} ${rows.length === 1 ? 'post' : 'posts'}`
  const start = parseIsoDate(dates[0] ?? '')
  const end = parseIsoDate(dates[dates.length - 1] ?? '')
  if (!start || !end) return `${rows.length} posts`
  const startLabel = formatDayMonthShort(start)
  const endLabel = formatDayMonthShort(end)
  const range = startLabel === endLabel ? startLabel : `${startLabel} – ${endLabel}`
  return `${range} · ${rows.length} ${rows.length === 1 ? 'post' : 'posts'}`
}

export function groupAgendaRowsByCampaign(rows: AgendaRowDto[]): AgendaCampaignListGroup[] {
  const buckets = new Map<string, AgendaRowDto[]>()
  const order: string[] = []
  for (const row of rows) {
    const campaignId = (row.campaignId ?? '').trim()
    const key = campaignId !== '' ? `c:${campaignId}` : 'theme'
    if (!buckets.has(key)) {
      buckets.set(key, [])
      order.push(key)
    }
    buckets.get(key)?.push(row)
  }

  const campaigns: AgendaCampaignListGroup[] = []
  let colorIndex = 0
  let theme: AgendaCampaignListGroup | null = null

  for (const key of order) {
    const grouped = [...(buckets.get(key) ?? [])].sort((a, b) => rowSortTime(a).localeCompare(rowSortTime(b)))
    if (key === 'theme') {
      theme = {
        key,
        campaignId: null,
        title: 'Losse artikelen',
        kind: 'theme',
        rows: grouped,
        rangeLabel: campaignRangeLabel(grouped),
        colorIndex: -1,
      }
      continue
    }
    const title = (grouped[0]?.campaignTitle ?? '').trim() || 'Campagne'
    campaigns.push({
      key,
      campaignId: grouped[0]?.campaignId ?? null,
      title,
      kind: 'campaign',
      rows: grouped,
      rangeLabel: campaignRangeLabel(grouped),
      colorIndex: colorIndex++,
    })
  }

  campaigns.sort((a, b) => {
    const aDate = a.rows[0]?.publishDate ?? ''
    const bDate = b.rows[0]?.publishDate ?? ''
    return aDate.localeCompare(bDate) || a.title.localeCompare(b.title, 'nl')
  })

  return theme ? [...campaigns, theme] : campaigns
}

export function campaignColorDotClass(colorIndex: number): string {
  if (colorIndex < 0) return 'bg-ce-beige'
  const palette = ['bg-ce-green', 'bg-ce-orange', 'bg-ce-blue'] as const
  return palette[colorIndex % palette.length] ?? 'bg-ce-green'
}


import type { CampagnePreviewCampaign } from '../api/campagnePreview'
import type { AgendaAction } from './agendaActions'
import { formatWeekRange, getIsoWeekParts } from './agendaDates'

export type CampagneWeekGroup = {
  key: string
  weekLabel: string
  weekNumber: number | null
  weekRange: string | null
  campaigns: CampagnePreviewCampaign[]
}

export function groupCampagnesByStartWeek(campaigns: CampagnePreviewCampaign[]): CampagneWeekGroup[] {
  const groups = new Map<string, CampagneWeekGroup>()

  for (const campaign of campaigns) {
    const start = campaign.startDate?.trim()
    let key = 'zonder-datum'
    let weekLabel = 'Zonder startdatum'
    let weekNumber: number | null = null
    let weekRange: string | null = null

    if (start) {
      const d = new Date(`${start}T12:00:00`)
      if (!Number.isNaN(d.getTime())) {
        const { week, year } = getIsoWeekParts(d)
        key = `${year}-w${String(week).padStart(2, '0')}`
        weekLabel = `Week ${week}`
        weekNumber = week
        weekRange = formatWeekRange(week, year)
      }
    }

    const bucket = groups.get(key)
    if (bucket) {
      bucket.campaigns.push(campaign)
    } else {
      groups.set(key, { key, weekLabel, weekNumber, weekRange, campaigns: [campaign] })
    }
  }

  return [...groups.values()].sort((a, b) => {
    if (a.key === 'zonder-datum') return 1
    if (b.key === 'zonder-datum') return -1
    return b.key.localeCompare(a.key)
  })
}

const TEMPLATE_PHASE_LABEL: Record<string, string> = {
  lancering: 'Sell',
  serie: 'Tell',
  touch: 'Touch',
  tell: 'Tell',
}

export function campagnePreviewStatusLabel(status: string): string {
  if (status === 'bevestigd') return 'Bevestigd'
  if (status === 'loopt') return 'Loopt'
  if (status === 'afgerond') return 'Afgerond'
  return 'Concept'
}

export function campagnePhaseLabel(campaign: CampagnePreviewCampaign): string {
  const key = (campaign.templateKey ?? 'lancering').toLowerCase()
  return TEMPLATE_PHASE_LABEL[key] ?? 'Sell'
}

export function buildCampagneListActions(onEdit: () => void): AgendaAction[] {
  return [
    { label: 'Bewerken', onClick: onEdit },
    { label: 'Verwijderen', action: 'delete' },
    { label: 'Archiveren', action: 'archive' },
  ]
}

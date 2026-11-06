import type { PostStatus } from '../components/ui/ActionLink'
import type { AgendaRowDto } from '../types/api'

export type AgendaAction = {
  label: string
  status?: PostStatus
  to?: string
  action?: 'delete' | 'archive'
  onClick?: () => void
  /** Geen hover/rollover (pipeline-fase themed) */
  noHover?: boolean
}

function nonemptyParts(values: Array<string | null | undefined>): string[] {
  return values.map((value) => (value ?? '').trim()).filter((value) => value !== '')
}

/** Unieke campagnenamen in een week — voor de periode-regel in agenda. */
export function agendaCampaignTitles(rows: AgendaRowDto[]): string[] {
  const titles: string[] = []
  const seen = new Set<string>()
  for (const row of rows) {
    const title = (row.campaignTitle ?? '').trim()
    if (title === '' || seen.has(title)) continue
    seen.add(title)
    titles.push(title)
  }
  return titles
}

export function agendaRowDisplayTitle(row: AgendaRowDto): string {
  if (row.campaignId) {
    const parts = nonemptyParts([row.campaignTitle, row.campaignFaseLabel, row.platform])
    if (parts.length > 0) return parts.join(' — ')
  }
  return row.title
}

/** Kalendercel: fase en kanaal; campagnenaam staat erboven. */
export function agendaCampaignCellTitle(row: AgendaRowDto): string {
  if (!row.campaignId) return agendaRowDisplayTitle(row)
  const parts = nonemptyParts([row.campaignFaseLabel, row.platform])
  return parts.length > 0 ? parts.join(' · ') : agendaRowDisplayTitle(row)
}

const STATUS_PRIORITY: Record<string, number> = {
  published: 7,
  approved: 6,
  feedback: 5,
  pending: 4,
  written: 3,
  draft: 2,
  research_done: 1,
  themed: 0,
}

/** Kies de meest ver gevorderde status (article vs slot kunnen tijdelijk divergeren). */
export function resolveAgendaStatus(
  articleStatus: string | null | undefined,
  slotStatus: string | null | undefined,
  resolvedStatus?: string | null | undefined,
): string {
  if (resolvedStatus) return resolvedStatus

  const candidates = [articleStatus, slotStatus].filter(Boolean) as string[]
  if (candidates.length === 0) return ''

  return candidates.reduce((best, current) =>
    (STATUS_PRIORITY[current] ?? 0) > (STATUS_PRIORITY[best] ?? 0) ? current : best,
  )
}

export function resolveAgendaPhase(row: AgendaRowDto): 'theme' | 'research' | 'article' {
  if (row.phase === 'theme' || row.phase === 'research' || row.phase === 'article') {
    return row.phase
  }

  if (row.articleId) return 'article'

  const slotStatus = row.slotStatus || ''
  if (slotStatus === 'themed') return 'theme'
  if (slotStatus === 'research_done') return 'article'

  return 'article'
}

export function agendaPhaseLabel(
  phase: 'theme' | 'research' | 'article',
  editorFirstName?: string | null,
): string {
  const base = phase === 'theme' ? 'Thema' : phase === 'research' ? 'Onderzoek' : 'Artikel'
  const firstName = (editorFirstName ?? '').trim()
  if (phase === 'article' && firstName !== '') {
    return `${base}-${firstName}`
  }
  return base
}

export type BuildAgendaActionsOptions = {
  /** Verlopen week: alleen Archive/Delete actief blijven klikbaar */
  pastWeek?: boolean
}

function articleEditPath(slotId: string, status: string, articleId?: string | null): string {
  const base = `/article/${slotId}`
  if (status === 'research_done' && !articleId) {
    return `/research/${slotId}`
  }
  if (status === 'research_done') {
    return `${base}?writing=1`
  }

  return base
}

/** Doel-URL bij klik op agenda-item (kalender + lijst). */
export function agendaSlotPath(row: AgendaRowDto): string {
  if (row.campaignId) {
    return `/article/${row.slotId}`
  }
  const status = row.status || resolveAgendaStatus(row.articleStatus, row.slotStatus)
  const slotId = row.slotId

  switch (status) {
    case 'themed':
      return `/research/${slotId}`
    case 'research_done':
      return articleEditPath(slotId, status, row.articleId)
    case 'feedback':
      return `/article/${slotId}/feedback`
    case 'published':
    case 'approved':
    case 'pending':
    case 'written':
    case 'draft':
      return articleEditPath(slotId, status, row.articleId)
    default:
      if (row.articleId) {
        return articleEditPath(slotId, status, row.articleId)
      }
      if ((row.slotStatus ?? '') === 'themed' || row.phase === 'theme') {
        return `/research/${slotId}`
      }
      if (row.phase === 'research') {
        return `/research/${slotId}`
      }
      return articleEditPath(slotId, status, row.articleId)
  }
}

export function buildAgendaActions(
  row: AgendaRowDto,
  _options: BuildAgendaActionsOptions = {},
): AgendaAction[] {
  // API resolved status: ingeleverd → pending, hoofdredacteur-correcties → feedback
  const status = row.status || resolveAgendaStatus(row.articleStatus, row.slotStatus)
  const slotId = row.slotId
  const articlePath = articleEditPath(slotId, status, row.articleId)
  const researchPath = `/research/${slotId}`
  const feedbackPath = `/article/${slotId}/feedback`

  let actions: AgendaAction[]

  switch (status) {
    case 'published':
      actions = [
        { label: 'Gepubliceerd', status: 'published' },
        { label: 'Bekijken', to: articlePath },
        { label: 'Verwijderen', action: 'delete' },
        { label: 'Archiveren', action: 'archive' },
      ]
      break
    case 'research_done':
      actions = [
        { label: 'Bewerken', to: articlePath },
        { label: 'Verwijderen', action: 'delete' },
        { label: 'Archiveren', action: 'archive' },
      ]
      break
    case 'themed':
      actions = [
        { label: 'Bewerken', to: researchPath },
        { label: 'Verwijderen', action: 'delete' },
        { label: 'Archiveren', action: 'archive' },
      ]
      break
    case 'approved':
      actions = [
        { label: 'Bewerken', to: articlePath },
        { label: 'Verwijderen', action: 'delete' },
        { label: 'Goedgekeurd', status: 'approved', to: articlePath },
        { label: 'Archiveren', action: 'archive' },
      ]
      break
    case 'feedback':
      actions = [
        { label: 'Bewerken', to: feedbackPath },
        { label: 'Verwijderen', action: 'delete' },
        { label: 'Terugkoppeling', status: 'feedback', to: feedbackPath },
        { label: 'Archiveren', action: 'archive' },
      ]
      break
    case 'pending':
      actions = [
        { label: 'Bewerken', to: articlePath },
        { label: 'Verwijderen', action: 'delete' },
        { label: 'Ingediend', status: 'pending' },
        { label: 'Archiveren', action: 'archive' },
      ]
      break
    case 'written':
    case 'draft':
      actions = [
        { label: 'Bewerken', to: articlePath },
        { label: 'Verwijderen', action: 'delete' },
        { label: 'Concept', status: 'draft' },
        { label: 'Archiveren', action: 'archive' },
      ]
      break
    default:
      actions = [
        { label: 'Bewerken', to: articlePath },
        { label: 'Verwijderen', action: 'delete' },
        { label: 'Archiveren', action: 'archive' },
      ]
  }

  return actions
}

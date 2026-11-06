const STORAGE_KEY = 'ce-agenda-refresh-at'
export const AGENDA_REFRESH_EVENT = 'ce:agenda-refresh'

export function bumpAgendaRefresh(): void {
  sessionStorage.setItem(STORAGE_KEY, String(Date.now()))
  window.dispatchEvent(new CustomEvent(AGENDA_REFRESH_EVENT))
}

export function getAgendaRefreshAt(): number {
  const raw = sessionStorage.getItem(STORAGE_KEY)
  if (!raw) return 0
  const parsed = Number(raw)
  return Number.isFinite(parsed) ? parsed : 0
}

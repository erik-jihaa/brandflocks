import { useCallback, useEffect, useRef, useState } from 'react'
import { useLocation } from 'react-router-dom'
import { fetchAgenda } from '../api/agenda'
import { ApiError, isApiConfigured } from '../api/client'
import { agendaMock } from '../data/agenda.mock'
import type { AgendaDto } from '../types/api'
import { resolveAgendaStatus } from '../utils/agendaActions'
import { AGENDA_REFRESH_EVENT, getAgendaRefreshAt } from '../utils/agendaRefresh'
import { formatWeekRange, getAgendaDateMeta, isoWeekRange } from '../utils/agendaDates'
import { defaultPublishDate, toIsoDate } from '../utils/agendaCalendarGrid'

const AGENDA_POLL_MS = 15_000

function actionStatus(
  actions: (typeof agendaMock)[number]['rows'][number]['actions'],
  target: string,
): string | null {
  const match = actions.find((a) => 'status' in a && a.status === target)
  return match && 'status' in match ? match.status : null
}

function mockAgenda(): AgendaDto {
  const { currentWeek, todayLabel } = getAgendaDateMeta()
  const weekNumbers = [currentWeek - 1, currentWeek, currentWeek + 1]

  return {
    todayLabel,
    currentWeek,
    planningHorizonWeeks: 4,
    groups: agendaMock.map((group, index) => {
      const weekNumber = weekNumbers[index] ?? currentWeek + index
      return {
        week: `Week ${weekNumber}`,
        weekNumber,
        weekRange: formatWeekRange(weekNumber),
        rows: group.rows.map((row, rowIndex) => {
          const monday = toIsoDate(isoWeekRange(weekNumber).start)
          const published = Boolean(actionStatus(row.actions, 'published'))
          const approved = Boolean(actionStatus(row.actions, 'approved'))
          return {
            slotId: `mock-${weekNumber}-${rowIndex}`,
            articleId: rowIndex === 0 && index === 2 ? 'mock-article' : null,
            title: row.title,
            articleStatus:
              actionStatus(row.actions, 'approved') ??
              actionStatus(row.actions, 'feedback') ??
              actionStatus(row.actions, 'published') ??
              ('researchDone' in row && row.researchDone ? null : 'draft'),
            slotStatus: 'researchDone' in row && row.researchDone ? 'research_done' : null,
            phaseLabel: 'researchDone' in row && row.researchDone ? 'Onderzoek' : 'Artikel',
            phase: 'researchDone' in row && row.researchDone ? 'research' : 'article',
            publishDate: defaultPublishDate(monday, rowIndex, 2),
            campaignId: index === 1 ? 'mock-campaign' : null,
            campaignTitle: index === 1 ? 'Lancering blend' : null,
            campaignFaseLabel: index === 1 ? (rowIndex === 0 ? 'Aankondiging' : 'Bewijs') : null,
            platform: index === 1 ? (rowIndex === 0 ? 'LinkedIn' : 'Instagram') : null,
            calendarPhase: published
              ? 'published'
              : approved
                ? 'publish'
                : 'researchDone' in row && row.researchDone
                  ? 'research'
                  : 'article',
          }
        }),
      }
    }),
  }
}

function agendaHasPendingReview(agenda: AgendaDto): boolean {
  return agenda.groups.some((group) =>
    group.rows.some(
      (row) => resolveAgendaStatus(row.articleStatus, row.slotStatus, row.status) === 'pending',
    ),
  )
}

export function useAgenda() {
  const [agenda, setAgenda] = useState<AgendaDto | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [usingMock, setUsingMock] = useState(false)
  const agendaRef = useRef<AgendaDto | null>(null)
  const lastLoadedAtRef = useRef(0)
  const location = useLocation()

  useEffect(() => {
    agendaRef.current = agenda
  }, [agenda])

  const load = useCallback(async (silent = false) => {
    if (!silent) {
      setLoading(true)
    }
    setError(null)

    if (!isApiConfigured()) {
      setAgenda(mockAgenda())
      setUsingMock(true)
      if (!silent) {
        setLoading(false)
      }
      return
    }

    try {
      const data = await fetchAgenda()
      setAgenda(data)
      setUsingMock(false)
      lastLoadedAtRef.current = Date.now()
    } catch (err) {
      if (err instanceof ApiError && err.status === 503) {
        setError('Database niet geconfigureerd — mock agenda actief.')
      } else if (!silent) {
        setError(err instanceof Error ? err.message : 'Kalender laden mislukt')
      }
      if (!agendaRef.current) {
        setAgenda(mockAgenda())
        setUsingMock(true)
      }
    } finally {
      if (!silent) {
        setLoading(false)
      }
    }
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  useEffect(() => {
    if (location.pathname !== '/agenda') return
    if (getAgendaRefreshAt() > lastLoadedAtRef.current) {
      void load(true)
    }
  }, [location.pathname, load])

  useEffect(() => {
    const refreshIfStale = () => {
      if (getAgendaRefreshAt() <= lastLoadedAtRef.current) return
      void load(true)
    }

    window.addEventListener(AGENDA_REFRESH_EVENT, refreshIfStale)
    window.addEventListener('focus', refreshIfStale)
    return () => {
      window.removeEventListener(AGENDA_REFRESH_EVENT, refreshIfStale)
      window.removeEventListener('focus', refreshIfStale)
    }
  }, [load])

  const shouldPoll = Boolean(agenda && !usingMock && agendaHasPendingReview(agenda))

  useEffect(() => {
    if (!shouldPoll) {
      return
    }

    const tick = () => {
      if (document.visibilityState !== 'visible') return
      void load(true)
    }

    const interval = window.setInterval(tick, AGENDA_POLL_MS)
    return () => window.clearInterval(interval)
  }, [shouldPoll, load])

  return {
    agenda,
    loading,
    error,
    usingMock,
    reload: () => load(false),
  }
}

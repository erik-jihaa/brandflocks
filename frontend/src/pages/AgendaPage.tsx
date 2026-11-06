import { useEffect, useMemo, useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { AgendaCalendar } from '../components/AgendaCalendar'
import { AgendaPortfolioSignals } from '../components/AgendaPortfolioSignals'
import { ActionNotice } from '../components/ui/ActionNotice'
import { useActionNotice } from '../hooks/useActionNotice'
import { GridShell } from '../layout/GridShell'
import { AGENDA_MAIN_WIDTH, LIST_SECTION } from '../layout/grid.constants'
import { statusRowActionClass } from '../components/ui/ActionLink'
import { AgendaRow } from '../components/StatusRow'
import { deleteAgendaSlot, scheduleAgendaSlot } from '../api/agenda'
import { ApiError } from '../api/client'
import { useAgenda } from '../hooks/useAgenda'
import {
  agendaCampaignTitles,
  agendaRowDisplayTitle,
  buildAgendaActions,
  resolveAgendaPhase,
  agendaPhaseLabel,
} from '../utils/agendaActions'
import {
  formatTodayAgendaLabel,
  formatWeekRange,
  getAgendaDateMeta,
  isPastAgendaWeek,
  parseWeekNumber,
  todayIso,
} from '../utils/agendaDates'
import { listAgendaGroups, mondayOfIso, upcomingMondaysFrom } from '../utils/agendaCalendarGrid'
import type { AgendaRowDto } from '../types/api'

type AgendaWeekHeaderProps = {
  week: string
  weekNumber?: number | null
  weekRange?: string | null
  dimmed?: boolean
}

function AgendaWeekHeader({ week, weekNumber, weekRange, dimmed = false }: AgendaWeekHeaderProps) {
  const parsedNumber = weekNumber ?? parseWeekNumber(week)
  const range =
    parsedNumber != null ? formatWeekRange(parsedNumber) : weekRange?.trim() ? weekRange : null

  return (
    <h2 className={`${LIST_SECTION.headerClass} whitespace-normal break-words ${dimmed ? 'text-ce-beige' : 'text-ce-black'}`}>
      {week}
      {range && (
        <span className="text-[14px] leading-[18px] font-normal"> _ {range}</span>
      )}
    </h2>
  )
}

function AgendaPageTitle({ todayLabel }: { todayLabel: string }) {
  return (
    <>
      Agenda
      <span className="text-[24px] leading-[32px] font-normal text-ce-black"> — {todayLabel}</span>
    </>
  )
}

const toggleClass = (active: boolean) =>
  `border-0 bg-transparent p-0 text-[14px] font-semibold leading-[18px] ${
    active
      ? 'text-ce-black underline decoration-[1.5px] underline-offset-[3px]'
      : 'text-ce-black'
  }`

/** Agenda — Artboard 21 + PDF pag. 6 */
export function AgendaPage() {
  const navigate = useNavigate()
  const location = useLocation()
  const { agenda, loading, error, usingMock, reload } = useAgenda()
  const {
    message: actionNotice,
    kind: actionNoticeKind,
    fading: actionNoticeFading,
    showSuccess: showActionSuccess,
  } = useActionNotice()
  const [busySlotId, setBusySlotId] = useState<string | null>(null)
  const [actionError, setActionError] = useState<string | null>(null)
  const [view, setView] = useState<'calendar' | 'list'>('calendar')

  useEffect(() => {
    const state = location.state as {
      researchSubmitted?: boolean
      researchSubmitMessage?: string
    } | null
    if (!state?.researchSubmitted) return
    showActionSuccess(
      state.researchSubmitMessage?.trim() || 'Onderzoek ingediend — agenda bijgewerkt.',
    )
    navigate(location.pathname + location.search, { replace: true, state: null })
  }, [location.pathname, location.search, location.state, navigate, showActionSuccess])

  const dateMeta = getAgendaDateMeta()
  const todayLabel = dateMeta.todayLabel
  const currentWeek = agenda?.currentWeek ?? dateMeta.currentWeek
  const postsPerWeek = agenda?.postsPerWeek ?? 2
  const planningHorizonWeeks = agenda?.planningHorizonWeeks ?? 4
  const currentMonday = mondayOfIso(todayIso()) ?? todayIso()
  const horizonMondays = useMemo(() => {
    if (agenda?.horizonMondays && agenda.horizonMondays.length > 0) {
      return agenda.horizonMondays
    }
    return upcomingMondaysFrom(currentMonday, planningHorizonWeeks)
  }, [agenda?.horizonMondays, currentMonday, planningHorizonWeeks])
  const listGroups = useMemo(
    () => (agenda ? listAgendaGroups(agenda.groups, horizonMondays) : []),
    [agenda, horizonMondays],
  )

  async function handleAction(
    slotId: string,
    title: string,
    action: 'delete' | 'archive',
    articleId?: string | null,
  ) {
    if (action === 'archive') {
      const emptyDraft = {
        slotId,
        title,
        publishedAt: null,
        promoBudget: null,
        socialPostUrl: null,
        archiveNote: null,
      }
      if (!articleId) {
        navigate(`/archive/${slotId}/confirm`, { state: emptyDraft })
      } else {
        navigate(`/archive/${slotId}/post`)
      }
      return
    }

    if (usingMock) {
      setActionError('Demo-agenda — log in om items te verwijderen.')
      return
    }

    setBusySlotId(slotId)
    setActionError(null)

    try {
      await deleteAgendaSlot(slotId)
      await reload()
    } catch (err) {
      const msg =
        err instanceof ApiError && err.status === 401
          ? 'Sessie verlopen — log opnieuw in via Login.'
          : err instanceof Error
            ? err.message
            : 'Verwijderen mislukt'
      setActionError(msg)
      if (err instanceof ApiError && err.status === 401) {
        navigate('/login', { replace: true, state: { from: '/agenda' } })
      }
    } finally {
      setBusySlotId(null)
    }
  }

  function handleCalendarAction(row: AgendaRowDto, action: 'delete' | 'archive') {
    if (busySlotId === row.slotId) return
    void handleAction(row.slotId, row.title, action, row.articleId)
  }

  async function handleSchedule(slotId: string, publishDate: string, publishTime?: string | null, platform?: string | null) {
    if (usingMock) {
      setActionError('Demo-agenda — log in om items te verplaatsen.')
      return
    }
    setBusySlotId(slotId)
    setActionError(null)
    try {
      await scheduleAgendaSlot(slotId, { publishDate, publishTime, platform })
      await reload()
    } catch (err) {
      setActionError(err instanceof Error ? err.message : 'Verplaatsen mislukt')
    } finally {
      setBusySlotId(null)
    }
  }

  if (loading || !agenda) {
    return (
      <GridShell
        pageTitle={<AgendaPageTitle todayLabel={formatTodayAgendaLabel()} />}
        activeNav="agenda"
        mainColumnWidth={AGENDA_MAIN_WIDTH}
        main={<p>Laden…</p>}
      />
    )
  }

  return (
    <GridShell
      pageTitle={<AgendaPageTitle todayLabel={todayLabel} />}
      activeNav="agenda"
      mainColumnWidth={AGENDA_MAIN_WIDTH}
      main={
        <>
          <ActionNotice
            message={actionNotice}
            kind={actionNoticeKind}
            fading={actionNoticeFading}
          />
          {error && (
            <p className="mb-[12px] text-[14px] leading-[18px] text-ce-red">{error}</p>
          )}
          {actionError && (
            <p className="mb-[12px] text-[14px] leading-[18px] text-ce-red">{actionError}</p>
          )}
          {usingMock && (
            <p className="mb-[12px] text-[14px] leading-[18px] text-ce-beige">
              Mock agenda — start PHP API + DB voor live slots.
            </p>
          )}

          <div className="mb-[18px] flex items-baseline gap-[0px]">
            <button
              type="button"
              className={toggleClass(view === 'calendar')}
              onClick={() => setView('calendar')}
            >
              Kalender
            </button>
            <span className="px-[8px] text-[14px] font-semibold leading-[18px] text-ce-black">|</span>
            <button
              type="button"
              className={toggleClass(view === 'list')}
              onClick={() => setView('list')}
            >
              Lijst
            </button>
          </div>

          {view === 'calendar' ? (
            <>
              <AgendaPortfolioSignals signals={agenda.portfolioSignals ?? []} />
              <AgendaCalendar
                groups={agenda.groups}
                postsPerWeek={postsPerWeek}
                horizonWeeks={planningHorizonWeeks}
                today={todayIso()}
                busySlotId={busySlotId}
                onSchedule={handleSchedule}
                onArchive={(row) => handleCalendarAction(row, 'archive')}
                onDelete={(row) => handleCalendarAction(row, 'delete')}
              />
            </>
          ) : null}

          {view === 'list' && listGroups.map((group) => {
            const weekNumber = group.weekNumber ?? parseWeekNumber(group.week)
            const dimmed = isPastAgendaWeek(weekNumber, currentWeek)
            const weekCampaigns = agendaCampaignTitles(group.rows)

            return (
              <section key={group.week} className={LIST_SECTION.sectionClass}>
                <AgendaWeekHeader
                  week={group.week}
                  weekNumber={weekNumber}
                  weekRange={group.weekRange}
                  dimmed={dimmed}
                />
                <div className={LIST_SECTION.bodyClass}>
                  <div className={LIST_SECTION.bodyStackClass}>
                    {weekCampaigns.length > 0 ? (
                      <p className="m-0 text-[14px] leading-[18px] text-ce-beige">
                        {weekCampaigns.join(' · ')}
                      </p>
                    ) : null}
                    {group.rows.length === 0 && group.themeOnly ? (
                      <p className="m-0 text-[14px] leading-[18px] text-ce-beige">
                        Nog geen artikel —{' '}
                        <Link
                          to={
                            group.cycleWeek
                              ? `/theme?week=${encodeURIComponent(group.cycleWeek)}`
                              : '/theme'
                          }
                          className={statusRowActionClass}
                        >
                          kies een thema
                        </Link>
                        .
                      </p>
                    ) : null}
                    {group.rows.map((row) => (
                      <AgendaRow
                        key={row.slotId}
                        title={agendaRowDisplayTitle(row)}
                        phaseLabel={
                          row.phaseLabel ??
                          agendaPhaseLabel(resolveAgendaPhase(row), row.editorFirstName)
                        }
                        actions={buildAgendaActions(row, { pastWeek: dimmed })}
                        dimmed={dimmed}
                        busy={busySlotId === row.slotId}
                        onAction={(nextAction) => {
                          if (busySlotId === row.slotId) return
                          void handleAction(
                            row.slotId,
                            row.title,
                            nextAction,
                            row.articleId,
                          )
                        }}
                      />
                    ))}
                  </div>
                </div>
              </section>
            )
          })}
        </>
      }
    />
  )
}

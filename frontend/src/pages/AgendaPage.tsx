import { useEffect, useMemo, useState } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'
import { AgendaCalendar } from '../components/AgendaCalendar'
import { AgendaCampaignList } from '../components/AgendaCampaignList'
import { AgendaDateList } from '../components/AgendaDateList'
import { AgendaPortfolioSignals } from '../components/AgendaPortfolioSignals'
import { AgendaViewFilters } from '../components/AgendaViewFilters'
import { ActionNotice } from '../components/ui/ActionNotice'
import { useActionNotice } from '../hooks/useActionNotice'
import { GridShell } from '../layout/GridShell'
import { AGENDA_MAIN_WIDTH } from '../layout/grid.constants'
import { deleteAgendaSlot, scheduleAgendaSlot } from '../api/agenda'
import { ApiError } from '../api/client'
import { useAgenda } from '../hooks/useAgenda'
import { formatTodayAgendaLabel, getAgendaDateMeta, todayIso } from '../utils/agendaDates'
import { flattenAgendaRows, listAgendaGroups, mondayOfIso, upcomingMondaysFrom } from '../utils/agendaCalendarGrid'
import {
  AGENDA_CHANNEL_ALL,
  AGENDA_EMPTY_MONTH_MESSAGE,
  AGENDA_MONTH_ALL,
  agendaChannelOptions,
  agendaMonthOptions,
  filterAgendaWeekGroups,
  rowsForAgendaGroupMode,
  type AgendaGroupMode,
} from '../utils/agendaFilters'
import type { AgendaRowDto } from '../types/api'

function AgendaPageTitle({ todayLabel }: { todayLabel: string }) {
  return (
    <>
      Kalender
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
  const [groupMode, setGroupMode] = useState<AgendaGroupMode>('campaign')
  const [channel, setChannel] = useState(AGENDA_CHANNEL_ALL)
  const [monthKey, setMonthKey] = useState(AGENDA_MONTH_ALL)

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
  const filteredListGroups = useMemo(
    () =>
      filterAgendaWeekGroups(listGroups, channel, monthKey, {
        keepEmptyWeeks: channel === AGENDA_CHANNEL_ALL,
      }),
    [listGroups, channel, monthKey],
  )
  const filteredListRows = useMemo(
    () => flattenAgendaRows(filteredListGroups),
    [filteredListGroups],
  )
  const channelOptions = useMemo(
    () => agendaChannelOptions(flattenAgendaRows(listGroups)),
    [listGroups],
  )
  const monthOptions = useMemo(() => agendaMonthOptions(listGroups), [listGroups])

  // Kanaalfilter kan verdwijnen als data/tenant wisselt — val terug op Alle kanalen.
  useEffect(() => {
    if (channel === AGENDA_CHANNEL_ALL) return
    if (channelOptions.some((option) => option.key === channel)) return
    setChannel(AGENDA_CHANNEL_ALL)
  }, [channel, channelOptions])

  const rowsForList = useMemo(
    () => rowsForAgendaGroupMode(filteredListRows, groupMode),
    [filteredListRows, groupMode],
  )
  const listEmptyMessage = useMemo(() => {
    if (rowsForList.length > 0) return null
    return monthKey !== AGENDA_MONTH_ALL ? AGENDA_EMPTY_MONTH_MESSAGE : 'Geen posts voor deze filters.'
  }, [rowsForList.length, monthKey])
  // Overzicht: lege maand op alle posts (niet op Lijst “Op campagne”-subset).
  const calendarEmptyMonth =
    view === 'calendar' && monthKey !== AGENDA_MONTH_ALL && filteredListRows.length === 0

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
              Overzicht
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

          <AgendaViewFilters
            showGroupToggles={view === 'list'}
            groupMode={groupMode}
            onGroupMode={setGroupMode}
            channel={channel}
            onChannel={setChannel}
            channels={channelOptions}
            month={monthKey}
            onMonth={setMonthKey}
            months={monthOptions}
          />

          {calendarEmptyMonth ? (
            <p className="m-0 text-[14px] leading-[18px] text-ce-beige">{AGENDA_EMPTY_MONTH_MESSAGE}</p>
          ) : null}

          {view === 'calendar' && !calendarEmptyMonth ? (
            <>
              <AgendaPortfolioSignals signals={agenda.portfolioSignals ?? []} />
              <AgendaCalendar
                groups={agenda.groups}
                postsPerWeek={postsPerWeek}
                horizonWeeks={planningHorizonWeeks}
                today={todayIso()}
                busySlotId={busySlotId}
                channel={channel}
                monthKey={monthKey}
                onSchedule={handleSchedule}
                onArchive={(row) => handleCalendarAction(row, 'archive')}
                onDelete={(row) => handleCalendarAction(row, 'delete')}
              />
            </>
          ) : null}

          {view === 'list' && listEmptyMessage ? (
            <p className="m-0 text-[14px] leading-[18px] text-ce-beige">{listEmptyMessage}</p>
          ) : null}

          {view === 'list' && groupMode === 'campaign' && !listEmptyMessage ? (
            <AgendaCampaignList
              rows={rowsForList}
              monthKey={monthKey}
              busySlotId={busySlotId}
              onAction={(row, action) => {
                void handleAction(row.slotId, row.title, action, row.articleId)
              }}
            />
          ) : null}

          {view === 'list' && groupMode === 'date' && !listEmptyMessage ? (
            <AgendaDateList
              rows={rowsForList}
              allRowsForColors={filteredListRows}
              busySlotId={busySlotId}
              onAction={(row, action) => {
                void handleAction(row.slotId, row.title, action, row.articleId)
              }}
            />
          ) : null}
        </>
      }
    />
  )
}

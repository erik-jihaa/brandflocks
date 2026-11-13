import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { useAgenda } from '../hooks/useAgenda'
import { flattenAgendaRows, parseIsoDate } from '../utils/agendaCalendarGrid'
import {
  AGENDA_CHANNEL_ALL,
  agendaRowMatchesChannel,
  agendaRowsInIsoRange,
  periodChannelOptions,
} from '../utils/agendaFilters'
import { agendaRowDisplayTitle } from '../utils/agendaActions'
import { formatDayMonthShort } from '../utils/localeDates'
import { ChannelFilterBar } from './ChannelFilterBar'
import { LIST_SECTION } from '../layout/grid.constants'

const hintClass = 'm-0 text-[14px] leading-[18px] text-ce-beige'
const bodyClass = 'm-0 text-[14px] leading-[18px] text-ce-black'

type PeriodAgendaOverviewProps = {
  startDate: string
  endDate: string
  /** Markeer het huidige slot in de lijst (artikel-flow). */
  highlightSlotId?: string | null
  title?: string
  className?: string
}

function formatRowDate(iso: string | null | undefined): string {
  const parsed = parseIsoDate((iso ?? '').trim())
  if (!parsed) return (iso ?? '').trim() || '—'
  return formatDayMonthShort(parsed)
}

/** Geplande posts in een datumvenster — alleen leesbaar, met kanaalfilter (§9). */
export function PeriodAgendaOverview({
  startDate,
  endDate,
  highlightSlotId,
  title = 'Wat er al gepland staat',
  className = '',
}: PeriodAgendaOverviewProps) {
  const { agenda, loading, usingMock } = useAgenda()
  const [channel, setChannel] = useState(AGENDA_CHANNEL_ALL)

  const rows = useMemo(() => {
    const all = flattenAgendaRows(agenda?.groups ?? [])
    const inRange = agendaRowsInIsoRange(all, startDate, endDate)
    return inRange.filter((row) => agendaRowMatchesChannel(row, channel))
  }, [agenda?.groups, startDate, endDate, channel])

  const startOk = /^\d{4}-\d{2}-\d{2}$/.test(startDate.trim())
  const endOk = /^\d{4}-\d{2}-\d{2}$/.test(endDate.trim())
  if (!startOk || !endOk) return null

  return (
    <div className={`flex flex-col gap-[12px] ${className}`.trim()}>
      <div>
        <p className="m-0 text-[14px] font-semibold leading-[18px] text-ce-black">{title}</p>
        <p className={`${hintClass} mt-[6px]`}>
          Alleen overzicht — het filter wijzigt niets in je planning.
          {usingMock ? ' (demodata)' : ''}
        </p>
      </div>
      <ChannelFilterBar channel={channel} onChannel={setChannel} channels={periodChannelOptions()} />
      {loading ? <p className={hintClass}>Agenda laden…</p> : null}
      {!loading && rows.length === 0 ? (
        <p className={hintClass}>Geen andere posts in deze periode{channel !== AGENDA_CHANNEL_ALL ? ' op dit kanaal' : ''}.</p>
      ) : null}
      {!loading && rows.length > 0 ? (
        <ul className={`m-0 list-none p-0 ${LIST_SECTION.bodyStackClass}`}>
          {rows.map((row) => {
            const highlighted = highlightSlotId != null && row.slotId === highlightSlotId
            const label = agendaRowDisplayTitle(row)
            const platform = (row.platform ?? '').trim() || '—'
            return (
              <li key={row.slotId} className={LIST_SECTION.itemClass}>
                <p className={`${bodyClass} ${highlighted ? 'font-semibold' : ''}`}>
                  {formatRowDate(row.publishDate)} · {platform} · {label}
                </p>
                {row.slotId && !highlighted ? (
                  <Link
                    to={`/article/${row.slotId}`}
                    className="mt-[4px] inline-block text-[14px] font-semibold leading-[18px] text-ce-blue no-underline"
                  >
                    Open
                  </Link>
                ) : null}
              </li>
            )
          })}
        </ul>
      ) : null}
    </div>
  )
}

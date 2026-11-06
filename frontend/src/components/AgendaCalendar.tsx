import { useEffect, useMemo, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import type { AgendaGroupDto, AgendaRowDto, CalendarPhase } from '../types/api'
import { agendaCampaignCellTitle, agendaCampaignTitles } from '../utils/agendaActions'
import { DeleteConfirmAction } from './ui/DeleteConfirmAction'
import { ActionLink, statusRowActionClass } from './ui/ActionLink'
import { agendaSlotPath } from '../utils/agendaActions'
import { agendaDayTone, todayIso, type AgendaDayTone } from '../utils/agendaDates'
import {
  buildWeekDayCells,
  calendarAgendaGroups,
  calendarPhaseClass,
  calendarPhaseLabel,
  CALENDAR_MAX_AHEAD,
  CALENDAR_SCROLL_BATCH,
  flattenAgendaRows,
  mondayOfIso,
  pipelineDotStates,
  resolveCalendarPhase,
  weekMondayIso,
} from '../utils/agendaCalendarGrid'

type AgendaCalendarProps = {
  groups: AgendaGroupDto[]
  postsPerWeek?: number
  horizonWeeks?: number
  today?: string
  busySlotId?: string | null
  onSchedule?: (slotId: string, publishDate: string, publishTime?: string | null, platform?: string | null) => void
  onArchive?: (row: AgendaRowDto) => void
  onDelete?: (row: AgendaRowDto) => void
}

function dayLabelClass(tone: AgendaDayTone): string {
  if (tone === 'today') return 'm-0 text-[14px] leading-[18px] font-semibold text-ce-blue'
  const color = tone === 'past' ? 'text-ce-beige' : 'text-ce-black'
  return `m-0 text-[14px] leading-[18px] font-normal ${color}`
}

function PipelineDots({ phase, isCampaign }: { phase: CalendarPhase; isCampaign?: boolean }) {
  const currentFill = isCampaign
    ? 'bg-ce-green-fill border border-ce-green-fill'
    : 'bg-ce-blue border border-ce-blue'
  return (
    <div className="mt-[8px] flex gap-[6px]" aria-hidden>
      {pipelineDotStates(phase).map((state, index) => {
        const fill =
          state === 'done'
            ? 'bg-ce-beige border border-ce-beige'
            : state === 'current'
              ? currentFill
              : state === 'failed'
                ? 'bg-ce-orange border border-ce-orange'
                : 'bg-transparent border border-ce-beige'
        return (
          <span
            key={index}
            className={`inline-block h-[8px] w-[8px] rounded-full ${fill}`}
          />
        )
      })}
    </div>
  )
}

function PhaseFooter({ phase, isCampaign }: { phase: CalendarPhase; isCampaign?: boolean }) {
  return (
    <div>
      <p
        className={`m-0 text-[14px] font-normal leading-[18px] ${calendarPhaseClass(phase, isCampaign === true)}`}
      >
        {calendarPhaseLabel(phase)}
      </p>
      <PipelineDots phase={phase} isCampaign={isCampaign} />
    </div>
  )
}

function CalendarSlotTitle({ row, titleClass }: { row: AgendaRowDto; titleClass: string }) {
  const campaignTitle = (row.campaignTitle ?? '').trim()
  return (
    <>
      {campaignTitle !== '' ? (
        <p className="m-0 line-clamp-2 text-[14px] font-normal leading-[18px] text-ce-beige">{campaignTitle}</p>
      ) : null}
      <p className={`m-0 line-clamp-3 text-[14px] font-semibold leading-[18px] ${titleClass}`}>
        {agendaCampaignCellTitle(row)}
      </p>
    </>
  )
}

function CampaignPlacement({
  row,
  onSchedule,
}: {
  row: AgendaRowDto
  onSchedule?: (slotId: string, publishDate: string, publishTime?: string | null, platform?: string | null) => void
}) {
  const date = row.publishDate ?? ''
  const time = (row.publishTime ?? '').slice(0, 5)
  const platform = (row.platform ?? '').toLowerCase()
  if (!onSchedule || date === '') return null
  return (
    <div className="mt-[8px] flex flex-col gap-[6px]" onClick={(event) => event.preventDefault()}>
      <input
        key={`${row.slotId}-${time}`}
        type="time"
        aria-label="Tijdstip"
        defaultValue={time}
        onClick={(event) => event.preventDefault()}
        onBlur={(event) => onSchedule(row.slotId, date, event.target.value || null, platform || null)}
        className="h-[28px] w-full rounded-[14px] border-0 bg-ce-pink px-[10px] text-[14px] text-ce-black outline-none"
      />
      <div className="flex gap-[8px]">
        {(['LinkedIn', 'Instagram'] as const).map((label) => {
          const active = platform === label.toLowerCase()
          return (
            <button
              key={label}
              type="button"
              className={`cursor-pointer border-0 bg-transparent p-0 text-[14px] leading-[18px] ${active ? 'font-semibold text-ce-green-text' : 'text-ce-beige'}`}
              onClick={(event) => {
                event.preventDefault()
                event.stopPropagation()
                onSchedule(row.slotId, date, time || null, label.toLowerCase())
              }}
            >
              {label}
            </button>
          )
        })}
      </div>
    </div>
  )
}

function CalendarItemBody({
  row,
  onArchive,
  onDelete,
  onSchedule,
  busy,
}: {
  row: AgendaRowDto
  onArchive?: (row: AgendaRowDto) => void
  onDelete?: (row: AgendaRowDto) => void
  onSchedule?: (slotId: string, publishDate: string, publishTime?: string | null, platform?: string | null) => void
  busy?: boolean
}) {
  const phase = resolveCalendarPhase(row)
  const isCampaign = Boolean((row.campaignId ?? '').trim())
  const published = phase === 'published'
  const titleClass = published ? 'text-ce-beige' : 'text-ce-black'
  const slotPath = agendaSlotPath(row)

  if (published) {
    return (
      <>
        <Link to={slotPath} className="block min-w-0 no-underline">
          <CalendarSlotTitle row={row} titleClass={titleClass} />
        </Link>
        <div className="mt-auto pt-[18px]">
          <div className="flex flex-col items-start">
            <ActionLink variant="statusRow" onClick={() => onArchive?.(row)} disabled={busy || !onArchive}>
              Archiveer
            </ActionLink>
            <DeleteConfirmAction
              deleteLabel="Delete"
              onConfirm={() => onDelete?.(row)}
              disabled={busy || !onDelete}
              busy={busy}
              className={`cursor-pointer border-0 bg-transparent p-0 ${statusRowActionClass}`}
            />
          </div>
        </div>
      </>
    )
  }

  return (
    <Link to={slotPath} className="flex min-h-0 flex-1 flex-col no-underline">
      <CalendarSlotTitle row={row} titleClass={titleClass} />
      {isCampaign ? <CampaignPlacement row={row} onSchedule={onSchedule} /> : null}
      <div className="mt-auto pt-[18px]">
        <PhaseFooter phase={phase} isCampaign={isCampaign} />
      </div>
    </Link>
  )
}

function EmptyDayBody({ themeHref }: { themeHref: string }) {
  return (
    <Link to={themeHref} className="flex min-h-0 flex-1 flex-col no-underline">
      <p className="m-0 text-[14px] font-normal leading-[18px] text-ce-beige">—</p>
      <div className="mt-auto pt-[18px]">
        <PhaseFooter phase="theme" />
      </div>
    </Link>
  )
}

export function AgendaCalendar({
  groups,
  postsPerWeek = 2,
  horizonWeeks = 4,
  today = todayIso(),
  busySlotId = null,
  onSchedule,
  onArchive,
  onDelete,
}: AgendaCalendarProps) {
  const allRows = flattenAgendaRows(groups)
  const currentMonday = mondayOfIso(today) ?? today
  const [extraWeeks, setExtraWeeks] = useState(0)
  const sentinelRef = useRef<HTMLDivElement>(null)

  const calendarGroups = useMemo(
    () => calendarAgendaGroups(groups, currentMonday, horizonWeeks, extraWeeks),
    [groups, currentMonday, horizonWeeks, extraWeeks],
  )

  useEffect(() => {
    const node = sentinelRef.current
    if (!node || extraWeeks >= CALENDAR_MAX_AHEAD) return
    const observer = new IntersectionObserver(
      (entries) => {
        if (!entries.some((entry) => entry.isIntersecting)) return
        setExtraWeeks((current) => Math.min(CALENDAR_MAX_AHEAD, current + CALENDAR_SCROLL_BATCH))
      },
      { root: null, rootMargin: '240px', threshold: 0 },
    )
    observer.observe(node)
    return () => observer.disconnect()
  }, [extraWeeks])

  if (calendarGroups.length === 0) {
    return <p className="m-0 text-[14px] leading-[18px] text-ce-beige">Nog geen weken in de agenda.</p>
  }

  return (
    <div className="flex flex-col">
      {calendarGroups.map((group) => {
        const monday = weekMondayIso(group)
        const themeHref = monday ? `/theme?week=${encodeURIComponent(monday)}` : '/theme'
        const cells = buildWeekDayCells(group, allRows, postsPerWeek)
        const weekCampaigns = agendaCampaignTitles(cells.flatMap((cell) => cell.rows))

        return (
          <div
            key={monday ?? group.week}
            className="grid grid-cols-7 gap-x-[14px] pt-[18px] first:pt-0"
          >
            {weekCampaigns.length > 0 ? (
              <p className="col-span-7 m-0 pb-[8px] text-[14px] font-normal leading-[18px] text-ce-beige">
                {weekCampaigns.join(' · ')}
              </p>
            ) : null}
            {cells.map((cell) => {
              const tone = agendaDayTone(cell.date, today)
              return (
                <div
                  key={`${monday ?? group.week}-${cell.date}`}
                  className="flex min-h-[140px] flex-col border-b border-ce-beige pb-[18px]"
                  onDragOver={(event) => {
                    if (!onSchedule) return
                    event.preventDefault()
                  }}
                  onDrop={(event) => {
                    if (!onSchedule) return
                    event.preventDefault()
                    const slotId = event.dataTransfer.getData('text/ce-slot-id')
                    if (!slotId) return
                    const publishTime = event.dataTransfer.getData('text/ce-publish-time') || null
                    onSchedule(slotId, cell.date, publishTime)
                  }}
                >
                  <p className={dayLabelClass(tone)}>{cell.label}</p>
                  <div className="mt-[18px] flex min-h-0 flex-1 flex-col">
                    {cell.rows.length === 0 ? (
                      <EmptyDayBody themeHref={themeHref} />
                    ) : (
                      cell.rows.map((row) => (
                        <div
                          key={row.slotId}
                          draggable={Boolean(onSchedule)}
                          onDragStart={(event) => {
                            event.dataTransfer.setData('text/ce-slot-id', row.slotId)
                            event.dataTransfer.setData('text/ce-publish-time', row.publishTime ?? '')
                            event.dataTransfer.effectAllowed = 'move'
                          }}
                          className={`flex min-h-0 flex-1 flex-col ${onSchedule ? 'cursor-grab' : ''}`}
                        >
                          <CalendarItemBody
                            row={row}
                            onArchive={onArchive}
                            onDelete={onDelete}
                            onSchedule={onSchedule}
                            busy={busySlotId === row.slotId}
                          />
                        </div>
                      ))
                    )}
                  </div>
                </div>
              )
            })}
          </div>
        )
      })}
      {extraWeeks < CALENDAR_MAX_AHEAD ? (
        <div ref={sentinelRef} className="h-[1px]" aria-hidden />
      ) : null}
    </div>
  )
}

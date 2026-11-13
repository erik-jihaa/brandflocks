import { useMemo, useState } from 'react'
import { LIST_SECTION } from '../layout/grid.constants'
import { AgendaRow } from './StatusRow'
import { ArrowIcon } from './ArrowIcon'
import type { AgendaRowDto } from '../types/api'
import {
  agendaCampaignCellTitle,
  buildAgendaActions,
  resolveAgendaPhase,
  agendaPhaseLabel,
} from '../utils/agendaActions'
import { formatArtboardDayLabel } from '../utils/agendaCalendarGrid'
import {
  campaignColorDotClass,
  groupAgendaRowsByCampaign,
  type AgendaCampaignListGroup,
} from '../utils/agendaFilters'

type AgendaCampaignListProps = {
  rows: AgendaRowDto[]
  busySlotId: string | null
  onAction: (row: AgendaRowDto, action: 'delete' | 'archive') => void
}

function campaignRowTitle(row: AgendaRowDto): string {
  const date = (row.publishDate ?? '').trim()
  const dateLabel = /^\d{4}-\d{2}-\d{2}$/.test(date) ? formatArtboardDayLabel(date) : ''
  const body = agendaCampaignCellTitle(row)
  return dateLabel !== '' ? `${dateLabel} · ${body}` : body
}

function CampaignGroup({
  group,
  open,
  onToggle,
  busySlotId,
  onAction,
}: {
  group: AgendaCampaignListGroup
  open: boolean
  onToggle: () => void
  busySlotId: string | null
  onAction: (row: AgendaRowDto, action: 'delete' | 'archive') => void
}) {
  return (
    <section className={LIST_SECTION.sectionClass}>
      <button
        type="button"
        className="flex w-full cursor-pointer items-baseline justify-between gap-[12px] border-0 bg-transparent p-0 text-left"
        aria-expanded={open}
        onClick={onToggle}
      >
        <span className="flex min-w-0 items-baseline gap-[8px]">
          <span
            className={`relative top-[1px] inline-block h-[10px] w-[10px] shrink-0 rounded-full ${campaignColorDotClass(group.colorIndex)}`}
            aria-hidden
          />
          <span className={`${LIST_SECTION.headerClass} whitespace-normal break-words text-ce-black`}>
            {group.title}
          </span>
        </span>
        <span className="flex shrink-0 items-baseline gap-[8px] text-[14px] font-normal leading-[18px] text-ce-beige">
          {group.rangeLabel}
          <ArrowIcon direction={open ? 'down' : 'up'} className="h-[11px] w-[11px]" />
        </span>
      </button>
      {open ? (
        <div className={LIST_SECTION.bodyClass}>
          <div className={LIST_SECTION.bodyStackClass}>
            {group.rows.map((row) => (
              <AgendaRow
                key={row.slotId}
                title={campaignRowTitle(row)}
                phaseLabel={
                  row.phaseLabel ?? agendaPhaseLabel(resolveAgendaPhase(row), row.editorFirstName)
                }
                actions={buildAgendaActions(row)}
                busy={busySlotId === row.slotId}
                onAction={(nextAction) => {
                  if (busySlotId === row.slotId) return
                  onAction(row, nextAction)
                }}
              />
            ))}
          </div>
        </div>
      ) : (
        <div className="border-b border-ce-black/10 pb-[20px] pt-[8px]" />
      )}
    </section>
  )
}

export function AgendaCampaignList({ rows, busySlotId, onAction }: AgendaCampaignListProps) {
  const groups = useMemo(() => groupAgendaRowsByCampaign(rows), [rows])
  const [openKeys, setOpenKeys] = useState<Set<string>>(() => new Set())

  function toggle(key: string) {
    setOpenKeys((current) => {
      const next = new Set(current)
      if (next.has(key)) next.delete(key)
      else next.add(key)
      return next
    })
  }

  if (groups.length === 0) {
    return <p className="m-0 text-[14px] leading-[18px] text-ce-beige">Geen posts voor deze filters.</p>
  }

  return (
    <>
      <div className="mb-[8px] flex justify-end">
        <button
          type="button"
          className="cursor-pointer border-0 bg-transparent p-0 text-[14px] font-semibold leading-[18px] text-ce-black"
          onClick={() => setOpenKeys(new Set())}
        >
          Alles inklappen
        </button>
      </div>
      {groups.map((group) => (
        <CampaignGroup
          key={group.key}
          group={group}
          open={openKeys.has(group.key)}
          onToggle={() => toggle(group.key)}
          busySlotId={busySlotId}
          onAction={onAction}
        />
      ))}
    </>
  )
}

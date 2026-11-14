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
import {
  agendaColorIndexForCampaign,
  agendaSeriesLabel,
  campaignColorDotClass,
  groupAgendaRowsByDate,
  type AgendaDateListGroup,
} from '../utils/agendaFilters'

type AgendaDateListProps = {
  rows: AgendaRowDto[]
  allRowsForColors: AgendaRowDto[]
  busySlotId: string | null
  onAction: (row: AgendaRowDto, action: 'delete' | 'archive') => void
}

function DateGroup({
  group,
  open,
  onToggle,
  allRowsForColors,
  busySlotId,
  onAction,
}: {
  group: AgendaDateListGroup
  open: boolean
  onToggle: () => void
  allRowsForColors: AgendaRowDto[]
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
        <span className={`${LIST_SECTION.headerClass} whitespace-normal break-words text-ce-black`}>
          {group.dateLabel}
        </span>
        <span className="flex shrink-0 items-baseline gap-[8px] text-[14px] font-normal leading-[18px] text-ce-beige">
          {group.postCountLabel}
          <ArrowIcon direction={open ? 'down' : 'up'} className="h-[11px] w-[11px]" />
        </span>
      </button>
      {open ? (
        <div className={LIST_SECTION.bodyClass}>
          <div className={LIST_SECTION.bodyStackClass}>
            {group.rows.map((row) => {
              const series = agendaSeriesLabel(row)
              const colorIndex = agendaColorIndexForCampaign(allRowsForColors, row.campaignId)
              return (
                <div key={row.slotId} className="flex flex-col gap-[4px]">
                  <div className="flex flex-wrap items-baseline gap-x-[8px] gap-y-[2px]">
                    <span
                      className={`inline-block h-[8px] w-[8px] shrink-0 rounded-full ${campaignColorDotClass(colorIndex)}`}
                      aria-hidden
                    />
                    <span className="text-[14px] leading-[18px] text-ce-beige">{series}</span>
                  </div>
                  <AgendaRow
                    title={agendaCampaignCellTitle(row)}
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
                </div>
              )
            })}
          </div>
        </div>
      ) : (
        <div className="border-b border-ce-black/10 pb-[20px] pt-[8px]" />
      )}
    </section>
  )
}

export function AgendaDateList({ rows, allRowsForColors, busySlotId, onAction }: AgendaDateListProps) {
  const groups = useMemo(() => groupAgendaRowsByDate(rows), [rows])
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
    if (rows.length === 0) return null
    return (
      <p className="m-0 text-[14px] leading-[18px] text-ce-beige">
        Geen posts met een plan datum voor deze filters.
      </p>
    )
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
        <DateGroup
          key={group.key}
          group={group}
          open={openKeys.has(group.key)}
          onToggle={() => toggle(group.key)}
          allRowsForColors={allRowsForColors}
          busySlotId={busySlotId}
          onAction={onAction}
        />
      ))}
    </>
  )
}
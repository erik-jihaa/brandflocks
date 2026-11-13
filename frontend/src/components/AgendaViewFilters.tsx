import type { AgendaChannelOption, AgendaGroupMode, AgendaMonthOption } from '../utils/agendaFilters'

const toggleClass = (active: boolean) =>
  `border-0 bg-transparent p-0 text-[14px] font-semibold leading-[18px] ${
    active
      ? 'text-ce-black underline decoration-[1.5px] underline-offset-[3px]'
      : 'text-ce-black'
  }`

type AgendaViewFiltersProps = {
  groupMode: AgendaGroupMode
  onGroupMode: (mode: AgendaGroupMode) => void
  channel: string
  onChannel: (key: string) => void
  channels: AgendaChannelOption[]
  month: string
  onMonth: (key: string) => void
  months: AgendaMonthOption[]
}

export function AgendaViewFilters({
  groupMode,
  onGroupMode,
  channel,
  onChannel,
  channels,
  month,
  onMonth,
  months,
}: AgendaViewFiltersProps) {
  return (
    <div className="mb-[18px] flex flex-col gap-[12px]">
      <div id="agenda-groep-filter" className="flex flex-wrap items-baseline gap-x-[0px] gap-y-[8px]">
        <button
          type="button"
          className={toggleClass(groupMode === 'campaign')}
          aria-pressed={groupMode === 'campaign'}
          onClick={() => onGroupMode('campaign')}
        >
          Op campagne
        </button>
        <span className="px-[8px] text-[14px] font-semibold leading-[18px] text-ce-black">|</span>
        <button
          type="button"
          className={toggleClass(groupMode === 'date')}
          aria-pressed={groupMode === 'date'}
          onClick={() => onGroupMode('date')}
        >
          Op datum
        </button>
      </div>

      <div className="flex flex-wrap items-baseline gap-x-[0px] gap-y-[8px]" aria-label="Kanaal">
        {channels.map((option, index) => (
          <span key={option.key} className="inline-flex items-baseline">
            {index > 0 ? (
              <span className="px-[8px] text-[14px] font-semibold leading-[18px] text-ce-black">|</span>
            ) : null}
            <button
              type="button"
              className={toggleClass(channel === option.key)}
              aria-pressed={channel === option.key}
              onClick={() => onChannel(option.key)}
            >
              {option.label}
            </button>
          </span>
        ))}
      </div>

      {months.length > 2 ? (
        <div className="flex flex-wrap items-baseline gap-x-[0px] gap-y-[8px]" aria-label="Maand">
          {months.map((option, index) => (
            <span key={option.key} className="inline-flex items-baseline">
              {index > 0 ? (
                <span className="px-[8px] text-[14px] font-semibold leading-[18px] text-ce-black">|</span>
              ) : null}
              <button
                type="button"
                className={toggleClass(month === option.key)}
                aria-pressed={month === option.key}
                onClick={() => onMonth(option.key)}
              >
                {option.label}
              </button>
            </span>
          ))}
        </div>
      ) : null}
    </div>
  )
}

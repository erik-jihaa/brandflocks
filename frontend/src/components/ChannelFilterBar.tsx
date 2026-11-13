import type { AgendaChannelOption } from '../utils/agendaFilters'
import { filterToggleClass } from './channelFilterStyles'

type ChannelFilterBarProps = {
  channel: string
  onChannel: (key: string) => void
  channels: AgendaChannelOption[]
  /** Voor toegankelijkheid — standaard "Kanaal". */
  ariaLabel?: string
  className?: string
}

/** Addendum §9 — Alle kanalen | LinkedIn | Instagram (alleen overzicht). */
export function ChannelFilterBar({
  channel,
  onChannel,
  channels,
  ariaLabel = 'Kanaal',
  className = '',
}: ChannelFilterBarProps) {
  return (
    <div
      className={`flex flex-wrap items-baseline gap-x-[0px] gap-y-[8px] ${className}`.trim()}
      aria-label={ariaLabel}
    >
      {channels.map((option, index) => (
        <span key={option.key} className="inline-flex items-baseline">
          {index > 0 ? (
            <span className="px-[8px] text-[14px] font-semibold leading-[18px] text-ce-black">|</span>
          ) : null}
          <button
            type="button"
            className={filterToggleClass(channel === option.key)}
            aria-pressed={channel === option.key}
            onClick={() => onChannel(option.key)}
          >
            {option.label}
          </button>
        </span>
      ))}
    </div>
  )
}

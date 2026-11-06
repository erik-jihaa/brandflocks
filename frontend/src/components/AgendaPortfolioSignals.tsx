import type { AgendaPortfolioSignalDto } from '../types/api'
import { PageNavLink } from './ui/PageNavLink'

type AgendaPortfolioSignalsProps = {
  signals: AgendaPortfolioSignalDto[]
}

/** §8 — max. 2 portfoliosignalen boven de kalender (HBA-tenants). */
export function AgendaPortfolioSignals({ signals }: AgendaPortfolioSignalsProps) {
  if (signals.length === 0) return null

  return (
    <div className="mb-[18px] space-y-[10px]">
      {signals.map((signal) => (
        <p
          key={signal.id}
          className="m-0 text-[14px] leading-[18px] text-ce-black"
        >
          {signal.message}{' '}
          <PageNavLink to={signal.actionHref}>{signal.actionLabel}</PageNavLink>
        </p>
      ))}
    </div>
  )
}

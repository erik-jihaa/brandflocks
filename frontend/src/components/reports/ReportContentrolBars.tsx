import type { HomeContentrolMix } from '../../api/home'
import { POSITIONERING_TARGET_PCT, contentrolShares } from '../../utils/contentrolBalance'

/**
 * Contentrol-verdeling als staafdiagram (notitie 15 §2.2) — aandeel van het totaal,
 * met het doel voor positionering als streep in de balk.
 */
export function ReportContentrolBars({ mix }: { mix: HomeContentrolMix | null | undefined }) {
  const shares = contentrolShares(mix)
  if (shares.length === 0) return null

  return (
    <div className="flex w-full flex-col gap-[8px]">
      {shares.map((share) => {
        const isPositionering = share.role.toLowerCase() === 'positionering'
        return (
          <div key={share.role} className="flex items-center gap-[10px]">
            <span className="w-[84px] shrink-0 text-[14px] leading-[18px] text-ce-black">
              {share.label}
            </span>
            <span className="relative h-[10px] min-w-0 flex-1 rounded-full bg-ce-offwhite">
              <span
                className={`absolute inset-y-0 left-0 rounded-full ${
                  isPositionering ? 'bg-ce-blue' : 'bg-ce-beige'
                }`}
                style={{ width: `${Math.max(2, Math.min(100, share.pct))}%` }}
              />
              {isPositionering ? (
                <span
                  className="absolute -inset-y-[3px] w-[1.5px] bg-ce-black"
                  style={{ left: `${POSITIONERING_TARGET_PCT}%` }}
                  aria-hidden
                />
              ) : null}
            </span>
            <span className="w-[34px] shrink-0 text-right text-[14px] leading-[18px] text-ce-black">
              {share.pct}%
            </span>
          </div>
        )
      })}
      <p className="m-0 pt-[2px] text-[14px] leading-[18px] text-ce-black">
        Streep in de blauwe balk: je doel van {POSITIONERING_TARGET_PCT}% positionering.
      </p>
    </div>
  )
}

import { Link } from 'react-router-dom'
import type { HbaProgress } from '../api/hbaV2'
import { HBA_SCORED_POSTS_FOR_OVERVIEW } from '../constants/hbaOverview'
import { LIST_SECTION } from '../layout/grid.constants'

type HomeBuildProgressProps = {
  progress: HbaProgress
  /** Uit als de baseline-melding er al boven staat. */
  showBaselineMessage?: boolean
  /** In een grotere sectie (Rapport lege staat) — geen extra lijn eromheen. */
  embedded?: boolean
}

/** Notitie 15 §1.7 — voortgang vóór de eerste volledige meting. */
export function HomeBuildProgress({
  progress,
  showBaselineMessage = true,
  embedded = false,
}: HomeBuildProgressProps) {
  // Gescoorde posts, niet gepubliceerde: de eerste meting wacht op de scores.
  const buildCount = progress.scoredPosts ?? 0
  const target = HBA_SCORED_POSTS_FOR_OVERVIEW
  const ringFilled = Math.min(buildCount, target)

  return (
    <section className={embedded ? 'pt-[12px]' : LIST_SECTION.dashboardSectionClass}>
      <p className="m-0 text-[14px] leading-[18px] text-ce-black">
        {progress.architectureComplete ? (
          'Je merkarchitectuur staat.'
        ) : (
          <>
            Vul je merkarchitectuur in op{' '}
            <Link to="/brandarchitecture" className="font-semibold text-ce-blue underline">
              Merkarchitectuur
            </Link>
            .
          </>
        )}
      </p>
      {showBaselineMessage && progress.baselineMessage ? (
        <p className="m-0 mt-[8px] text-[14px] leading-[18px] text-ce-black">{progress.baselineMessage}</p>
      ) : null}
      <div className="mt-[12px] flex flex-wrap items-center gap-[8px]">
        {Array.from({ length: target }, (_, i) => (
          <span
            key={i}
            className={`inline-block h-[10px] w-[10px] rounded-full ${
              i < ringFilled ? 'bg-ce-green' : 'border border-ce-beige bg-transparent'
            }`}
            aria-hidden
          />
        ))}
        <span className="text-[14px] leading-[18px] text-ce-black">
          {ringFilled} van {target} posts voor de eerste meting
        </span>
      </div>
    </section>
  )
}

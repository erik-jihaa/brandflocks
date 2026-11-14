import { Link } from 'react-router-dom'
import type { HbaProgress } from '../api/hbaV2'
import { BrandPositionTrendChart } from './BrandPositionTrendChart'
import { ArrowIcon } from './ArrowIcon'
import { hbaContentrolLabel } from '../constants/hba'
import { formatHbaScoreValue } from '../utils/hbaScoreDisplay'
import { pulldownBoxClass, frameLineGapBeforeClass } from '../design/identity.constants'

type BrandWatcherSectionProps = {
  progress: HbaProgress
  /** Overview = compact; Report = expanded with meer detail */
  variant?: 'compact' | 'expanded'
}

function TrendArrow({ trend, delta }: { trend: HbaProgress['pbsTrend']; delta: number | null | undefined }) {
  if (!trend || trend === 'flat') {
    return <span className="text-[14px] leading-[18px] text-ce-beige">→ stabiel</span>
  }
  const direction = trend === 'up' ? 'up' : 'down'
  const color = trend === 'up' ? 'text-ce-green-text' : 'text-ce-orange'
  const label = trend === 'up' ? 'stijgt' : 'daalt'
  const deltaText = delta != null ? ` ${Math.abs(delta).toFixed(0)}` : ''

  return (
    <span className={`inline-flex items-center gap-[4px] text-[14px] leading-[18px] ${color}`}>
      <ArrowIcon direction={direction} className="h-[10px] w-[10px]" />
      {label}
      {deltaText}
    </span>
  )
}

function ProgressCounters({ progress }: { progress: HbaProgress }) {
  const { architectureComplete, themesChosen, themesReinforcing, hintsApplied } = progress

  return (
    <ul className="m-0 list-none space-y-[8px] p-0 text-[14px] leading-[18px] text-ce-black">
      <li>
        {architectureComplete ? (
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
      </li>
      {themesChosen > 0 ? (
        <li>
          {themesReinforcing} van de {themesChosen} gekozen thema&apos;s versterkten je positie.
        </li>
      ) : null}
      {hintsApplied > 0 ? <li>Je paste {hintsApplied} aanwijzingen toe.</li> : null}
    </ul>
  )
}

function TrackerDetails({ progress }: { progress: HbaProgress }) {
  const details = progress.trackerDetails
  if (!details) return null

  const { contentRoles, storyTriangle, averages } = details
  const displayMode = progress.scoreDisplayMode === 'decimal' ? 'decimal' : 'band'
  const hasRoles = contentRoles.length > 0
  const hasTriangle = storyTriangle.some((item) => item.avg != null)
  const hasAverages = averages.some((item) => item.avg != null)

  if (!hasRoles && !hasTriangle && !hasAverages) return null

  return (
    <div className="mt-[16px] grid gap-[16px] border-t border-ce-black/10 pt-[16px]">
      {hasRoles ? (
        <div>
          <p className="m-0 text-[14px] font-semibold leading-[18px] text-ce-black">Contentrollen</p>
          <ul className="m-0 mt-[8px] list-none space-y-[6px] p-0 text-[14px] leading-[18px] text-ce-beige">
            {contentRoles.map((item) => (
              <li key={item.role}>
                {hbaContentrolLabel(item.role)} — {item.count}×
              </li>
            ))}
          </ul>
        </div>
      ) : null}
      {hasTriangle ? (
        <div>
          <p className="m-0 text-[14px] font-semibold leading-[18px] text-ce-black">Verhaaldriehoek (gemiddeld)</p>
          <ul className="m-0 mt-[8px] list-none space-y-[6px] p-0 text-[14px] leading-[18px] text-ce-beige">
            {storyTriangle
              .filter((item) => item.avg != null)
              .map((item) => (
                <li key={item.label}>
                  {item.label}: {item.avg}
                </li>
              ))}
          </ul>
        </div>
      ) : null}
      {hasAverages ? (
        <div>
          <p className="m-0 text-[14px] font-semibold leading-[18px] text-ce-black">Gemiddelden</p>
          <ul className="m-0 mt-[8px] list-none space-y-[6px] p-0 text-[14px] leading-[18px] text-ce-beige">
            {averages
              .filter((item) => item.avg != null)
              .map((item) => (
                <li key={item.label}>
                  {item.label}: {formatHbaScoreValue(item.avg, displayMode)}
                </li>
              ))}
          </ul>
        </div>
      ) : null}
    </div>
  )
}

function RingProgress({ scoredPosts, targetPosts }: { scoredPosts: number; targetPosts: number }) {
  const ringFilled = Math.min(scoredPosts, targetPosts)
  const ringDots = Array.from({ length: targetPosts }, (_, i) => i < ringFilled)

  return (
    <div className="flex items-center gap-[8px]">
      {ringDots.map((filled, i) => (
        <span
          key={i}
          className={`inline-block h-[10px] w-[10px] rounded-full ${filled ? 'bg-ce-green' : 'border border-ce-beige bg-transparent'}`}
          aria-hidden
        />
      ))}
      <span className="ml-[8px] text-[14px] leading-[18px] text-ce-black">
        {ringFilled} van {targetPosts} artikelen
      </span>
    </div>
  )
}

/**
 * Meldingen die bij het merkcijfer horen (voetnoot, baseline, stilte).
 * Het erosiesignaal staat op het Overzicht (notitie 15 §1.2) en dus niet hier.
 */
export function BrandWatcherNotices({ progress }: { progress: HbaProgress }) {
  const { architectureFootnote, baselineMessage, idleMessage } = progress
  if (!architectureFootnote && !baselineMessage && !idleMessage) return null

  return (
    <div className="space-y-[8px] border-b border-ce-black/10 pb-[20px]">
      {architectureFootnote ? (
        <p className="m-0 text-[14px] leading-[18px] text-ce-black">{architectureFootnote}</p>
      ) : null}
      {baselineMessage ? (
        <p className="m-0 text-[14px] leading-[18px] text-ce-black">{baselineMessage}</p>
      ) : null}
      {idleMessage ? (
        <p className="m-0 text-[14px] leading-[18px] text-ce-black">
          {idleMessage}{' '}
          <Link to="/agenda" className="font-semibold text-ce-blue underline">
            Naar Agenda
          </Link>
        </p>
      ) : null}
    </div>
  )
}

/** De cijfers onder de grafieken — ingeklapt, want het Rapport leidt met tekst. */
export function BrandWatcherDetails({ progress }: { progress: HbaProgress }) {
  if (!progress.ok) return null

  return (
    <details className="group border-t border-ce-beige pt-[16px]">
      <summary className="flex cursor-pointer list-none items-center gap-[6px] text-[14px] font-semibold leading-[18px] text-ce-black [&::-webkit-details-marker]:hidden">
        <span>Onderliggende cijfers</span>
        <span className="inline-flex transition-transform group-open:rotate-180">
          <ArrowIcon direction="up" className="h-[9px] w-[9px]" />
        </span>
      </summary>
      <div className="pt-[12px]">
        <ProgressCounters progress={progress} />
        <TrackerDetails progress={progress} />
      </div>
    </details>
  )
}

/** HBA WP5 — stille bewaker op Overview en Report. */
export function BrandWatcherSection({ progress, variant = 'compact' }: BrandWatcherSectionProps) {
  if (!progress.ok) return null

  const {
    phase,
    scoredPosts,
    publishedPosts,
    voiceActive,
    targetPosts,
    showRevealMessage,
    pbsDisplayScore,
    pbsLabel,
    pbsHeadline,
    pbsTrend,
    pbsTrendDelta,
    pbsHistory = [],
    erosionAlert,
    idleMessage,
    architectureFootnote,
    baselineMessage,
  } = progress

  const showScore = phase !== 'A' && pbsDisplayScore != null
  const expanded = variant === 'expanded'

  const buildCount = Math.max(publishedPosts ?? 0, scoredPosts ?? 0)
  if (phase === 'A' || voiceActive === false) {
    return (
      <section className="min-w-0">
        <h2 className="m-0 text-[14px] font-semibold leading-[24px] text-ce-black">Je merk wordt opgebouwd</h2>
        {baselineMessage ? (
          <p className="m-0 mt-[8px] text-[14px] leading-[18px] text-ce-beige">{baselineMessage}</p>
        ) : null}
        <div className="mt-[12px]">
          <RingProgress scoredPosts={buildCount} targetPosts={targetPosts} />
        </div>
        <p className="m-0 mt-[8px] text-[14px] leading-[18px] text-ce-black">
          {buildCount}/{targetPosts} posts
        </p>
        <div className="mt-[12px]">
          <ProgressCounters progress={progress} />
        </div>
        <p className="m-0 mt-[12px] text-[14px] leading-[18px] text-ce-black">
          Tot {targetPosts} gepubliceerde posts leert de tool je stem uit architectuur en kern-bronnen — niet alsof er al een vast patroon is.
        </p>
      </section>
    )
  }

  return (
    <section className={`min-w-0 ${expanded ? 'border-b border-ce-black/10 pb-[18px]' : ''}`}>
      {showRevealMessage ? (
        <p className="m-0 mb-[12px] text-[14px] leading-[18px] text-ce-black">
          Je merk wordt nu gemeten. We hebben je eerste weken met terugwerkende kracht doorgerekend — je ziet meteen het verloop.
        </p>
      ) : null}

      {architectureFootnote ? (
        <p className="m-0 mb-[12px] text-[14px] leading-[18px] text-ce-black">{architectureFootnote}</p>
      ) : null}

      {baselineMessage ? (
        <p className="m-0 mb-[12px] text-[14px] leading-[18px] text-ce-black">{baselineMessage}</p>
      ) : null}

      {idleMessage ? (
        <p className="m-0 mb-[12px] text-[14px] leading-[18px] text-ce-black">
          {idleMessage}{' '}
          <Link to="/agenda" className="font-semibold text-ce-blue underline">
            Naar Agenda
          </Link>
        </p>
      ) : null}

      {erosionAlert ? (
        <div className={`${pulldownBoxClass} ${frameLineGapBeforeClass} mb-[12px] px-[30px] py-[16px]`}>
          <p className="m-0 text-[14px] leading-[18px] font-normal text-ce-black">
            {erosionAlert.message}{' '}
            {erosionAlert.linkPath ? (
              <Link to={erosionAlert.linkPath} className="font-semibold text-ce-blue underline">
                Bekijk thema&apos;s
              </Link>
            ) : null}
          </p>
        </div>
      ) : null}

      {showScore ? (
        <div className="flex flex-wrap items-end justify-between gap-[12px]">
          <div>
            {pbsLabel ? (
              <p className="m-0 text-[12px] font-semibold uppercase tracking-wide text-ce-beige">{pbsLabel}</p>
            ) : null}
            <p className="m-0 text-[48px] font-semibold leading-[52px] text-ce-blue">{pbsDisplayScore}</p>
            <p className="m-0 mt-[4px] text-[16px] font-semibold leading-[20px] text-ce-black">
              {pbsHeadline ?? 'Positionering'}
            </p>
          </div>
          {phase === 'C' ? <TrendArrow trend={pbsTrend} delta={pbsTrendDelta} /> : null}
        </div>
      ) : null}

      {phase === 'C' && pbsHistory.length >= 2 ? (
        <BrandPositionTrendChart history={pbsHistory} compact={!expanded} />
      ) : null}

      {phase === 'B' ? (
        <div className="mt-[16px]">
          <RingProgress scoredPosts={scoredPosts} targetPosts={targetPosts} />
        </div>
      ) : null}

      {phase === 'B' || expanded ? (
        <details className={`group ${phase === 'B' ? 'mt-[16px]' : 'mt-[16px] border-t border-ce-black/10 pt-[16px]'}`}>
          <summary className="flex cursor-pointer list-none items-center gap-[6px] text-[14px] font-semibold leading-[18px] text-ce-black [&::-webkit-details-marker]:hidden">
            <span>Meer details</span>
            <span className="inline-flex transition-transform group-open:rotate-180">
              <ArrowIcon direction="up" className="h-[9px] w-[9px]" />
            </span>
          </summary>
          <div className="pt-[12px]">
            <ProgressCounters progress={progress} />
            {expanded ? <TrackerDetails progress={progress} /> : null}
            {expanded && pbsHistory.length >= 2 ? (
              <div className="mt-[16px]">
                <BrandPositionTrendChart history={pbsHistory} />
              </div>
            ) : null}
          </div>
        </details>
      ) : (
        <p className="m-0 mt-[8px] text-[14px] leading-[18px] text-ce-beige">
          {scoredPosts} gepubliceerde artikelen gemeten
        </p>
      )}

      {phase === 'B' ? (
        <p className="m-0 mt-[12px] text-[14px] leading-[18px] text-ce-beige">
          Vanaf 20 artikelen wordt de score stabiel.
        </p>
      ) : null}

      {!expanded ? (
        <p className="m-0 mt-[12px] text-[14px] leading-[18px] text-ce-beige">
          Meer details en de volledige merkanalyse →{' '}
          <Link to="/report" className="font-semibold text-ce-blue underline">
            open het rapport
          </Link>
        </p>
      ) : null}
    </section>
  )
}

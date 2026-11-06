import type { HomeResult } from '../api/home'
import type { HbaProgress } from '../api/hbaV2'
import { HBA_SCORED_POSTS_FOR_OVERVIEW } from '../constants/hbaOverview'
import { POSITIONERING_TARGET_PCT, positioneringPct } from './contentrolBalance'
import { cumulativeToPerPeriod, directionLabel, seriesDirection } from './trendDirection'

export type HomeMeter = {
  id: string
  title: string
  value: string
  hint?: string
  trendLabel?: string
}

/**
 * Band voor het merkcijfer (0–100), zelfde grenzen als de kop uit de backend.
 * Nodig zolang de determinismemeting niet is geslaagd: dan geeft de API geen
 * getal meer, maar wel de ruwe score (notitie 15 §1.3, briefing 11).
 */
export function hbaBrandScoreBand(score: number | null | undefined): string | null {
  if (score == null || Number.isNaN(score)) return null
  if (score >= 75) return 'Sterk'
  if (score >= 55) return 'Op koers'
  return 'Aandacht nodig'
}

export function homeOverviewHasPbsMeters(hba: HbaProgress | null): boolean {
  if (!hba?.ok) return false
  if ((hba.scoredPosts ?? 0) < HBA_SCORED_POSTS_FOR_OVERVIEW) return false
  return hba.pbsDisplayScore != null || hba.pbsScore != null
}

/** weeklyData uit /api/home is cumulatief; de richting zit in de verschillen per week. */
function engagementTrendLabel(weeklyData: number[]): string | undefined {
  return directionLabel(seriesDirection(cumulativeToPerPeriod(weeklyData)))
}

function balansFromResult(result: HomeResult): { value: string; hint: string } | null {
  const positioning = positioneringPct(result.contentrolMix)
  if (positioning === null) return null

  return positioning < POSITIONERING_TARGET_PCT
    ? { value: 'Scheef', hint: `Positionering ${positioning}% (doel ${POSITIONERING_TARGET_PCT}%)` }
    : { value: 'In balans', hint: `Positionering ${positioning}%` }
}

/** Vijf meters voor Overzicht (notitie 15 §1.3) — geen grafieken. */
export function buildHomeOverviewMeters(
  hba: HbaProgress | null,
  result: HomeResult | undefined,
): HomeMeter[] {
  if (!result) return []

  const meters: HomeMeter[] = []
  const showPbs = homeOverviewHasPbsMeters(hba)

  // Zonder HBA-antwoord weten we niets over de architectuur; dan liever geen meter
  // dan "Incompleet" beweren.
  if (hba?.ok) {
    meters.push({
      id: 'architecture',
      title: 'Merkarchitectuur',
      value: hba.architectureComplete ? 'Staat' : 'Incompleet',
      hint: hba.architectureComplete ? 'Merkcontrole aan' : 'Vul Merkarchitectuur in',
    })
  }

  if (showPbs && hba) {
    const band = hbaBrandScoreBand(hba.pbsScore)
    const hasNumber = hba.pbsDisplayScore != null
    meters.push({
      id: 'pbs',
      title: 'Merkcijfer',
      value: hasNumber ? String(hba.pbsDisplayScore) : (band ?? '—'),
      hint: hasNumber ? (hba.pbsHeadline ?? undefined) : 'Nog geen exact cijfer',
      trendLabel:
        hba.pbsTrend === 'up'
          ? '↑ licht op'
          : hba.pbsTrend === 'down'
            ? '↓ dalend'
            : hba.pbsTrend === 'flat'
              ? '→ stabiel'
              : undefined,
    })
  }

  if (showPbs) {
    const balans = balansFromResult(result)
    if (balans) {
      meters.push({
        id: 'balance',
        title: 'Balans',
        value: balans.value,
        hint: balans.hint,
      })
    }
  }

  const engagement = result.engagementTrend
  if (engagement.hasEnoughData && engagement.totalEngagement > 0) {
    meters.push({
      id: 'engagement',
      title: 'Engagement',
      value: engagement.totalEngagement.toLocaleString('nl-NL'),
      hint: `${engagement.weeksSpan} weken`,
      trendLabel: engagementTrendLabel(engagement.weeklyData),
    })
  } else {
    // §1.4: engagement staat altijd als eigen meter, ook zonder data.
    meters.push({
      id: 'engagement',
      title: 'Engagement',
      value: '—',
      hint: 'Nog te weinig data',
    })
  }

  const goal = Math.max(1, result.postsPerWeek)
  const onSchedule = result.publishedThisWeek >= goal
  meters.push({
    id: 'rhythm',
    title: 'Ritme',
    value: `${result.publishedThisWeek}/${goal}`,
    hint: onSchedule ? 'Deze week op schema' : 'Achter op ritme deze week',
  })

  return meters
}

export function homeOverviewEarlyBuild(hba: HbaProgress | null): boolean {
  if (!hba?.ok) return false
  return !homeOverviewHasPbsMeters(hba)
}

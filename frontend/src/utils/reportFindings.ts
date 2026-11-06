import type { HbaPbsHistoryPoint, HbaProgress, HbaTrackerMetric } from '../api/hbaV2'
import type { HomeContentrolMix } from '../api/home'
import type { ReportDto } from '../api/reports'
import { POSITIONERING_TARGET_PCT, contentrolShares, positioneringPct } from './contentrolBalance'
import { hbaBrandScoreBand } from './homeOverviewMeters'

export type ScoreDisplayMode = 'decimal' | 'band'

/** Minimaal aantal posts in beide categorieën voor de op-strategie-vergelijking (§2.4). */
const MIN_POSTS_PER_CATEGORY = 3

/** Subdimensies van brand_contribution lopen 0–5 (briefing/cursor-hba-brandlaag.md §164). */
export const STORY_TRIANGLE_MAX = 5

function nl(value: number, decimals = 1): string {
  return value.toLocaleString('nl-NL', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  })
}

function scoredMetrics(metrics: HbaTrackerMetric[] | undefined): Array<{ label: string; avg: number }> {
  return (metrics ?? [])
    .filter((metric): metric is HbaTrackerMetric & { avg: number } => metric.avg != null)
    .map((metric) => ({ label: metric.label, avg: metric.avg }))
}

/** Merkverhaal-radar: sterkste en zwakste as in gewone taal (§2.2). Labels = API (`Persoonlijkheid`, `Doel`, …). */
export function storyTriangleFinding(
  hba: HbaProgress | null,
  displayMode: ScoreDisplayMode = 'decimal',
): string | null {
  const metrics = scoredMetrics(hba?.trackerDetails?.storyTriangle)
  if (metrics.length < 2) return null

  const sorted = [...metrics].sort((a, b) => b.avg - a.avg)
  const strongest = sorted[0]
  const weakest = sorted[sorted.length - 1]
  if (strongest.label === weakest.label) return null

  const gap = strongest.avg - weakest.avg
  const closing =
    gap < 0.5
      ? 'De assen liggen dicht bij elkaar; je verhaal is gelijkmatig opgebouwd.'
      : `Je verhaal leunt meer op ${strongest.label} dan op ${weakest.label}.`

  if (displayMode === 'band') {
    return `${strongest.label} komt het sterkst terug, ${weakest.label} het zwakst. ${closing}`
  }

  return `${strongest.label} komt het sterkst terug (${nl(strongest.avg)} van ${STORY_TRIANGLE_MAX}), ${weakest.label} het zwakst (${nl(weakest.avg)}). ${closing}`
}

/** Contentrol-verdeling: positionering tegen het doel, plus de twee grootste rollen (§2.2). */
export function contentrolFinding(mix: HomeContentrolMix | null | undefined): string | null {
  const positioning = positioneringPct(mix)
  const shares = contentrolShares(mix)
  if (positioning === null || shares.length === 0) return null

  const first = `Positionering staat op ${positioning}%, ${
    positioning < POSITIONERING_TARGET_PCT ? 'onder' : 'op of boven'
  } je doel van ${POSITIONERING_TARGET_PCT}%.`

  const top = shares.filter((share) => share.role.toLowerCase() !== 'positionering').slice(0, 2)
  if (top.length < 2) return first

  const combined = top[0].pct + top[1].pct
  return `${first} ${top[0].label} en ${top[1].label.toLowerCase()} nemen samen ${combined}% in.`
}

/**
 * Merkcijfer over tijd (§2.2). Het merkcijfer loopt 0–100, dus hele getallen;
 * de backend noemt een verschil van meer dan 1 punt een richting.
 */
function pbsBandPhrase(score: number | null | undefined): string {
  const band = hbaBrandScoreBand(score ?? null)
  if (band === 'Sterk') return 'je merk staat sterk'
  if (band === 'Op koers') return 'je merk staat op koers'
  if (band === 'Aandacht nodig') return 'je merk vraagt aandacht'
  return 'je merk beweegt langzaam'
}

export function pbsTrendFinding(
  history: HbaPbsHistoryPoint[] | undefined,
  displayMode: ScoreDisplayMode = 'decimal',
  hba: HbaProgress | null = null,
): string | null {
  if (!history || history.length < 2) return null

  const months = history.length === 2 ? 'twee maanden' : `${history.length} maanden`

  if (displayMode === 'band') {
    const trend = hba?.pbsTrend
    const direction =
      trend === 'up' ? 'Licht stijgend' : trend === 'down' ? 'Licht dalend' : 'Vrij stabiel'
    return `${direction} over ${months}; ${pbsBandPhrase(hba?.pbsScore)}.`
  }

  const first = Math.round(history[0].score)
  const last = Math.round(history[history.length - 1].score)
  const delta = last - first
  const direction = Math.abs(delta) <= 1 ? 'Vlak' : delta > 0 ? 'Stijgend' : 'Dalend'

  return `${direction}, van ${first} naar ${last} op een schaal van 100, over ${months}.`
}

export type StrategyVsGeneric = {
  onStrategy: number
  general: number
  strategyCount: number
  generalCount: number
  diffPct: number | null
  message: string
}

/**
 * §2.4 — jouw content op-strategie versus jouw generieke content.
 * Bewust géén branchevergelijking, en niet tonen zonder genoeg posts aan beide kanten.
 */
export function strategyVsGenericFinding(report: ReportDto | null): StrategyVsGeneric | null {
  if (!report) return null

  const strategyCount = report.articleCountStrategy ?? 0
  const generalCount = report.articleCountGeneral ?? 0
  if (strategyCount < MIN_POSTS_PER_CATEGORY || generalCount < MIN_POSTS_PER_CATEGORY) return null

  const onStrategy = report.medianOnStrategy ?? report.engagementOnStrategy ?? 0
  const general = report.medianGeneral ?? report.engagementGeneral ?? 0
  if (onStrategy <= 0 && general <= 0) return null

  const diffPct = report.engagementDiffPct ?? null
  const verb = onStrategy > general ? 'méér' : onStrategy < general ? 'minder' : 'evenveel'
  const amount = diffPct != null ? ` (${diffPct > 0 ? '+' : ''}${Math.round(diffPct)}%)` : ''

  return {
    onStrategy,
    general,
    strategyCount,
    generalCount,
    diffPct,
    message: `Je posts op-strategie kregen gemiddeld ${verb} engagement dan je generieke posts${amount}.`,
  }
}

/**
 * §2.1 — twee tot drie zinnen diagnose, vóór de eerste grafiek.
 * Elke zin leunt op data die er echt is; zonder data valt de zin weg.
 */
export function reportSummarySentences(
  hba: HbaProgress | null,
  mix: HomeContentrolMix | null | undefined,
  report: ReportDto | null,
): string[] {
  const sentences: string[] = []

  const trend = hba?.pbsTrend
  const shares = contentrolShares(mix)
  const leaning = shares.filter((share) => share.role.toLowerCase() !== 'positionering').slice(0, 2)

  if (trend === 'flat' && leaning.length === 2) {
    sentences.push(
      `Je merk bleef deze periode stabiel, maar leunt zwaar op ${leaning[0].label.toLowerCase()} en ${leaning[1].label.toLowerCase()}.`,
    )
  } else if (trend === 'up' && leaning.length === 2) {
    sentences.push(
      `Je merk werd deze periode licht sterker, maar leunt zwaar op ${leaning[0].label.toLowerCase()} en ${leaning[1].label.toLowerCase()}.`,
    )
  } else if (trend === 'down' && leaning.length === 2) {
    sentences.push(
      `Je merk werd deze periode iets zwakker, maar leunt zwaar op ${leaning[0].label.toLowerCase()} en ${leaning[1].label.toLowerCase()}.`,
    )
  } else if (trend === 'flat') {
    sentences.push('Je merk bleef deze periode stabiel.')
  } else if (trend === 'up') {
    sentences.push('Je merk werd deze periode licht sterker.')
  } else if (trend === 'down') {
    sentences.push('Je merk werd deze periode iets zwakker.')
  }

  const positioning = positioneringPct(mix)
  if (positioning !== null && positioning < POSITIONERING_TARGET_PCT) {
    sentences.push(
      `Positionering blijft achter op ${positioning}% — je doel is ${POSITIONERING_TARGET_PCT}%.`,
    )
  } else if (positioning !== null) {
    sentences.push(`Positionering zit met ${positioning}% op je doel.`)
  }

  const comparison = strategyVsGenericFinding(report)
  if (sentences.length < 3 && comparison) {
    sentences.push(comparison.message)
  }

  return sentences.slice(0, 3)
}

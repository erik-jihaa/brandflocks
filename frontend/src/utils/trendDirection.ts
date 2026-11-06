export type TrendDirection = 'up' | 'down' | 'flat'

/**
 * Richting van een reeks per periode (niet cumulatief): tweede helft tegen eerste helft.
 * Eén regel voor Overzicht en Rapport, zodat "stijgend" overal hetzelfde betekent.
 */
export function seriesDirection(perPeriod: number[]): TrendDirection | null {
  if (perPeriod.length < 4) return null

  const half = Math.floor(perPeriod.length / 2)
  const avg = (rows: number[]) => (rows.length === 0 ? 0 : rows.reduce((a, b) => a + b, 0) / rows.length)
  const earlier = avg(perPeriod.slice(0, half))
  const recent = avg(perPeriod.slice(perPeriod.length - half))
  const delta = recent - earlier

  if (Math.abs(delta) < Math.max(3, earlier * 0.1)) return 'flat'
  return delta > 0 ? 'up' : 'down'
}

export function directionLabel(direction: TrendDirection | null): string | undefined {
  if (direction === 'up') return '↑ stijgend'
  if (direction === 'down') return '↓ dalend'
  if (direction === 'flat') return '→ stabiel'
  return undefined
}

/** Cumulatieve reeks (zoals engagementTrend.weeklyData) naar waarden per week. */
export function cumulativeToPerPeriod(cumulative: number[]): number[] {
  return cumulative.map((value, index) => (index === 0 ? value : value - cumulative[index - 1]))
}

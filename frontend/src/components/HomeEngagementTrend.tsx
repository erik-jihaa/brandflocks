import { useCallback, useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import type { HomeEngagementTrend as HomeEngagementTrendData } from '../api/home'

type HomeEngagementTrendProps = {
  data: HomeEngagementTrendData
}

const CHART_HEIGHT = 140
const CHART_PAD_TOP = 12
const CHART_PAD_BOTTOM = 8

function isoDate(date: Date): string {
  return date.toISOString().slice(0, 10)
}

function isoWeekLabel(date: Date): string {
  const thursday = new Date(date)
  thursday.setDate(thursday.getDate() + 3 - ((thursday.getDay() + 6) % 7))
  const yearStart = new Date(thursday.getFullYear(), 0, 4)
  const week =
    1 +
    Math.round(
      ((thursday.getTime() - yearStart.getTime()) / 86400000 -
        3 +
        ((yearStart.getDay() + 6) % 7)) /
        7,
    )
  return `Week ${week}`
}

function buildWeekLabels(weeksSpan: number): string[] {
  const labels: string[] = []
  const now = new Date()
  for (let i = weeksSpan - 1; i >= 0; i--) {
    const d = new Date(now)
    d.setDate(d.getDate() - i * 7)
    labels.push(isoWeekLabel(d))
  }
  return labels
}

function chartPoints(values: number[], width: number, height: number): Array<{ x: number; y: number }> {
  if (values.length < 2 || width <= 0) return []
  const min = 0
  const max = Math.max(...values) || 1
  const range = max - min
  const plotH = height - CHART_PAD_TOP - CHART_PAD_BOTTOM
  const stepX = width / (values.length - 1)

  return values.map((value, index) => ({
    x: index * stepX,
    y: CHART_PAD_TOP + plotH - ((value - min) / range) * plotH,
  }))
}

function areaPath(points: Array<{ x: number; y: number }>, height: number): string {
  if (points.length < 2) return ''
  const baseline = height - CHART_PAD_BOTTOM
  return `M0,${baseline} L${points.map((p) => `${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(' L')} L${points[points.length - 1].x.toFixed(1)},${baseline} Z`
}

function linePath(points: Array<{ x: number; y: number }>): string {
  return points.map((p) => `${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(' ')
}

function articleEngagementTotal(data: HomeEngagementTrendData): number {
  return data.breakdown.articles.views
}

function linkedInEngagementTotal(data: HomeEngagementTrendData): number {
  const { reactions, likes, comments } = data.breakdown.linkedIn
  return reactions + likes + comments
}

function engagementDetailLine(data: HomeEngagementTrendData): string {
  const articleTotal = articleEngagementTotal(data)
  const linkedInTotal = linkedInEngagementTotal(data)
  const parts: string[] = []

  if (articleTotal > 0) {
    parts.push(`${articleTotal.toLocaleString('nl-NL')} weergaven (GA4)`)
  }
  if (linkedInTotal > 0) {
    parts.push(`${linkedInTotal.toLocaleString('nl-NL')} via LinkedIn`)
  }

  return parts.length > 0 ? parts.join(' · ') : 'Nog geen interacties geregistreerd'
}

/** Overview — cumulatieve engagement, volle breedte + GA4-achtige scrub */
export function HomeEngagementTrend({ data }: HomeEngagementTrendProps) {
  const navigate = useNavigate()
  const containerRef = useRef<HTMLDivElement>(null)
  const [chartWidth, setChartWidth] = useState(0)
  const [activeIndex, setActiveIndex] = useState<number | null>(null)

  const { totalEngagement, weeklyData, hasEnoughData, weeksSpan, lastWeekTotal } = data
  const weekLabels = buildWeekLabels(weeksSpan)
  const canChart = weeklyData.length >= 2 && chartWidth > 0
  const points = canChart ? chartPoints(weeklyData, chartWidth, CHART_HEIGHT) : []

  useEffect(() => {
    const el = containerRef.current
    if (!el) return

    const measure = () => setChartWidth(el.clientWidth)
    measure()

    const ro = new ResizeObserver(measure)
    ro.observe(el)
    return () => ro.disconnect()
  }, [])

  const indexFromClientX = useCallback(
    (clientX: number): number | null => {
      const el = containerRef.current
      if (!el || weeklyData.length < 2) return null
      const rect = el.getBoundingClientRect()
      const x = clientX - rect.left
      const ratio = Math.max(0, Math.min(1, x / rect.width))
      return Math.round(ratio * (weeklyData.length - 1))
    },
    [weeklyData.length],
  )

  function handlePointerMove(clientX: number) {
    setActiveIndex(indexFromClientX(clientX))
  }

  function handlePointerLeave() {
    setActiveIndex(null)
  }

  function goToReport() {
    const periodEnd = new Date()
    const periodStart = new Date()
    periodStart.setDate(periodStart.getDate() - weeksSpan * 7)
    navigate('/report', {
      state: { periodStart: isoDate(periodStart), periodEnd: isoDate(periodEnd) },
    })
  }

  const scrubIndex = activeIndex ?? weeklyData.length - 1
  const scrubValue = weeklyData[scrubIndex] ?? totalEngagement
  const scrubLabel = weekLabels[scrubIndex] ?? ''
  const prevValue = scrubIndex > 0 ? (weeklyData[scrubIndex - 1] ?? 0) : 0
  const scrubDelta = scrubValue - prevValue
  const isScrubbing = activeIndex !== null

  return (
    <section className="min-w-0">
      <p className="m-0 text-[14px] leading-[24px] font-semibold text-ce-black">Totale engagement</p>

      {!hasEnoughData && weeklyData.length < 2 ? (
        <div className="mt-[8px]">
          <p className="m-0 text-[14px] leading-[18px] text-ce-black">
            {totalEngagement > 0
              ? `${totalEngagement.toLocaleString('nl-NL')} interacties — nog te weinig weken voor een trend.`
              : 'Interacties verschijnen na publicatie (views, likes, comments op artikelen en LinkedIn).'}
          </p>
          {totalEngagement > 0 && (
            <p className="m-0 mt-[4px] text-[14px] leading-[18px] text-ce-black">{engagementDetailLine(data)}</p>
          )}
        </div>
      ) : (
        <div className="mt-[8px]">
          <div className="mb-[8px]">
            <p className="m-0 text-[24px] font-semibold leading-[28px] text-ce-black">
              {scrubValue.toLocaleString('nl-NL')}
            </p>
            <p className="m-0 mt-[2px] text-[14px] leading-[18px] text-ce-black">
              {isScrubbing ? (
                <>
                  {scrubLabel}
                  {scrubDelta > 0 && (
                    <> · +{scrubDelta.toLocaleString('nl-NL')} t.o.v. vorige week</>
                  )}
                </>
              ) : (
                <>
                  {weeksSpan} weken cumulatief · {engagementDetailLine(data)}
                  {!isScrubbing && lastWeekTotal > 0 && (
                    <> · deze week +{lastWeekTotal.toLocaleString('nl-NL')}</>
                  )}
                </>
              )}
            </p>
          </div>

          <div
            ref={containerRef}
            className="relative w-full cursor-crosshair touch-none"
            style={{ height: CHART_HEIGHT }}
            role="img"
            aria-label="Cumulatieve engagement — beweeg over de grafiek voor weekwaarden"
            onMouseMove={(e) => handlePointerMove(e.clientX)}
            onMouseLeave={handlePointerLeave}
            onTouchMove={(e) => {
              const touch = e.touches[0]
              if (touch) handlePointerMove(touch.clientX)
            }}
            onTouchEnd={handlePointerLeave}
            onClick={goToReport}
            onKeyDown={(e) => {
              if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault()
                goToReport()
              }
            }}
            tabIndex={0}
          >
            {canChart && (
              <svg
                width={chartWidth}
                height={CHART_HEIGHT}
                className="block"
                aria-hidden
              >
                <path d={areaPath(points, CHART_HEIGHT)} fill="var(--color-ce-green-text)" fillOpacity={0.12} />
                <polyline
                  points={linePath(points)}
                  fill="none"
                  stroke="var(--color-ce-green-text)"
                  strokeWidth={2}
                  strokeLinecap="round"
                  strokeLinejoin="round"
                />
                {activeIndex !== null && points[activeIndex] && (
                  <>
                    <line
                      x1={points[activeIndex].x}
                      y1={CHART_PAD_TOP}
                      x2={points[activeIndex].x}
                      y2={CHART_HEIGHT - CHART_PAD_BOTTOM}
                      stroke="var(--color-ce-black)"
                      strokeWidth={1}
                      strokeDasharray="4 3"
                      opacity={0.35}
                    />
                    <circle
                      cx={points[activeIndex].x}
                      cy={points[activeIndex].y}
                      r={5}
                      fill="var(--color-ce-green-text)"
                      stroke="var(--color-ce-offwhite)"
                      strokeWidth={2}
                    />
                  </>
                )}
              </svg>
            )}

            {isScrubbing && points[activeIndex!] && (
              <div
                className="pointer-events-none absolute z-10 rounded-[4px] bg-ce-black px-[8px] py-[4px] text-[12px] leading-[16px] text-ce-offwhite"
                style={{
                  left: Math.max(
                    8,
                    Math.min(chartWidth - 80, points[activeIndex!].x - 40),
                  ),
                  top: Math.max(0, points[activeIndex!].y - 32),
                }}
              >
                {scrubValue.toLocaleString('nl-NL')}
              </div>
            )}
          </div>

          {canChart && (
            <div className="mt-[4px] flex justify-between text-[12px] leading-[16px] text-ce-black">
              <span>{weekLabels[0]}</span>
              <span>{weekLabels[weekLabels.length - 1]}</span>
            </div>
          )}
        </div>
      )}
    </section>
  )
}

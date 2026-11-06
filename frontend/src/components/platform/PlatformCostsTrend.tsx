import { useCallback, useEffect, useRef, useState } from 'react'
import type { PlatformCostsTrend as PlatformCostsTrendData } from '../../api/platform'
import { formatEur } from './platformFormat'

type PlatformCostsTrendProps = {
  data: PlatformCostsTrendData
}

const CHART_HEIGHT = 140
const CHART_PAD_TOP = 12
const CHART_PAD_BOTTOM = 8

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

/** Finance — cumulatieve kosten per maand, stijl engagement-grafiek op Home. */
export function PlatformCostsTrend({ data }: PlatformCostsTrendProps) {
  const containerRef = useRef<HTMLDivElement>(null)
  const [chartWidth, setChartWidth] = useState(0)
  const [activeIndex, setActiveIndex] = useState<number | null>(null)

  const { monthlyData, monthLabels, hasEnoughData, monthsSpan, lastMonthTotal, totalCost } = data
  const canChart = monthlyData.length >= 2 && chartWidth > 0
  const points = canChart ? chartPoints(monthlyData, chartWidth, CHART_HEIGHT) : []

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
      if (!el || monthlyData.length < 2) return null
      const rect = el.getBoundingClientRect()
      const x = clientX - rect.left
      const ratio = Math.max(0, Math.min(1, x / rect.width))
      return Math.round(ratio * (monthlyData.length - 1))
    },
    [monthlyData.length],
  )

  function handlePointerMove(clientX: number) {
    setActiveIndex(indexFromClientX(clientX))
  }

  function handlePointerLeave() {
    setActiveIndex(null)
  }

  const scrubIndex = activeIndex ?? monthlyData.length - 1
  const scrubValue = monthlyData[scrubIndex] ?? totalCost
  const scrubLabel = monthLabels[scrubIndex] ?? ''
  const prevValue = scrubIndex > 0 ? (monthlyData[scrubIndex - 1] ?? 0) : 0
  const scrubDelta = scrubValue - prevValue
  const isScrubbing = activeIndex !== null

  return (
    <section className="min-w-0">
      <p className="m-0 text-[14px] leading-[24px] font-semibold text-ce-black">Totale kosten</p>

      {!hasEnoughData && monthlyData.length < 2 ? (
        <div className="mt-[8px]">
          <p className="m-0 text-[14px] leading-[18px] text-ce-black">
            {totalCost > 0
              ? `${formatEur(totalCost)} — nog te weinig maanden voor een trend.`
              : 'Kosten verschijnen zodra tenants content en integraties gebruiken.'}
          </p>
        </div>
      ) : (
        <div className="mt-[8px]">
          <div className="mb-[8px]">
            <p className="m-0 text-[24px] font-semibold leading-[28px] text-ce-black">{formatEur(scrubValue)}</p>
            <p className="m-0 mt-[2px] text-[14px] leading-[18px] text-ce-beige">
              {isScrubbing ? (
                <>
                  {scrubLabel}
                  {scrubDelta > 0 && <> · +{formatEur(scrubDelta)} t.o.v. vorige maand</>}
                </>
              ) : (
                <>
                  {monthsSpan} maanden cumulatief
                  {lastMonthTotal > 0 && <> · {formatEur(lastMonthTotal)} in geselecteerde maand</>}
                </>
              )}
            </p>
          </div>

          <div
            ref={containerRef}
            className="relative w-full cursor-crosshair touch-none"
            style={{ height: CHART_HEIGHT }}
            role="img"
            aria-label="Cumulatieve platformkosten — beweeg over de grafiek voor maandwaarden"
            onMouseMove={(e) => handlePointerMove(e.clientX)}
            onMouseLeave={handlePointerLeave}
            onTouchMove={(e) => {
              const touch = e.touches[0]
              if (touch) handlePointerMove(touch.clientX)
            }}
            onTouchEnd={handlePointerLeave}
          >
            {canChart && (
              <svg width={chartWidth} height={CHART_HEIGHT} className="block" aria-hidden>
                <path d={areaPath(points, CHART_HEIGHT)} fill="var(--color-ce-blue)" fillOpacity={0.12} />
                <polyline
                  points={linePath(points)}
                  fill="none"
                  stroke="var(--color-ce-blue)"
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
                      fill="var(--color-ce-blue)"
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
                  left: Math.max(8, Math.min(chartWidth - 80, points[activeIndex!].x - 40)),
                  top: Math.max(0, points[activeIndex!].y - 32),
                }}
              >
                {formatEur(scrubValue)}
              </div>
            )}
          </div>

          {canChart && (
            <div className="mt-[4px] flex justify-between text-[12px] leading-[16px] text-ce-beige">
              <span>{monthLabels[0]}</span>
              <span>{monthLabels[monthLabels.length - 1]}</span>
            </div>
          )}
        </div>
      )}
    </section>
  )
}

import { useEffect, useRef, useState } from 'react'
import type { HbaPbsHistoryPoint } from '../api/hbaV2'

const CHART_HEIGHT = 100
const CHART_PAD_TOP = 8
const CHART_PAD_BOTTOM = 8

function monthLabel(monthKey: string): string {
  const [year, month] = monthKey.split('-')
  const date = new Date(Number(year), Number(month) - 1, 1)
  return date.toLocaleDateString('nl-NL', { month: 'short', year: '2-digit' })
}

function chartPoints(values: number[], width: number, height: number): Array<{ x: number; y: number }> {
  if (values.length < 2 || width <= 0) return []
  const min = Math.max(0, Math.min(...values) - 5)
  const max = Math.min(100, Math.max(...values) + 5)
  const range = max - min || 1
  const plotH = height - CHART_PAD_TOP - CHART_PAD_BOTTOM
  const stepX = width / (values.length - 1)

  return values.map((value, index) => ({
    x: index * stepX,
    y: CHART_PAD_TOP + plotH - ((value - min) / range) * plotH,
  }))
}

function linePath(points: Array<{ x: number; y: number }>): string {
  return points.map((p) => `${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(' ')
}

type BrandPositionTrendChartProps = {
  history: HbaPbsHistoryPoint[]
  compact?: boolean
}

/** HBA stille bewaker — positionering over tijd (maandgemiddelden). */
export function BrandPositionTrendChart({ history, compact = false }: BrandPositionTrendChartProps) {
  const containerRef = useRef<HTMLDivElement>(null)
  const [chartWidth, setChartWidth] = useState(0)

  useEffect(() => {
    const el = containerRef.current
    if (!el) return
    const measure = () => setChartWidth(el.clientWidth)
    measure()
    const ro = new ResizeObserver(measure)
    ro.observe(el)
    return () => ro.disconnect()
  }, [])

  if (history.length < 2) {
    return (
      <p className="m-0 text-[14px] leading-[18px] text-ce-beige">
        Nog te weinig maanden voor een verlooplijn.
      </p>
    )
  }

  const values = history.map((point) => point.score)
  const canChart = chartWidth > 0
  const points = canChart ? chartPoints(values, chartWidth, CHART_HEIGHT) : []

  return (
    <div className={compact ? 'min-w-0' : 'mt-[12px] min-w-0'}>
      {!compact ? (
        <p className="m-0 mb-[8px] text-[14px] leading-[18px] font-semibold text-ce-black">
          Verloop positionering
        </p>
      ) : null}
      <div ref={containerRef} className="relative w-full" style={{ height: CHART_HEIGHT }}>
        {canChart && (
          <svg width={chartWidth} height={CHART_HEIGHT} className="block" aria-hidden>
            <polyline
              points={linePath(points)}
              fill="none"
              stroke="var(--color-ce-green-text)"
              strokeWidth={2}
              strokeLinecap="round"
              strokeLinejoin="round"
            />
            {points.map((point, index) => (
              <circle
                key={history[index]?.month ?? index}
                cx={point.x}
                cy={point.y}
                r={3}
                fill="var(--color-ce-green-text)"
              />
            ))}
          </svg>
        )}
      </div>
      <div className="mt-[4px] flex justify-between text-[12px] leading-[16px] text-ce-beige">
        <span>{monthLabel(history[0].month)}</span>
        <span>{monthLabel(history[history.length - 1].month)}</span>
      </div>
    </div>
  )
}

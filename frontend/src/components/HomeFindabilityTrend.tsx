import { useNavigate } from 'react-router-dom'
import { ArrowIcon } from './ArrowIcon'
import type { HomeFindabilityTrend as HomeFindabilityTrendData } from '../api/home'
import { findabilityScoreDetail } from '../utils/findabilityScore'

type HomeFindabilityTrendProps = {
  data: HomeFindabilityTrendData
}

const SPARKLINE_WIDTH = 100
const SPARKLINE_HEIGHT = 36

function sparklinePoints(values: number[]): string {
  if (values.length < 2) return ''
  const min = Math.min(...values, 0)
  const max = Math.max(...values, 100)
  const range = max - min || 1
  const stepX = SPARKLINE_WIDTH / (values.length - 1)

  return values
    .map((value, index) => {
      const x = index * stepX
      const y = SPARKLINE_HEIGHT - ((value - min) / range) * SPARKLINE_HEIGHT
      return `${x.toFixed(1)},${y.toFixed(1)}`
    })
    .join(' ')
}

function emptyMessage(data: HomeFindabilityTrendData): string {
  if (!data.hasQuestions) {
    return 'Voeg GEO- en SEO-vragen toe bij Vindbaarheid om je zichtbaarheid te meten.'
  }
  return 'Nog geen vindbaarheidsmeting — start een controle of wacht op de maandelijkse run.'
}

/** Overview — findability-score + trendsparkline */
export function HomeFindabilityTrend({ data }: HomeFindabilityTrendProps) {
  const navigate = useNavigate()
  const { score, geoScore, seoScore, trendDirection, trendData, hasEnoughData, hasMeasurement } = data

  const lineColor = trendDirection === 'down' ? 'var(--color-ce-orange)' : 'var(--color-ce-green-text)'
  const arrowDirection = trendDirection === 'up' ? 'up' : trendDirection === 'down' ? 'down' : 'right'

  function goToReport() {
    navigate('/report')
  }

  return (
    <section className="min-w-0">
      <p className="m-0 text-[14px] leading-[18px] font-semibold text-ce-black">Vindbaarheidsscore</p>

      {!hasMeasurement ? (
        <div className="mt-[8px]">
          <p className="m-0 text-[14px] leading-[18px] text-ce-black">{emptyMessage(data)}</p>
        </div>
      ) : (
        <button
          type="button"
          onClick={goToReport}
          className="mt-[8px] flex w-full cursor-pointer items-end justify-between gap-[12px] border-0 bg-transparent p-0 text-left"
          aria-label="Bekijk vindbaarheid in het rapport"
        >
          <span className="min-w-0">
            <span className="inline-flex items-center gap-[6px]">
              <span className="text-[24px] font-semibold leading-[28px] text-ce-black">{score}/100</span>
              {hasEnoughData && <ArrowIcon direction={arrowDirection} />}
            </span>
            <p className="m-0 mt-[2px] text-[14px] leading-[18px] text-ce-black">
              {findabilityScoreDetail(geoScore, seoScore)}
              {hasEnoughData && trendData.length > 0 ? ` · ${trendData.length} metingen` : ''}
            </p>
          </span>
          {hasEnoughData && (
            <svg
              width={SPARKLINE_WIDTH}
              height={SPARKLINE_HEIGHT}
              viewBox={`0 0 ${SPARKLINE_WIDTH} ${SPARKLINE_HEIGHT}`}
              role="img"
              aria-label="Verloop vindbaarheid"
              className="shrink-0"
            >
              <polyline
                points={sparklinePoints(trendData)}
                fill="none"
                stroke={lineColor}
                strokeWidth={2}
                strokeLinecap="round"
                strokeLinejoin="round"
              />
            </svg>
          )}
        </button>
      )}
    </section>
  )
}

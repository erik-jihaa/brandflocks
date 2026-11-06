import { findabilityScoreDetail } from '../utils/findabilityScore'

type FindabilityScoreSummaryProps = {
  score: number | null
  geoScore?: number | null
  seoScore?: number | null
  compact?: boolean
}

/** Findability — gecombineerde score 0–100 (gemiddelde GEO + SEO zichtbaarheid) */
export function FindabilityScoreSummary({
  score,
  geoScore = null,
  seoScore = null,
  compact = false,
}: FindabilityScoreSummaryProps) {
  if (score === null) {
    return (
      <p className="m-0 text-[14px] leading-[18px] text-ce-beige">
        Nog geen vindbaarheidsscore — voeg vragen toe en start een meting.
      </p>
    )
  }

  if (compact) {
    return (
      <p className="m-0 text-[14px] leading-[18px] text-ce-black">
        <span className="font-semibold">{score}%</span>
        <span className="text-ce-beige"> · {findabilityScoreDetail(geoScore, seoScore)}</span>
      </p>
    )
  }

  return (
    <div className="flex flex-wrap items-end gap-[16px]">
      <div>
        <p className="m-0 text-[14px] leading-[18px] font-semibold text-ce-black">Vindbaarheidsscore</p>
        <p className="m-0 pt-[4px] text-[36px] font-semibold leading-[40px] text-ce-blue">
          {score}
          <span className="text-[18px] leading-[24px] text-ce-beige">/100</span>
        </p>
      </div>
      <div className="min-w-[140px] flex-1 space-y-[8px]">
        {geoScore !== null && <ScoreBar label="GEO (AI)" value={geoScore} />}
        {seoScore !== null && <ScoreBar label="SEO (Google)" value={seoScore} />}
      </div>
    </div>
  )
}

function ScoreBar({ label, value }: { label: string; value: number }) {
  return (
    <div>
      <div className="mb-[4px] flex items-baseline justify-between gap-[8px]">
        <span className="text-[14px] leading-[18px] text-ce-black">{label}</span>
        <span className="text-[14px] leading-[18px] font-semibold text-ce-black">{value}%</span>
      </div>
      <div className="h-[6px] overflow-hidden rounded-full bg-ce-beige/30">
        <div
          className="h-full rounded-full bg-ce-green-text transition-[width]"
          style={{ width: `${Math.max(0, Math.min(100, value))}%` }}
        />
      </div>
    </div>
  )
}

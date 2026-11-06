import { pulldownBoxClass, frameLineGapBeforeClass } from '../design/identity.constants'
import { themeVerdictKlantLabel } from '../utils/themeVerdict'
import { verdictPillClass } from './ArticleHbaHintSection'
import { LoadingDots } from './LoadingDots'

type ThemeFitAdviceProps = {
  title?: string | null
  label?: string | null
  toelichting?: string | null
  loading?: boolean
}

export function ThemeFitAdvice({
  title = null,
  label = null,
  toelichting = null,
  loading = false,
}: ThemeFitAdviceProps) {
  const displayLabel = themeVerdictKlantLabel(label)
  if (loading && !displayLabel && !toelichting) {
    return (
      <div className="mt-[12px] mb-[18px]">
        <LoadingDots label="Positionering beoordelen…" className="text-ce-black" />
      </div>
    )
  }
  if (!displayLabel && !toelichting) {
    return null
  }

  return (
    <div className={`${pulldownBoxClass} ${frameLineGapBeforeClass} mb-[18px] px-[30px] py-[16px]`}>
      {title?.trim() ? (
        <p className="m-0 mb-[8px] text-[14px] font-semibold leading-[18px] text-ce-black">
          {title.trim()}
        </p>
      ) : null}
      {displayLabel ? (
        <span
          className={`mb-[8px] inline-flex w-fit rounded-full px-[10px] py-[4px] text-[12px] font-semibold ${verdictPillClass(displayLabel)}`}
        >
          {displayLabel}
        </span>
      ) : null}
      {toelichting ? (
        <p className={`m-0 text-[14px] font-normal leading-[18px] text-ce-black ${displayLabel ? 'mt-[8px]' : ''}`}>
          {toelichting}
        </p>
      ) : null}
    </div>
  )
}

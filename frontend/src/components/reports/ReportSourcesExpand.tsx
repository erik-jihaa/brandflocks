import { Link } from 'react-router-dom'
import type { HomeSourcesReview } from '../../api/home'
import { LIST_SECTION } from '../../layout/grid.constants'

export function ReportSourcesExpand({ review }: { review: HomeSourcesReview }) {
  if (review.expandTips.length === 0) return null

  return (
    <section className={LIST_SECTION.dashboardSectionClass}>
      <h2 className={`${LIST_SECTION.headerClass} text-ce-black`}>Je bronnen uitbreiden</h2>
      <p className="m-0 pt-[8px] text-[14px] leading-[18px] text-ce-black">{review.summary}</p>
      <div className="flex flex-col gap-[8px] pt-[12px]">
        {review.expandTips.map((tip) => (
          <div key={tip.id}>
            <p className="m-0 text-[14px] font-semibold leading-[18px] text-ce-black">{tip.label}</p>
            <p className="m-0 pt-[2px] text-[14px] leading-[18px] text-ce-black">{tip.reason}</p>
          </div>
        ))}
      </div>
      <Link
        to="/sources"
        className="mt-[12px] inline-block text-[14px] font-semibold leading-[18px] text-ce-blue underline-offset-[3px] hover:underline"
      >
        Naar Bronnen →
      </Link>
    </section>
  )
}

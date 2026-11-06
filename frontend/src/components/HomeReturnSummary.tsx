import { Link } from 'react-router-dom'
import { LIST_SECTION } from '../layout/grid.constants'

type HomeReturnSummaryProps = {
  message: string
  reportPath?: string
}

/** Notitie 15 §1.1 — alleen na >7 dagen afwezigheid. */
export function HomeReturnSummary({ message, reportPath = '/report' }: HomeReturnSummaryProps) {
  return (
    <section className={LIST_SECTION.dashboardSectionClass}>
      <h2 className={`${LIST_SECTION.headerClass} text-ce-black`}>Sinds je laatste bezoek</h2>
      <p className="m-0 text-[14px] leading-[21px] text-ce-black">{message}</p>
      <p className="m-0 pt-[8px]">
        <Link to={reportPath} className="text-[14px] leading-[18px] font-semibold text-ce-blue underline">
          Bekijk het volledige rapport
        </Link>
      </p>
    </section>
  )
}

import { Link } from 'react-router-dom'
import { LIST_SECTION } from '../layout/grid.constants'
import type { HomeMeter } from '../utils/homeOverviewMeters'

type HomeStatusMetersProps = {
  meters: HomeMeter[]
}

function HomeStatusMeterItem({ meter }: { meter: HomeMeter }) {
  return (
    <div className="min-w-0 break-words">
      <p className="m-0 text-[14px] leading-[18px] text-ce-black">{meter.title}</p>
      <p className="m-0 pt-[4px] text-[20px] font-semibold leading-[24px] text-ce-black">
        {meter.value}
        {meter.trendLabel ? (
          <span className="ml-[6px] text-[14px] font-normal leading-[18px] text-ce-black">
            {meter.trendLabel}
          </span>
        ) : null}
      </p>
      {meter.hint ? (
        <p className="m-0 pt-[2px] text-[14px] leading-[18px] text-ce-black">{meter.hint}</p>
      ) : null}
    </div>
  )
}

/** Notitie 15 §1.3 — vijf meters, geen grafieken. */
export function HomeStatusMeters({ meters }: HomeStatusMetersProps) {
  if (meters.length === 0) return null

  return (
    <section className={LIST_SECTION.dashboardSectionClass}>
      <h2 className={`${LIST_SECTION.headerClass} text-ce-black`}>Zo staat je merk ervoor</h2>
      <div className="grid grid-cols-[repeat(auto-fit,minmax(160px,1fr))] gap-[20px] pt-[12px]">
        {meters.map((meter) => (
          <HomeStatusMeterItem key={meter.id} meter={meter} />
        ))}
      </div>
      <p className="m-0 pt-[12px] text-[14px] leading-[18px] text-ce-black">
        Uitsplitsing per kanaal en verloop over tijd staan in het{' '}
        <Link to="/report" className="font-semibold text-ce-blue underline">
          rapport
        </Link>
        .
      </p>
    </section>
  )
}

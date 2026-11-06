import type { HbaProgress } from '../../api/hbaV2'
import { HBA_SCORED_POSTS_FOR_OVERVIEW } from '../../constants/hbaOverview'
import { LIST_SECTION } from '../../layout/grid.constants'
import { HomeBuildProgress } from '../HomeBuildProgress'

/** §2.5 — lege staat zonder grafieken met ruis. */
export function ReportNotEnoughData({ progress }: { progress: HbaProgress | null }) {
  return (
    <section className={LIST_SECTION.dashboardSectionClass}>
      <h2 className={`${LIST_SECTION.headerClass} text-ce-black`}>
        Nog niet genoeg gemeten voor een diagnose
      </h2>
      <p className="m-0 pt-[8px] text-[14px] leading-[21px] text-ce-black">
        Het rapport toont je positionering pas na minstens {HBA_SCORED_POSTS_FOR_OVERVIEW} beoordeelde
        posts — dezelfde drempel als op het Overzicht. Anders zouden de grafieken vooral ruis laten zien.
      </p>
      {progress?.ok ? <HomeBuildProgress progress={progress} showBaselineMessage={false} embedded /> : null}
    </section>
  )
}

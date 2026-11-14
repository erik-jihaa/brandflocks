import { borderedSectionClass } from '../design/identity.constants'
import { FieldSingle } from '../components/ui/Field'
import { PeriodAgendaOverview } from '../components/PeriodAgendaOverview'
import type { ArticleStartPlanItem } from './articleStartPlan'
import { planDateRange } from './articleStartPlan'

const SECTION = borderedSectionClass

type Props = {
  planItems: ArticleStartPlanItem[]
  onChangeItem: (id: string, patch: Partial<ArticleStartPlanItem>) => void
}

/** Stap 6 — één publicatiedag per artikel + overzicht bestaande planning. */
export function ArticleStartStepPeriode({ planItems, onChangeItem }: Props) {
  const { start, end } = planDateRange(planItems)

  return (
    <div className="space-y-[14px]">
      <p className="m-0 text-[14px] leading-[21px] text-ce-beige">
        Wijs voor elk artikel een eigen dag aan. Kanalen van hetzelfde artikel delen standaard die dag; verschuiven
        per kanaal volgt later.
      </p>
      <section className={SECTION}>
        <h2 className="m-0 text-[18px] leading-[22px] font-semibold text-ce-black">Publicatiedagen</h2>
        <div className="mt-[12px] flex flex-col gap-[12px]">
          {planItems.map((item, index) => (
            <div key={item.id}>
              <p className="m-0 mb-[6px] text-[14px] leading-[18px] font-semibold text-ce-black">
                Artikel {index + 1}
                {item.angle.trim() ? (
                  <span className="font-normal text-ce-beige"> — {item.angle.trim()}</span>
                ) : null}
              </p>
              <FieldSingle
                id={`article-start-date-${item.id}`}
                type="date"
                label="Datum"
                value={item.publishDate}
                onChange={(publishDate) => onChangeItem(item.id, { publishDate })}
                placeholder="Datum"
              />
            </div>
          ))}
        </div>
      </section>
      <PeriodAgendaOverview startDate={start} endDate={end} title="Wat er al gepland staat" />
    </div>
  )
}

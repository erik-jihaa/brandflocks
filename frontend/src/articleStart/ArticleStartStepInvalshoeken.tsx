import { borderedSectionClass } from '../design/identity.constants'
import { FieldSingle } from '../components/ui/Field'
import type { ArticleStartDraft } from './useArticleStartDraft'
import type { ArticleStartPlanItem } from './articleStartPlan'

const SECTION = borderedSectionClass

type Props = {
  draft: ArticleStartDraft
  planItems: ArticleStartPlanItem[]
  onChangeItem: (id: string, patch: Partial<ArticleStartPlanItem>) => void
}

/** Stap 5 — invalshoek per artikel (klikmodel; voorstellen lokaal uit thema tot LLM-API er is). */
export function ArticleStartStepInvalshoeken({ draft, planItems, onChangeItem }: Props) {
  return (
    <section className={SECTION}>
      <h2 className="m-0 text-[18px] leading-[22px] font-semibold text-ce-black">Invalshoeken per artikel</h2>
      <p className="m-0 mt-[8px] text-[14px] leading-[21px] text-ce-beige">
        Op basis van je onderwerp &quot;{draft.theme.trim() || '…'}&quot; en de merkonderdelen uit stap 3 stellen we per
        artikel een invalshoek voor. Pas aan waar nodig; de volgorde bepaalt niet de publicatiedatum — die kies je
        hierna.
      </p>
      <div className="mt-[14px] flex flex-col gap-[12px]">
        {planItems.map((item, index) => (
          <FieldSingle
            key={item.id}
            id={`article-start-angle-${item.id}`}
            showPencil
            label={`Artikel ${index + 1}`}
            value={item.angle}
            onChange={(angle) => onChangeItem(item.id, { angle })}
            placeholder="Invalshoek"
          />
        ))}
      </div>
    </section>
  )
}

import { useState } from 'react'
import { borderedSectionClass, borderedSectionStackClass } from '../design/identity.constants'
import { PillButton } from '../components/PillButton'
import type { ArticleStartDraft } from './useArticleStartDraft'
import type { ArticleStartPlanItem } from './articleStartPlan'
import { merkLabelsForDraft, selectedChannelLabels } from './articleStartPlan'
import { ARTICLE_START_MERK_LABELS } from './articleStartMerk'

const SECTION = borderedSectionClass

type AdviceChoice = 'volgen' | 'niet_volgen' | 'zelf'
type CheckChoice = 'akkoord' | 'aanpassen'

type Props = {
  draft: ArticleStartDraft
  planItems: ArticleStartPlanItem[]
}

function formatDateNl(iso: string): string {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(iso)) return iso || '—'
  const [y, m, d] = iso.split('-')
  return `${Number(d)}-${Number(m)}-${y}`
}

/** Stap 7 — planoverzicht (klikmodel `p-artikelstart-klopt`); bevestigen zit in de pagina-acties. */
export function ArticleStartStepKlopt({ draft, planItems }: Props) {
  const [advice, setAdvice] = useState<AdviceChoice | null>(null)
  const [check, setCheck] = useState<CheckChoice | null>(null)
  const channels = selectedChannelLabels(draft)
  const missingMerk = (['wat_je_doet', 'waarom', 'waar_je_tegen_bent', 'hoe_je_werkt', 'waar_je_nee_tegen_zegt'] as const).filter(
    (k) => !draft.merkOnderdelen.includes(k),
  )

  return (
    <div className={borderedSectionStackClass}>
      <section className={SECTION}>
        <h2 className="m-0 text-[18px] leading-[22px] font-semibold text-ce-black">Systeemcheck</h2>
        <p className="m-0 mt-[8px] text-[14px] leading-[21px] text-ce-beige">
          Automatische controles, geen aanbeveling. Past iets niet, dan zie je hier een waarschuwing; de keuze blijft
          aan jou.
        </p>
        <div className="mt-[10px] space-y-[8px] text-[14px] leading-[21px] text-ce-black">
          <div className="flex justify-between gap-[12px]">
            <span>Valt binnen je basisritme</span>
            <span className="text-ce-beige">Nog niet gekoppeld</span>
          </div>
          <div className="flex justify-between gap-[12px]">
            <span>Conflict met lopende reeksen</span>
            <span className="text-ce-beige">Geen check</span>
          </div>
        </div>
      </section>

      <section className={SECTION}>
        <h2 className="m-0 text-[18px] leading-[22px] font-semibold text-ce-black">Wat er is en wat ontbreekt</h2>
        <div className="mt-[10px] space-y-[8px] text-[14px] leading-[21px] text-ce-black">
          <div className="flex justify-between gap-[12px]">
            <span>Meegenomen bij deze artikelen</span>
            <span className="text-right text-ce-beige">{merkLabelsForDraft(draft)}</span>
          </div>
          {missingMerk.length > 0 ? (
            <p className="m-0 text-[14px] leading-[21px] text-ce-beige">
              Niet meegenomen: {missingMerk.map((k) => ARTICLE_START_MERK_LABELS[k]).join(' · ')}. Ga terug naar stap 3
              als je die wilt toevoegen.
            </p>
          ) : null}
        </div>
      </section>

      <section className={SECTION}>
        <h2 className="m-0 text-[18px] leading-[22px] font-semibold text-ce-black">De artikelen</h2>
        <div className="mt-[10px] space-y-[8px] text-[14px] leading-[21px] text-ce-black">
          <div className="flex justify-between gap-[12px]">
            <span>Aantal</span>
            <span className="text-ce-beige">{planItems.length}</span>
          </div>
          <div className="flex justify-between gap-[12px]">
            <span>Kanalen</span>
            <span className="text-right text-ce-beige">{channels.join(' · ') || '—'}</span>
          </div>
          {draft.channels.linkedin ? (
            <div className="flex justify-between gap-[12px]">
              <span>LinkedIn-vorm</span>
              <span className="text-ce-beige">{draft.linkedinType === 'artikel' ? 'Artikelen' : 'Posts'}</span>
            </div>
          ) : null}
        </div>
      </section>

      <section className={SECTION}>
        <h2 className="m-0 text-[18px] leading-[22px] font-semibold text-ce-black">Advies</h2>
        <p className="m-0 mt-[8px] text-[14px] leading-[21px] text-ce-beige">
          Per advies kies je: volgen, niet volgen, of zelf aanpassen. (Live plan-check volgt later; dit is de
          klikmodel-keuze.)
        </p>
        <div className="mt-[10px] flex flex-col gap-[10px]">
          <p className="m-0 text-[14px] leading-[21px] text-ce-black">
            Controleer of elk artikel een harde claim of feit uit stap 3 meeneemt.
          </p>
          <div className="flex flex-wrap gap-[6px]">
            {(
              [
                ['volgen', 'Volgen'],
                ['niet_volgen', 'Niet volgen'],
                ['zelf', 'Zelf aanpassen'],
              ] as const
            ).map(([key, label]) => (
              <PillButton
                key={key}
                variant={advice === key ? 'main' : 'second'}
                className="mb-0"
                onClick={() => setAdvice(key)}
              >
                {label}
              </PillButton>
            ))}
          </div>
        </div>
      </section>

      <section className={SECTION}>
        <h2 className="m-0 text-[18px] leading-[22px] font-semibold text-ce-black">Check vooraf</h2>
        <div className="mt-[10px] flex flex-col gap-[10px]">
          <p className="m-0 text-[14px] leading-[21px] text-ce-black">
            {missingMerk.includes('waar_je_tegen_bent')
              ? 'Dekking van “waar je tegen bent” is laag in deze reeks.'
              : 'Merkonderdelen uit stap 3 zijn meegenomen in dit plan.'}
          </p>
          <div className="flex flex-wrap gap-[6px]">
            {(
              [
                ['akkoord', 'Akkoord'],
                ['aanpassen', 'Aanpassen'],
              ] as const
            ).map(([key, label]) => (
              <PillButton
                key={key}
                variant={check === key ? 'main' : 'second'}
                className="mb-0"
                onClick={() => setCheck(key)}
              >
                {label}
              </PillButton>
            ))}
          </div>
        </div>
      </section>

      <section className={SECTION}>
        <h2 className="m-0 text-[18px] leading-[22px] font-semibold text-ce-black">Artikelvoorstel</h2>
        <p className="m-0 mt-[8px] text-[14px] leading-[21px] text-ce-beige">
          Zoveel artikelen als je bij stap 4 instelde, elk voor de kanalen die je daar koos, met de invalshoek uit stap
          5. Bij bevestigen maakt de app per artikel een thema + slot (via de bestaande snel-artikel-API) en zet die in
          je kalender/onderzoek.
        </p>
        <ul className="m-0 mt-[12px] list-none space-y-[12px] p-0">
          {planItems.map((item, index) => (
            <li key={item.id} className="text-[14px] leading-[21px] text-ce-black">
              <span className="font-semibold">
                Artikel {index + 1} · {channels.join(' + ') || 'geen kanalen'}
              </span>
              <span className="mt-[4px] block text-ce-beige">Invalshoek: &quot;{item.angle.trim() || '—'}&quot;</span>
              <span className="mt-[2px] block text-ce-beige">Datum: {formatDateNl(item.publishDate)}</span>
            </li>
          ))}
        </ul>
      </section>
    </div>
  )
}
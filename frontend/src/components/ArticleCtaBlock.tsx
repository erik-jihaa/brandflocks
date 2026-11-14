import { ARTICLE_CTA_VOORSTELLEN } from '../utils/articleCta'
import { borderedSectionClass } from '../design/identity.constants'
import { PillButton } from './PillButton'

type Props = {
  hasCta: boolean | null
  ctaText: string
  readonly?: boolean
  onChange: (next: { hasCta: boolean; ctaText: string }) => void
}

export function ArticleCtaBlock({ hasCta, ctaText, readonly = false, onChange }: Props) {
  const showVoorstellen = hasCta === true

  return (
    <section className={`${borderedSectionClass} mb-[16px]`}>
      <h3 className="m-0 mb-[8px] text-[14px] font-semibold leading-[18px] text-ce-black">Call-to-action</h3>
      <p className="m-0 mb-[10px] text-[14px] leading-[18px] text-ce-beige">
        Heeft deze post een CTA? Dit zet &apos;m op de spreidingsgrafiek in Rapport bij activatie in plaats van
        merkbouw — bepaal je zelf, de app leidt dit niet uit de tekst af.
      </p>
      {readonly ? (
        <p className="m-0 text-[14px] leading-[18px] text-ce-black">
          {hasCta === true
            ? `Ja — ${ctaText.trim() || 'activatie'}`
            : hasCta === false
              ? 'Nee — puur positionering'
              : 'Nog niet gekozen'}
        </p>
      ) : (
        <>
          <div className="flex flex-wrap gap-[8px]">
            <PillButton
              type="button"
              variant={hasCta === false ? 'main' : 'second'}
              onClick={() => onChange({ hasCta: false, ctaText: '' })}
            >
              Nee — puur positionering
            </PillButton>
            <PillButton
              type="button"
              variant={hasCta === true ? 'main' : 'second'}
              onClick={() => onChange({ hasCta: true, ctaText })}
            >
              Ja — activatie
            </PillButton>
          </div>
          {showVoorstellen ? (
            <div className="mt-[12px]">
              <p className="m-0 mb-[8px] text-[14px] leading-[18px] text-ce-beige">
                Kies een voorstel of schrijf je eigen tekst:
              </p>
              <div className="mb-[10px] flex flex-wrap gap-[8px]">
                {ARTICLE_CTA_VOORSTELLEN.map((voorstel, index) => (
                  <PillButton
                    key={voorstel}
                    type="button"
                    variant={ctaText.trim() === voorstel ? 'main' : 'second'}
                    onClick={() => onChange({ hasCta: true, ctaText: voorstel })}
                  >
                    Voorstel {index + 1}
                  </PillButton>
                ))}
              </div>
              <textarea
                value={ctaText}
                onChange={(e) => onChange({ hasCta: true, ctaText: e.target.value })}
                placeholder="Jouw CTA-tekst..."
                rows={3}
                className="w-full resize-y rounded-[8px] border border-ce-beige bg-transparent px-[10px] py-[8px] text-[14px] leading-[18px] text-ce-black outline-none"
              />
            </div>
          ) : null}
        </>
      )}
    </section>
  )
}
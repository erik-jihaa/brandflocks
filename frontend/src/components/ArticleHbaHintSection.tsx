import { Link } from 'react-router-dom'
import { borderedSectionClass } from '../design/identity.constants'
import { hintRewritePreviews, textHasOpenPlaceholders } from '../utils/hbaHintApply'
import { renderInvulveldHighlights } from '../utils/contentDiff'
import { LoadingDots } from './LoadingDots'
import { PillButton } from './PillButton'

const HINT_FRAME_CLASS = `${borderedSectionClass} mb-[16px] bg-transparent font-figtree font-normal`
const HINT_TITLE_CLASS = 'm-0 mb-[8px] text-[14px] leading-[18px] font-semibold text-ce-black'

export type ArticleHbaHintSectionProps = {
  hint: {
    id: string
    aanwijzing: string
    passageVoor?: string | null
    passageNa?: string | null
    headlineNa?: string | null
    ctaNa?: string | null
    regelOvertreden?: string | null
    invulvelden?: string[]
    bewijsveldBron?: string | null
    toegepast: boolean
  } | null
  loading?: boolean
  error?: string | null
  onRetry?: () => void
  onApply?: () => void
  onIgnore?: () => void
  applying?: boolean
}

const LABEL_STYLES: Record<string, string> = {
  versterkt: 'border border-ce-green-text text-ce-green-text bg-transparent',
  neutraal: 'border border-ce-beige text-ce-beige bg-transparent',
  verwatert: 'border border-ce-orange text-ce-orange bg-transparent',
  botst: 'border border-ce-orange text-ce-orange bg-transparent',
}

export function verdictPillClass(label: string): string {
  const key = label === 'verwatert' ? 'botst' : label
  return LABEL_STYLES[key] ?? LABEL_STYLES.neutraal
}

export function ArticleHbaHintSection({
  hint,
  loading = false,
  error = null,
  onRetry,
  onApply,
  onIgnore,
  applying = false,
}: ArticleHbaHintSectionProps) {
  if (loading) {
    return (
      <section className={HINT_FRAME_CLASS}>
        <LoadingDots label="We stellen iets voor…" className="text-ce-black" />
      </section>
    )
  }

  if (error && !hint?.aanwijzing) {
    return (
      <section className={HINT_FRAME_CLASS}>
        <p className={HINT_TITLE_CLASS}>Positionering scherper maken</p>
        <p className="m-0 text-[14px] font-normal leading-[18px] text-ce-black">{error}</p>
        {error.includes('merkarchitectuur') ? (
          <p className="m-0 pt-[8px] text-[14px] leading-[18px]">
            <Link to="/brandarchitecture" className="font-semibold text-ce-blue underline">
              Open Merkarchitectuur
            </Link>
          </p>
        ) : null}
        {onRetry ? (
          <div className="mt-[12px]">
            <PillButton type="button" onClick={onRetry}>
              Opnieuw
            </PillButton>
          </div>
        ) : null}
      </section>
    )
  }

  if (!hint?.aanwijzing) {
    return null
  }

  const previews = hintRewritePreviews(hint)
  const previewHasPlaceholders = previews.some((preview) => textHasOpenPlaceholders(preview.text))
  const canApply = Boolean(onApply && !hint.toegepast && previews.length > 0)
  /** Gewenste actie = main (groen volvlak); zolang Opnieuw zichtbaar is, is dat primair — Pas toe outline. */
  const showRetryButton = Boolean(onRetry && error)

  return (
    <section className={HINT_FRAME_CLASS}>
      <p className={HINT_TITLE_CLASS}>Positionering scherper maken</p>
      {hint.regelOvertreden ? (
        <p className="m-0 mb-[10px] text-[14px] font-normal leading-[18px] text-ce-black">
          {hint.regelOvertreden}
        </p>
      ) : null}
      <p className="m-0 text-[14px] font-normal leading-[18px] text-ce-black">{hint.aanwijzing}</p>
      {previews.length > 0 && !hint.toegepast ? (
        <div className="mt-[12px] flex flex-col gap-[10px]">
          {previews.map((preview) => (
            <div key={`${preview.label ?? 'passage'}:${preview.text}`}>
              {preview.label ? (
                <p className="m-0 mb-[4px] text-[14px] font-normal leading-[18px] text-ce-beige">
                  {preview.label}
                </p>
              ) : null}
              <p className="m-0 whitespace-pre-wrap text-[14px] font-normal leading-[18px] text-ce-red">
                {renderInvulveldHighlights(preview.text, `hint-${preview.label ?? 'passage'}`)}
              </p>
            </div>
          ))}
        </div>
      ) : null}
      {(hint.invulvelden?.length ?? 0) > 0 && !hint.toegepast && !previewHasPlaceholders ? (
        <div className="mt-[12px]">
          <p className="m-0 mb-[4px] text-[14px] font-normal leading-[18px] text-ce-beige">
            Vul zelf in
          </p>
          <ul className="m-0 list-none p-0">
            {hint.invulvelden?.map((field) => (
              <li
                key={field}
                className="m-0 mb-[6px] whitespace-pre-wrap text-[14px] font-normal leading-[18px] text-ce-red"
              >
                [{field}]
              </li>
            ))}
          </ul>
        </div>
      ) : null}
      {previewHasPlaceholders && !hint.toegepast ? (
        <p className="m-0 mt-[10px] text-[14px] font-normal leading-[18px] text-ce-black">
          De oranje stukken tussen haken vul je zelf in. Zolang die er staan, kun je niet publiceren.
        </p>
      ) : null}
      {canApply ? (
        <div className="mt-[12px] flex flex-wrap gap-[8px]">
          <PillButton
            type="button"
            variant={showRetryButton ? 'second' : 'main'}
            onClick={onApply}
            disabled={applying}
            loading={applying}
          >
            Pas toe
          </PillButton>
          {onIgnore ? (
            <PillButton type="button" variant="second" onClick={onIgnore}>
              Negeer
            </PillButton>
          ) : null}
        </div>
      ) : hint.toegepast ? (
        <p className="m-0 mt-[10px] text-[14px] font-normal leading-[18px] text-ce-green-text">Toegepast</p>
      ) : null}
      {error && hint.aanwijzing ? (
        <>
          <p className="m-0 mt-[10px] text-[14px] font-normal leading-[18px] text-ce-red">{error}</p>
          {onRetry ? (
            <div className="mt-[8px]">
              <PillButton type="button" onClick={onRetry}>
                Opnieuw
              </PillButton>
            </div>
          ) : null}
        </>
      ) : null}
      {canApply ? (
        <p className="m-0 mt-[12px] text-[14px] font-normal leading-[18px] text-ce-beige">
          Eén aanwijzing per keer — de belangrijkste. Jij houdt de regie.
        </p>
      ) : null}
    </section>
  )
}

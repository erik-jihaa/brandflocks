import { useEffect, useRef, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { apiFetch } from '../api/client'
import { MetaSection, RadioOption } from './MetaSection'
import { RadioSelected } from './ui/RadioOption'
import { FieldHint } from './ui/FieldHint'
import { IMAGE_RATIO_OPTIONS, PLATFORM_OPTIONS, imageRatioDisplayLabel } from '../constants/metaOptions'
import {
  coerceTextvormForPlatform,
  textvormDisplayLabel,
  textvormOptionsForPlatform,
} from '../constants/contentTextvorm'
import { useChannelConstraints } from '../hooks/useChannelConstraints'
import { toneDisplayLabel } from '../utils/toneStyles'
import { ArticleSelfReviewSection, type ArticleSelfReviewDto } from './ArticleSelfReviewSection'
import { ArticleHbaHintSection } from './ArticleHbaHintSection'
import { ArticleHbaSection } from './ArticleHbaSection'
import { ArticleCtaBlock } from './ArticleCtaBlock'
import { PillButton } from './PillButton'
import type { HbaArticleHint } from '../api/hbaV2'
import type { HbaScoreDto } from '../types/api'
import type { BrandNarrativeCompleteness } from '../types/onboarding'

function RationaleField({ value, onChange }: { value: string; onChange: (v: string) => void }) {
  const ref = useRef<HTMLTextAreaElement>(null)

  function syncHeight() {
    const el = ref.current
    if (!el) return
    el.style.height = 'auto'
    el.style.height = `${el.scrollHeight}px`
  }

  useEffect(() => {
    syncHeight()
  }, [value])

  return (
    <textarea
      ref={ref}
      value={value}
      onChange={(e) => {
        onChange(e.target.value)
        requestAnimationFrame(syncHeight)
      }}
      rows={1}
      aria-label="Toelichting"
      className="mt-[8px] w-full resize-none overflow-hidden border-0 bg-transparent p-0 text-[14px] leading-[18px] text-ce-black outline-none focus:ring-0"
    />
  )
}

type ArticleMetaSidebarProps = {
  rationale: string
  /** Optioneel — redacteur-rationale invullen (Fastlane) */
  onRationaleChange?: (value: string) => void
  /** Beige hint onder rationale-box (Fastlane) */
  rationaleHint?: string
  tone: string
  editor?: string | null
  imageRatio: string
  platform: string
  contentTextvorm?: string
  toneSuggested?: string
  editorSuggested?: string | null
  imageRatioSuggested?: string
  platformSuggested?: string
  onToneChange: (v: string) => void
  onEditorChange?: (v: string) => void
  onImageRatioChange: (v: string) => void
  onPlatformChange: (v: string) => void
  onContentTextvormChange?: (v: string) => void
  /** Objective-id; leeg = geen selectie */
  objective?: string | null
  objectiveSuggested?: string | null
  objectiveOptions?: ReadonlyArray<{ id: string; label: string; percentage: number }>
  onObjectiveChange?: (v: string) => void
  /** Feedback-pagina: kaarten boven Rationale (briefing FeedbackType+.png) */
  feedbackSlot?: React.ReactNode
  /** Approved/published: alleen geselecteerde waarden, geen AI/reviewer-suggesties */
  readonly?: boolean
  /** Verberg AI-aandachtspunten (approved-pagina) */
  hideSelfReview?: boolean
  selfReview?: ArticleSelfReviewDto | null
  slotId?: string
  articleContent?: string
  onArticleContentChange?: (
    content: string,
    options?: { incrementalCorrection?: boolean },
  ) => void
  /** Article: meta opslaan + CE-03 rewrite */
  onRewrite?: () => void
  rewriting?: boolean
  rewriteNotice?: string | null
  /** Tenant-specifieke tones uit Strategy; fallback: vaste meta-opties */
  toneOptions?: readonly string[]
  editorOptions?: readonly string[]
  /** HBA: Details-panel standaard open (bijv. feedback-pagina) */
  detailsDefaultOpen?: boolean
  hbaHint?: HbaArticleHint | null
  hbaHintLoading?: boolean
  hbaHintError?: string | null
  onHbaHintRetry?: () => void
  onHbaHintApply?: () => void
  onHbaHintIgnore?: () => void
  hbaHintApplying?: boolean
  hbaScore?: HbaScoreDto | null
  hbaScoreDisplayMode?: 'decimal' | 'band'
  hbaContentrolOverride?: string | null
  brandNarrativeCompleteness?: BrandNarrativeCompleteness | null
  onContentrolOverrideChange?: (value: string | null) => void
  onHbaRescore?: () => Promise<void>
  hbaNarrativeScoreBlockers?: string[] | null
  hbaScoreAutoWaiting?: boolean
  hbaRescoring?: boolean
  positioningArchetype?: string | null
  positioningBrandGoal?: string | null
  hideHbaContentrol?: boolean
  hasCta?: boolean | null
  ctaText?: string
  onCtaChange?: (next: { hasCta: boolean; ctaText: string }) => void
}

export function ArticleMetaSidebar({
  rationale,
  onRationaleChange,
  rationaleHint = 'Optioneel — na indienen kan AI een toelichting schrijven',
  tone,
  editor = null,
  imageRatio,
  platform,
  contentTextvorm = '',
  toneSuggested,
  editorSuggested,
  imageRatioSuggested,
  platformSuggested,
  onToneChange,
  onEditorChange,
  onImageRatioChange,
  onPlatformChange,
  onContentTextvormChange,
  objective = null,
  objectiveSuggested = null,
  objectiveOptions = [],
  onObjectiveChange,
  feedbackSlot,
  readonly = false,
  hideSelfReview = false,
  selfReview,
  slotId,
  articleContent,
  onArticleContentChange,
  onRewrite,
  rewriting = false,
  rewriteNotice,
  toneOptions,
  editorOptions = [],
  detailsDefaultOpen = false,
  hbaHint = null,
  hbaHintLoading = false,
  hbaHintError = null,
  onHbaHintRetry,
  onHbaHintApply,
  onHbaHintIgnore,
  hbaHintApplying = false,
  hbaScore = null,
  hbaScoreDisplayMode = 'band',
  hbaContentrolOverride = null,
  brandNarrativeCompleteness = null,
  onContentrolOverrideChange,
  onHbaRescore,
  hbaNarrativeScoreBlockers = null,
  hbaScoreAutoWaiting = false,
  hbaRescoring = false,
  positioningArchetype = null,
  positioningBrandGoal = null,
  hideHbaContentrol = false,
  hasCta = null,
  ctaText = '',
  onCtaChange,
}: ArticleMetaSidebarProps) {
  const [searchParams] = useSearchParams()
  const { constraints: channelConstraints } = useChannelConstraints()
  const textvormOptions = textvormOptionsForPlatform(platform, channelConstraints?.textvormByPlatform)
  const resolvedTextvorm = coerceTextvormForPlatform(platform, contentTextvorm)
  const textvormReadLabel =
    textvormOptions.find((o) => o.value === resolvedTextvorm)?.label ??
    textvormDisplayLabel(resolvedTextvorm)
  const [ceEnvironment, setCeEnvironment] = useState<string | null>(null)
  const [detailsOpen, setDetailsOpen] = useState(detailsDefaultOpen)
  const prevHintLoading = useRef(hbaHintLoading)

  useEffect(() => {
    let cancelled = false
    void apiFetch<{ environment?: string }>('/health')
      .then((data) => {
        if (!cancelled) setCeEnvironment(data.environment ?? null)
      })
      .catch(() => {
        if (!cancelled) setCeEnvironment(null)
      })
    return () => {
      cancelled = true
    }
  }, [])

  const clientHoofdoordeelPreview =
    searchParams.get('hbaKlant') === '1' &&
    (ceEnvironment === 'staging' || ceEnvironment === 'local')

  useEffect(() => {
    if (prevHintLoading.current && !hbaHintLoading) {
      setDetailsOpen(true)
    }
    prevHintLoading.current = hbaHintLoading
  }, [hbaHintLoading])

  useEffect(() => {
    if (hbaHintLoading) return
    if (hbaHint?.aanwijzing) {
      setDetailsOpen(true)
    }
  }, [hbaHintLoading, hbaHint?.aanwijzing])

  const [rationaleOpen, setRationaleOpen] = useState(false)

  const showSuggested = !readonly
  const resolvedToneOptions = toneOptions ?? []
  const showTone = resolvedToneOptions.length > 0
  const showEditor = editorOptions.length > 0
  const hasObjectives = objectiveOptions.length > 0
  const selectedObjectiveLabel = hasObjectives
    ? objectiveOptions.find((opt) => opt.id === objective)?.label
    : undefined

  const metaDetails = (
    <>
      <MetaSection
        label="Toelichting"
        open={rationaleOpen}
        onToggle={() => setRationaleOpen((v) => !v)}
        collapsible
      >
        {onRationaleChange ? (
          <>
            <RationaleField value={rationale} onChange={onRationaleChange} />
            {rationaleHint ? <FieldHint position="below">{rationaleHint}</FieldHint> : null}
          </>
        ) : (
          <p className="m-0 whitespace-pre-line text-[14px] leading-[18px] text-ce-black">
            {rationale}
          </p>
        )}
      </MetaSection>

      {showTone ? (
      <MetaSection label="Toon" collapsible={false}>
        {readonly ? (
          tone ? <RadioSelected label={toneDisplayLabel(tone)} /> : null
        ) : (
          <fieldset className="m-0 border-0 p-0">
            {resolvedToneOptions.map((opt) => (
              <RadioOption
                key={opt}
                name="tone"
                value={opt}
                label={toneDisplayLabel(opt)}
                checked={tone === opt}
                suggested={showSuggested && toneSuggested === opt}
                onChange={onToneChange}
              />
            ))}
          </fieldset>
        )}
      </MetaSection>
      ) : null}

      {showEditor && onEditorChange && (
        <MetaSection label="Auteur" collapsible={false}>
          {readonly ? (
            editor ? <RadioSelected label={editor} /> : null
          ) : (
            <fieldset className="m-0 border-0 p-0">
              {editorOptions.map((opt) => (
                <RadioOption
                  key={opt}
                  name="editor"
                  value={opt}
                  label={opt}
                  checked={editor === opt}
                  suggested={showSuggested && editorSuggested === opt}
                  onChange={onEditorChange}
                />
              ))}
            </fieldset>
          )}
        </MetaSection>
      )}

      <MetaSection label="Beeldverhouding" collapsible={false}>
        {readonly ? (
          imageRatio ? <RadioSelected label={imageRatioDisplayLabel(imageRatio)} /> : null
        ) : (
          <fieldset className="m-0 border-0 p-0">
            {IMAGE_RATIO_OPTIONS.map((opt) => (
              <RadioOption
                key={opt}
                name="image_ratio"
                value={opt}
                label={imageRatioDisplayLabel(opt)}
                checked={imageRatio === opt}
                suggested={showSuggested && imageRatioSuggested === opt}
                onChange={onImageRatioChange}
              />
            ))}
          </fieldset>
        )}
      </MetaSection>

      <MetaSection label="Kanaal" collapsible={false}>
        {readonly ? (
          platform ? <RadioSelected label={platform} /> : null
        ) : (
          <fieldset className="m-0 border-0 p-0">
            {PLATFORM_OPTIONS.map((opt) => (
              <RadioOption
                key={opt}
                name="platform"
                value={opt}
                label={opt}
                checked={platform === opt}
                suggested={showSuggested && platformSuggested === opt}
                onChange={(next) => {
                  onPlatformChange(next)
                  if (onContentTextvormChange) {
                    onContentTextvormChange(coerceTextvormForPlatform(next, contentTextvorm))
                  }
                }}
              />
            ))}
          </fieldset>
        )}
      </MetaSection>

      {textvormOptions.length > 1 ? (
        <MetaSection label="Tekstvorm" collapsible={false}>
          {readonly ? (
            contentTextvorm ? <RadioSelected label={textvormReadLabel} /> : null
          ) : onContentTextvormChange ? (
            <fieldset className="m-0 border-0 p-0">
              {textvormOptions.map((opt) => (
                <RadioOption
                  key={opt.value}
                  name="content_textvorm"
                  value={opt.value}
                  label={opt.label}
                  checked={coerceTextvormForPlatform(platform, contentTextvorm) === opt.value}
                  onChange={onContentTextvormChange}
                />
              ))}
            </fieldset>
          ) : (
            <RadioSelected label={textvormReadLabel} />
          )}
        </MetaSection>
      ) : null}

      {hasObjectives && (
        <MetaSection label="Doel" collapsible={false}>
          {readonly ? (
            selectedObjectiveLabel ? <RadioSelected label={selectedObjectiveLabel} /> : null
          ) : onObjectiveChange ? (
            <fieldset className="m-0 border-0 p-0">
              {objectiveOptions.map((opt) => (
                <RadioOption
                  key={opt.id}
                  name="objective"
                  value={opt.id}
                  label={opt.label}
                  checked={objective === opt.id}
                  suggested={showSuggested && objectiveSuggested === opt.id}
                  onChange={onObjectiveChange}
                />
              ))}
            </fieldset>
          ) : null}
        </MetaSection>
      )}

      {!readonly && onRewrite && (
        <div className="mt-[18px] flex flex-col items-start">
          <PillButton
            variant="second"
            disabled={rewriting}
            loading={rewriting}
            onClick={onRewrite}
          >
            Opnieuw schrijven
          </PillButton>
          {rewriteNotice && (
            <p className="m-0 mt-[8px] text-[14px] leading-[18px] text-ce-beige">{rewriteNotice}</p>
          )}
        </div>
      )}
    </>
  )

  return (
    <>
      {feedbackSlot && <div className="mb-[16px] flex flex-col gap-[16px]">{feedbackSlot}</div>}

      {hbaHintLoading || (hbaHintError && !hbaHint?.aanwijzing) || hbaHint?.aanwijzing ? (
        <h3 className="m-0 mb-[8px] text-[14px] font-semibold leading-[18px] text-ce-black">Advies</h3>
      ) : null}
      <ArticleHbaHintSection
        hint={hbaHint}
        loading={hbaHintLoading}
        error={hbaHintError}
        onRetry={onHbaHintRetry}
        onApply={onHbaHintApply}
        onIgnore={onHbaHintIgnore}
        applying={hbaHintApplying}
      />

      {onContentrolOverrideChange && onHbaRescore ? (
        <>
          <h3 className="m-0 mb-[8px] text-[14px] font-semibold leading-[18px] text-ce-black">Score</h3>
          <ArticleHbaSection
            hbaScore={hbaScore}
            scoreDisplayMode={hbaScoreDisplayMode}
            contentrolOverride={hbaContentrolOverride}
            narrativeCompleteness={brandNarrativeCompleteness}
            readonly={readonly}
            onContentrolOverrideChange={onContentrolOverrideChange}
            onRescore={onHbaRescore}
            narrativeScoreBlockers={hbaNarrativeScoreBlockers}
            scoreAutoWaiting={hbaScoreAutoWaiting}
            rescoring={hbaRescoring}
            positioningArchetype={positioningArchetype}
            positioningBrandGoal={positioningBrandGoal}
            clientHoofdoordeelPreview={clientHoofdoordeelPreview}
            hideHbaContentrol={hideHbaContentrol}
          />
        </>
      ) : null}

      {!hideSelfReview && (
        <ArticleSelfReviewSection
          selfReview={selfReview}
          defaultOpen={true}
          slotId={slotId}
          content={articleContent}
          onContentChange={onArticleContentChange}
          readonly={readonly}
        />
      )}

      {onCtaChange || readonly ? (
        <ArticleCtaBlock
          hasCta={hasCta}
          ctaText={ctaText}
          readonly={readonly || !onCtaChange}
          onChange={onCtaChange ?? (() => undefined)}
        />
      ) : null}

      <h3 className="m-0 mb-[8px] text-[14px] font-semibold leading-[18px] text-ce-black">Instellingen</h3>
      <MetaSection label="Toon en kanaal" open={detailsOpen} onToggle={() => setDetailsOpen((v) => !v)}>
        {detailsOpen ? metaDetails : null}
      </MetaSection>
    </>
  )
}

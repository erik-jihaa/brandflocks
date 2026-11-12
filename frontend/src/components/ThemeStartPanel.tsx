import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { PillButton } from './PillButton'
import { patchTheme } from '../api/onboarding'
import { ThemeFitAdvice } from './ThemeFitAdvice'
import { fetchThemeVerdicts, runThemeVerdicts, type HbaThemeVerdict } from '../api/hbaV2'
import { themeVerdictToelichtingForDisplay } from '../utils/themeVerdict'
import type { ArticleDto } from '../types/api'
import { stripEditorQuestionsBlock } from '../utils/editorQuestions'

/** Campagnethema zonder artikeltekst — geen indienen, wel starten via onderzoek. */
export function isThemeStartArticle(
  article: ArticleDto | null | undefined,
  options: { writing?: boolean } = {},
): boolean {
  if (!article || options.writing) return false
  if (article.themeOnly) return true
  const body = stripEditorQuestionsBlock(article.contentAi ?? article.content ?? '').trim()
  if (body !== '') return false
  const origin = (article.slotOrigin ?? '').toLowerCase()
  if (origin === 'campagne_preview') return true
  return article.status === 'themed' || article.slotStatus === 'themed'
}

function formatThemeDate(value: string | null | undefined): string {
  const raw = (value ?? '').trim()
  if (raw === '') return ''
  const d = new Date(`${raw}T12:00:00`)
  if (Number.isNaN(d.getTime())) return raw
  return d.toLocaleDateString('nl-NL', { weekday: 'short', day: 'numeric', month: 'short' })
}

export function ThemeStartPanel({
  article,
  slotId,
  onCopyModusChange,
}: {
  article: ArticleDto
  slotId: string
  onCopyModusChange?: (next: Pick<ArticleDto, 'copyModus' | 'copyModusOverride' | 'copyModusLabel'>) => void
}) {
  const navigate = useNavigate()
  const themeId = (article.themeId ?? '').trim()
  const [copyBusy, setCopyBusy] = useState(false)
  const copyModus = (article.copyModus ?? 'merkbouw_bewijs').trim()
  const showActivatieOverride =
    copyModus === 'merkbouw_bewijs' && !article.copyModusOverride && themeId !== ''
  const [themeFit, setThemeFit] = useState<HbaThemeVerdict | null>(null)
  const [themeFitLoading, setThemeFitLoading] = useState(false)
  const [positioningArchetype, setPositioningArchetype] = useState<string | null>(
    article.positioningArchetype ?? null,
  )

  useEffect(() => {
    const key = themeId || slotId
    if (!key) return

    let cancelled = false
    setThemeFitLoading(true)
    const apply = (res: { verdicts?: HbaThemeVerdict[]; positioningArchetype?: string | null; code?: string }) => {
      if (cancelled) return
      if (res.positioningArchetype !== undefined) {
        setPositioningArchetype(res.positioningArchetype)
      }
      if (res.code === 'architecture_incomplete' || res.code === 'guardrail_inactive') {
        setThemeFit(null)
        return
      }
      const match =
        (res.verdicts ?? []).find((row) => row.themeId === key)
        ?? (res.verdicts ?? []).find((row) => row.themeId === themeId)
        ?? res.verdicts?.[0]
        ?? null
      if (match) setThemeFit(match)
    }

    void fetchThemeVerdicts(undefined, themeId || undefined, slotId)
      .then(apply)
      .catch(() => {})
    void runThemeVerdicts(undefined, themeId || undefined, slotId)
      .then(apply)
      .catch(() => {})
      .finally(() => {
        if (!cancelled) setThemeFitLoading(false)
      })

    return () => {
      cancelled = true
    }
  }, [themeId, slotId])

  const metaParts = [
    formatThemeDate(article.publishDate),
    article.platform,
    article.campaignFaseLabel,
  ].filter((part) => (part ?? '').trim() !== '')

  return (
    <div>
      {article.campaignTitle ? (
        <p className="m-0 mb-[8px] text-[14px] leading-[18px] text-ce-beige">{article.campaignTitle}</p>
      ) : null}
      <h2 className="m-0 text-[18px] font-semibold leading-[24px] text-ce-black">
        {article.headline.trim() || 'Thema'}
      </h2>
      {metaParts.length > 0 ? (
        <p className="m-0 mt-[8px] text-[14px] leading-[18px] text-ce-beige">{metaParts.join(' · ')}</p>
      ) : null}
      {article.rationale.trim() ? (
        <p className="m-0 mt-[12px] whitespace-pre-wrap text-[14px] leading-[18px] text-ce-black">
          {article.rationale.trim()}
        </p>
      ) : null}
      {article.copyModusLabel ? (
        <p className="m-0 mt-[10px] text-[13px] leading-[18px] text-ce-beige">
          Schrijfwijze: {article.copyModusLabel}
          {article.copyModusOverride ? ' (activatie gekozen)' : ''}
        </p>
      ) : null}
      {positioningArchetype ? (
        <ThemeFitAdvice
          label={themeFit?.label ?? null}
          toelichting={themeVerdictToelichtingForDisplay(themeFit?.toelichting, positioningArchetype)}
          loading={themeFitLoading && !themeFit}
        />
      ) : null}
      <div className="mt-[18px] flex flex-wrap items-center gap-[10px]">
        <PillButton variant="main" onClick={() => navigate(`/research/${slotId}`)}>
          Schrijf dit thema
        </PillButton>
        {showActivatieOverride ? (
          <PillButton
            variant="second"
            disabled={copyBusy}
            onClick={() => {
              setCopyBusy(true)
              void patchTheme(themeId, { copyModusOverrideActivatie: true })
                .then((res) => {
                  const t = res.theme
                  onCopyModusChange?.({
                    copyModus: t.copyModus ?? 'sell_activatie',
                    copyModusOverride: true,
                    copyModusLabel: t.copyModusLabel ?? 'Activatie',
                  })
                })
                .finally(() => setCopyBusy(false))
            }}
          >
            Schrijf met activatie-toon
          </PillButton>
        ) : null}
      </div>
    </div>
  )
}

import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { GridShell } from '../layout/GridShell'
import { BREADCRUMB_PUBLICATION_ARTICLE, NavBreadcrumb } from '../components/NavBreadcrumb'
import { ThemeReviewRow, normalizeThemeFeedback, type ThemeReviewFeedback } from '../components/ThemeReviewRow'
import { ThemeCustomCard } from '../components/ThemeCustomCard'
import { ThemeGenerationTips } from '../components/ThemeGenerationTips'
import { PillButton } from '../components/PillButton'
import { LoadingDots } from '../components/LoadingDots'
import { createTheme, fetchThemes, generateThemes, patchTheme, submitTheme, type ThemeListMeta } from '../api/onboarding'
import { isApiConfigured } from '../api/client'
import { themeMock } from '../data/theme.mock'
import type { ThemeDto } from '../types/onboarding'
import { ActionLink } from '../components/ui/ActionLink'
import { ActionNotice } from '../components/ui/ActionNotice'
import { useActionNotice } from '../hooks/useActionNotice'
import { bumpAgendaRefresh } from '../utils/agendaRefresh'
import { GRID2_INNER_WIDTH, GRID2_REVIEW_MAIN_WIDTH } from '../layout/grid.constants'
import { pulldownBoxClass, frameLineGapBeforeClass } from '../design/identity.constants'
import { fetchThemeVerdicts, runThemeVerdicts, type HbaThemeVerdict } from '../api/hbaV2'
import {
  themeVerdictConflictsWithArchetype,
  themeVerdictToelichtingForDisplay,
} from '../utils/themeVerdict'

const MAX_CANDIDATES = 3

type ThemeDraft = {
  title: string
  motivation: string
  sourceArticleUrl: string
  editorSuggested: string | null
  feedback: ThemeReviewFeedback
}

type CustomDraft = {
  title: string
  motivation: string
  sourceArticleUrl: string
}

const emptyCustomDraft = (): CustomDraft => ({
  title: '',
  motivation: '',
  sourceArticleUrl: '',
})

function draftFromTheme(theme: ThemeDto): ThemeDraft {
  return {
    title: theme.title,
    motivation: theme.motivation,
    sourceArticleUrl: theme.sourceArticleUrl,
    editorSuggested: theme.editorSuggested ?? theme.editorOptions?.[0] ?? null,
    feedback: normalizeThemeFeedback(theme.feedback),
  }
}

/** Theme-scherm — briefing/theme.png; één week tegelijk, max 3; backlog-navigatie */
export function ThemePage() {
  const navigate = useNavigate()
  const [searchParams, setSearchParams] = useSearchParams()
  const weekParam = searchParams.get('week') ?? undefined

  const [themes, setThemes] = useState<ThemeDto[]>([])
  const [meta, setMeta] = useState<ThemeListMeta | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [usingMock, setUsingMock] = useState(false)
  const [drafts, setDrafts] = useState<Record<string, ThemeDraft>>({})
  const [customDraft, setCustomDraft] = useState<CustomDraft>(emptyCustomDraft)
  const [submitting, setSubmitting] = useState(false)
  const [generating, setGenerating] = useState(false)
  const [awaitingThemePoll, setAwaitingThemePoll] = useState(false)
  const {
    message: actionMessage,
    kind: actionKind,
    fading: actionFading,
    showSuccess: showActionSuccess,
    showInfo: showActionInfo,
    clear: clearActionNotice,
  } = useActionNotice()
  const themesCountRef = useRef(0)
  const [verdicts, setVerdicts] = useState<Record<string, HbaThemeVerdict>>({})
  const [watOntbreekt, setWatOntbreekt] = useState<string | null>(null)
  const [verdictError, setVerdictError] = useState<string | null>(null)
  const [guardrailNotice, setGuardrailNotice] = useState<string | null>(null)
  const [verdictsLoading, setVerdictsLoading] = useState(false)
  const [positioningArchetype, setPositioningArchetype] = useState<string | null>(null)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    clearActionNotice()

    if (!isApiConfigured()) {
      setThemes(themeMock.slice(0, MAX_CANDIDATES))
      setMeta(null)
      setUsingMock(true)
      setLoading(false)
      return
    }

    try {
      const data = await fetchThemes(weekParam)
      setThemes(data.themes)
      setMeta(data.meta ?? null)
      setUsingMock(false)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Laden mislukt')
      setThemes(themeMock.slice(0, MAX_CANDIDATES))
      setMeta(null)
      setUsingMock(true)
    } finally {
      setLoading(false)
    }
  }, [weekParam])

  useEffect(() => {
    void load()
  }, [load])

  const themesReadyAfterGenerate = useCallback(
    (data: { themes: ThemeDto[]; meta?: ThemeListMeta | null }) => (data.themes?.length ?? 0) > 0,
    [],
  )

  const themeIdsKey = useMemo(() => themes.map((t) => t.id).join(','), [themes])
  themesCountRef.current = themes.length

  useEffect(() => {
    if (!awaitingThemePoll || usingMock) return

    let attempts = 0
    let unlocked = false
    let unlockedAtAttempt = 0
    const interval = window.setInterval(() => {
      attempts += 1
      if (attempts > 24 || (unlocked && attempts - unlockedAtAttempt >= 6)) {
        setAwaitingThemePoll(false)
        setGenerating(false)
        if (themesCountRef.current === 0 && !unlocked) {
          setError('Thema-generatie duurt langer dan verwacht — vernieuw de pagina over een minuut.')
        }
        window.clearInterval(interval)
        return
      }

      void fetchThemes(weekParam)
        .then((data) => {
          const nextIds = (data.themes ?? []).map((t) => t.id).join(',')
          setThemes((prev) => {
            const prevIds = prev.map((t) => t.id).join(',')
            return prevIds === nextIds ? prev : data.themes
          })
          setMeta(data.meta ?? null)

          if (!themesReadyAfterGenerate(data)) return

          if (!unlocked) {
            unlocked = true
            unlockedAtAttempt = attempts
            setGenerating(false)
            setError(null)
            showActionSuccess('Thema-voorstellen gegenereerd — kies je favoriet en dien in.')
          }

          // Soft poll tot we 3 voorstellen hebben (of timeout), zonder UI te blokkeren.
          if ((data.themes?.length ?? 0) >= MAX_CANDIDATES) {
            setAwaitingThemePoll(false)
            window.clearInterval(interval)
          }
        })
        .catch(() => {
          // stille retry tot timeout
        })
    }, 5000)

    return () => {
      window.clearInterval(interval)
    }
  }, [awaitingThemePoll, usingMock, weekParam, themesReadyAfterGenerate])

  useEffect(() => {
    const redirect = meta?.redirectWeekKey?.trim()
    if (!redirect || redirect === weekParam) return
    setSearchParams({ week: redirect })
  }, [meta?.redirectWeekKey, weekParam, setSearchParams])

  useEffect(() => {
    if (themes.length === 0) return
    setDrafts((prev) => {
      const next = { ...prev }
      for (const theme of themes) {
        if (!next[theme.id]) next[theme.id] = draftFromTheme(theme)
      }
      return next
    })
  }, [themes])

  useEffect(() => {
    if (usingMock || themes.length === 0) return

    let cancelled = false
    const apply = (res: {
      ok?: boolean
      verdicts?: HbaThemeVerdict[]
      watOntbreekt?: string | null
      error?: string
      code?: string
      architectureFootnote?: string
      positioningArchetype?: string | null
    }) => {
      if (cancelled) return
      if (res.positioningArchetype !== undefined) {
        setPositioningArchetype(res.positioningArchetype)
      }
      if (res.code === 'architecture_incomplete') {
        setVerdictError('Vul eerst je merkarchitectuur in op Merkarchitectuur.')
        return
      }
      if (res.code === 'guardrail_inactive') {
        setGuardrailNotice(
          res.architectureFootnote ??
            'Positioneringsadvies op thema\'s start na de architectuurcheck. Open Merkarchitectuur (stap 7) om die af te ronden.',
        )
        setVerdictError(null)
        return
      }
      if (res.verdicts && res.verdicts.length > 0) {
        const map: Record<string, HbaThemeVerdict> = {}
        for (const v of res.verdicts) map[v.themeId] = v
        setVerdicts(map)
        const wat = res.watOntbreekt ?? null
        setWatOntbreekt(
          themeVerdictConflictsWithArchetype(wat, res.positioningArchetype) ? null : wat,
        )
      } else if (res.verdicts) {
        setVerdicts({})
      }
      if (res.error && res.code !== 'architecture_incomplete') {
        setVerdictError(res.error)
      }
    }

    setVerdictError(null)
    setGuardrailNotice(null)
    setVerdictsLoading(true)
    void fetchThemeVerdicts(weekParam)
      .then(apply)
      .catch(() => {})

    void runThemeVerdicts(weekParam)
      .then((res) => {
        apply(res)
        if (!cancelled && !res.ok && res.code !== 'architecture_incomplete' && res.code !== 'guardrail_inactive' && !res.verdicts?.length) {
          setVerdictError(res.error || 'Thema-advies kon niet worden geladen.')
        }
      })
      .catch((err) => {
        if (!cancelled) {
          setVerdictError(err instanceof Error ? err.message : 'Thema-advies kon niet worden geladen.')
        }
      })
      .finally(() => {
        if (!cancelled) setVerdictsLoading(false)
      })

    return () => {
      cancelled = true
    }
  }, [usingMock, themeIdsKey, weekParam])

  const visibleThemes = useMemo(() => themes.slice(0, MAX_CANDIDATES), [themes])

  const weekLabel =
    meta?.weeks[meta.weekIndex]?.weekLabel || visibleThemes[0]?.weekLabel || 'Thema'
  const primaryCycleWeek =
    meta?.activeCycleWeek || visibleThemes[0]?.cycleWeek || ''
  // Soft poll (awaitingThemePoll) mag like/delete niet blokkeren zodra er themes zijn.
  const busy = submitting || (generating && visibleThemes.length === 0)
  const needsThemeGeneration = meta?.needsThemeGeneration === true
  const showWeekNav = Boolean(meta && meta.weekCount > 1)

  const themesStillRequired = meta?.themesStillRequired ?? 1
  const weekComplete = meta?.weekFull === true && themesStillRequired <= 0
  const postsPerWeek = meta?.postsPerWeek ?? 1
  const postsProduced = meta?.slotsFilledForWeek ?? 0
  const canSubmitThemes = !meta || themesStillRequired > 0
  const showProgressNotice = Boolean(actionMessage && actionKind === 'info')
  const showSubmitSuccessNotice = Boolean(actionMessage && actionKind === 'success' && canSubmitThemes)
  const showTopSuccessNotice = Boolean(actionMessage && actionKind === 'success' && !canSubmitThemes)
  const quotaHint = useMemo(() => {
    if (weekComplete) {
      if (postsPerWeek <= 1) {
        return 'Deze week is ingevuld — je thema staat klaar voor Onderzoek.'
      }
      return `Deze week is ingevuld — ${postsProduced} van ${postsPerWeek} posts gepland.`
    }
    if (themesStillRequired <= 0) {
      return null
    }
    if (themesStillRequired === 1) {
      return 'Kies 1 thema voor deze week.'
    }
    return `Kies nog ${themesStillRequired} thema's voor deze week.`
  }, [themesStillRequired, weekComplete, postsPerWeek, postsProduced])

  const prevWeek = meta && meta.weekIndex > 0 ? meta.weeks[meta.weekIndex - 1] : null
  const nextWeek =
    meta && meta.weekIndex >= 0 && meta.weekIndex < meta.weeks.length - 1
      ? meta.weeks[meta.weekIndex + 1]
      : null

  function goToWeek(weekKey: string | undefined) {
    if (!weekKey) {
      setSearchParams({})
      return
    }
    setSearchParams({ week: weekKey })
  }

  function handleThemeFeedback(themeId: string, feedback: 'liked' | 'deleted') {
    const theme = visibleThemes.find((t) => t.id === themeId)
    const current = drafts[themeId] ?? (theme ? draftFromTheme(theme) : null)
    if (!current) return

    if (feedback === 'liked' && canSubmitThemes) {
      const likedCount = visibleThemes.filter(
        (t) => t.id !== themeId && drafts[t.id]?.feedback === 'liked',
      ).length
      if (likedCount >= themesStillRequired) {
        setError(`Je kunt maximaal ${themesStillRequired} thema('s) kiezen voor deze week.`)
        return
      }
    }

    setError(null)
    const nextDraft = { ...current, feedback }
    setDrafts((prev) => ({
      ...prev,
      [themeId]: nextDraft,
    }))
  }

  async function persistTheme(theme: ThemeDto, draft: ThemeDraft) {
    if (usingMock) return theme

    const result = await patchTheme(theme.id, {
      title: draft.title.trim() || theme.title,
      motivation: draft.motivation,
      rationale: theme.rationale,
      sourceArticleUrl: draft.sourceArticleUrl,
      editorSuggested: draft.editorSuggested,
      feedback: draft.feedback,
    })
    return result.theme
  }

  async function handleThemeEditor(themeId: string, editorSuggested: string) {
    const theme = visibleThemes.find((item) => item.id === themeId)
    const current = drafts[themeId] ?? (theme ? draftFromTheme(theme) : null)
    if (!current) return

    const nextDraft = { ...current, editorSuggested }
    setDrafts((prev) => ({ ...prev, [themeId]: nextDraft }))

    if (usingMock || !theme) return
    try {
      const updated = await persistTheme(theme, nextDraft)
      setThemes((prev) => prev.map((item) => (item.id === themeId ? { ...item, ...updated } : item)))
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Auteur opslaan mislukt')
    }
  }

  async function persistExplicitDiscards() {
    if (usingMock) return

    for (const theme of visibleThemes) {
      const draft = drafts[theme.id] ?? draftFromTheme(theme)
      if (draft.feedback !== 'deleted') continue
      await persistTheme(theme, draft)
    }
  }

  function applyGenerateResult(result: {
    ok: boolean
    async?: boolean
    themes?: ThemeDto[]
    meta?: ThemeListMeta
    ce01?: { error?: string }
  }): boolean {
    if (!result.ok && (result.themes?.length ?? 0) === 0) {
      const message =
        (typeof result.ce01?.error === 'string' && result.ce01.error) ||
        'Thema-voorstellen genereren mislukt.'
      setError(message)
      return false
    }

    setThemes(result.themes ?? [])
    setMeta(result.meta ?? null)

    if ((result.themes?.length ?? 0) > 0) {
      setAwaitingThemePoll(false)
      setError(null)
      showActionSuccess('Thema-voorstellen gegenereerd — kies je favoriet en dien in.')
      return false
    }

    if (result.async) {
      setAwaitingThemePoll(true)
      showActionInfo('Thema-voorstellen worden gegenereerd… dit kan tot twee minuten duren.')
      return true
    }

    setError(
      (typeof result.ce01?.error === 'string' && result.ce01.error) ||
        'Geen thema-voorstellen ontvangen. Probeer opnieuw.',
    )
    return false
  }

  async function handleGenerateThemes() {
    setGenerating(true)
    setError(null)
    showActionInfo('Thema-voorstellen worden gegenereerd…')
    setAwaitingThemePoll(false)

    let keepGenerating = false
    try {
      if (usingMock) {
        await load()
        return
      }

      const weekKey = weekParam ?? meta?.activeWeekKey ?? ''
      const result = await generateThemes(weekKey !== '' ? weekKey : undefined)
      keepGenerating = applyGenerateResult(result)
    } catch (err) {
      // Proxy kan alsnog knippen; poll als fallback voor het geval CE-01 wél doordraaide.
      setAwaitingThemePoll(true)
      keepGenerating = true
      showActionInfo('Bezig met ophalen van thema-voorstellen…')
      setError(err instanceof Error ? err.message : 'Thema-voorstellen genereren mislukt')
    } finally {
      if (!keepGenerating) {
        setGenerating(false)
      }
    }
  }

  async function handleSubmitTheme() {
    setSubmitting(true)
    setError(null)
    clearActionNotice()

    try {
      const quota = themesStillRequired
      if (quota <= 0) {
        setError("Alle thema's voor deze week zijn al ingediend.")
        return
      }

      const likedThemes = visibleThemes.filter((t) => drafts[t.id]?.feedback === 'liked')
      const customTitle = customDraft.title.trim()

      if (likedThemes.length > quota) {
        setError(`Markeer maximaal ${quota} thema('s) voordat je indient.`)
        return
      }

      const themesToSubmit: Array<{ theme: ThemeDto; draft: ThemeDraft }> = []

      for (const theme of likedThemes.slice(0, quota)) {
        const draft = drafts[theme.id] ?? draftFromTheme(theme)
        themesToSubmit.push({ theme, draft })
      }

      if (customTitle !== '' && themesToSubmit.length < quota) {
        if (usingMock) {
          themesToSubmit.push({
            theme: {
              id: `mock-custom-${Date.now()}`,
              weekLabel,
              cycleWeek: primaryCycleWeek,
              title: customTitle,
              motivation: customDraft.motivation,
              rationale: '',
              sourceArticleUrl: customDraft.sourceArticleUrl,
              feedback: 'liked',
              status: 'proposed',
              researchItems: [],
            },
            draft: {
              title: customTitle,
              motivation: customDraft.motivation,
              sourceArticleUrl: customDraft.sourceArticleUrl,
              editorSuggested: null,
              feedback: 'liked',
            },
          })
        } else {
          const created = await createTheme({
            title: customTitle,
            motivation: customDraft.motivation,
            sourceArticleUrl: customDraft.sourceArticleUrl,
            cycleWeek: primaryCycleWeek || undefined,
          })
          themesToSubmit.push({
            theme: created.theme,
            draft: {
              title: customTitle,
              motivation: customDraft.motivation,
              sourceArticleUrl: customDraft.sourceArticleUrl,
              editorSuggested: created.theme.editorSuggested ?? created.theme.editorOptions?.[0] ?? null,
              feedback: 'liked',
            },
          })
        }
      }

      const allProposalsRejected =
        visibleThemes.length > 0 &&
        visibleThemes.every((t) => drafts[t.id]?.feedback === 'deleted') &&
        likedThemes.length === 0 &&
        customTitle === ''

      if (themesToSubmit.length === 0 && allProposalsRejected) {
        if (!usingMock) {
          await persistExplicitDiscards()
          setGenerating(true)
          setError(null)
          clearActionNotice()
          setAwaitingThemePoll(false)
          let keepGenerating = false
          try {
            const weekKey = weekParam ?? meta?.activeWeekKey ?? ''
            const result = await generateThemes(weekKey !== '' ? weekKey : undefined)
            keepGenerating = applyGenerateResult(result)
          } catch (err) {
            setAwaitingThemePoll(true)
            keepGenerating = true
            showActionInfo('Bezig met ophalen van thema-voorstellen…')
            setError(err instanceof Error ? err.message : 'Nieuwe thema-voorstellen genereren mislukt')
          } finally {
            if (!keepGenerating) {
              setGenerating(false)
            }
          }
          return
        }
        setThemes([])
        setDrafts({})
        showActionInfo('Thema-voorstellen verwijderd — genereer opnieuw.')
        return
      }

      if (themesToSubmit.length === 0) {
        setError('Markeer eerst een thema, of vul een eigen titel in.')
        return
      }

      if (!usingMock && !themesToSubmit[0].theme.id.startsWith('mock-')) {
        await persistExplicitDiscards()

        for (const { theme, draft } of themesToSubmit) {
          const persisted = await persistTheme(theme, draft)
          await submitTheme(persisted.id)
        }

        bumpAgendaRefresh()

        const submittedCount = themesToSubmit.length
        const stillNeeded = Math.max(0, quota - submittedCount)
        if (stillNeeded > 0) {
          showActionInfo(
            `${submittedCount} thema('s) ingediend — kies nog ${stillNeeded} thema('s) voor ${weekLabel}.`,
          )
        } else {
          const currentIndex = meta?.weekIndex ?? 0
          const next = meta?.weeks[currentIndex + 1]
          if (next?.weekKey) {
            showActionSuccess(`${weekLabel} compleet — agenda bijgewerkt.`)
            setSearchParams({ week: next.weekKey })
          } else {
            showActionSuccess(`${weekLabel} compleet — agenda bijgewerkt.`)
            setSearchParams({})
          }
        }
        await load()
      } else {
        for (const { theme } of themesToSubmit) {
          setThemes((prev) => prev.filter((t) => t.id !== theme.id))
        }
      }

      for (const { theme } of themesToSubmit) {
        setDrafts((prev) => {
          const next = { ...prev }
          delete next[theme.id]
          return next
        })
      }
      setCustomDraft(emptyCustomDraft())
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Indienen mislukt')
    } finally {
      setSubmitting(false)
    }
  }

  if (loading) {
    return (
      <GridShell
        pageTitle="Thema's"
        activeNav="article"
        breadcrumb={
          <NavBreadcrumb items={[...BREADCRUMB_PUBLICATION_ARTICLE, { label: "Thema's" }]} />
        }
        mainLayout="grid2"
        main={<p>Laden…</p>}
      />
    )
  }

  return (
    <GridShell
      pageTitle={
        <span className="block min-w-0" style={{ maxWidth: GRID2_REVIEW_MAIN_WIDTH }}>
          {weekLabel}
        </span>
      }
      pageTitleTone="section"
      activeNav="article"
      breadcrumb={
        <NavBreadcrumb items={[...BREADCRUMB_PUBLICATION_ARTICLE, { label: "Thema's" }]} />
      }
      mainLayout="grid2"
      headerContentGap={20}
      main={
        <div style={{ maxWidth: GRID2_INNER_WIDTH }}>
          {usingMock && (
            <p className="mb-0 text-[14px] leading-[18px] text-ce-beige">
              Mockdata — configureer DB + API voor live thema's.
            </p>
          )}

          {meta && quotaHint && !weekComplete && (
            <p className="m-0 text-[14px] leading-[18px] text-ce-black">{quotaHint}</p>
          )}

          {guardrailNotice ? (
            <p className="m-0 mb-[12px] text-[14px] leading-[18px] text-ce-black">{guardrailNotice}</p>
          ) : null}
          {positioningArchetype && !guardrailNotice ? (
            <p className="m-0 mb-[12px] text-[14px] leading-[18px] text-ce-beige">
              Past dit onderwerp bij je merk?
            </p>
          ) : null}
          {verdictError ? (
            <p className="m-0 mb-[12px] text-[14px] leading-[18px] text-ce-red">{verdictError}</p>
          ) : null}
          {verdictsLoading && visibleThemes.every((theme) => !verdicts[theme.id]) && !watOntbreekt ? (
            <div className="mt-[18px] mb-[18px]">
              <LoadingDots label="Positionering beoordelen…" className="text-ce-black" />
            </div>
          ) : null}
          {watOntbreekt ? (
            <div className={`${pulldownBoxClass} ${frameLineGapBeforeClass} mb-[18px] px-[30px] py-[16px]`}>
              <p className="m-0 mb-[12px] text-[14px] font-semibold leading-[18px] text-ce-black">
                Advies voor je agenda
              </p>
              <p className="m-0 text-[14px] font-normal leading-[18px] text-ce-black">{watOntbreekt}</p>
            </div>
          ) : null}

          {weekComplete && (
            <div className="flex flex-col items-start gap-[12px] pt-[8px]">
              {quotaHint && (
                <p className="m-0 text-[14px] leading-[18px] text-ce-beige">{quotaHint}</p>
              )}
              <ActionLink
                variant="statusRow"
                className="self-start text-left"
                onClick={() => navigate('/research')}
              >
                Ga naar Onderzoek →
              </ActionLink>
              {postsPerWeek <= 1 && (
                <p className="m-0 text-[14px] leading-[18px] text-ce-beige">
                  Meer posts per week? Pas het aan onder Instellingen → Planning.
                </p>
              )}
            </div>
          )}

          {meta && needsThemeGeneration && !quotaHint && visibleThemes.length === 0 && !awaitingThemePoll && (
            <p className="m-0 text-[14px] leading-[18px] text-ce-black">
              {weekLabel !== 'Thema'
                ? `Nog geen thema-voorstellen voor ${weekLabel} — klik op Genereer thema-voorstellen.`
                : 'Nog geen thema-voorstellen — klik op Genereer thema-voorstellen.'}
            </p>
          )}

          {showWeekNav && (
            <div className="flex items-baseline gap-[12px] text-[14px] leading-[18px]">
              {prevWeek ? (
                <ActionLink variant="statusRow" onClick={() => goToWeek(prevWeek.weekKey)}>
                  ← {prevWeek.weekLabel}
                </ActionLink>
              ) : (
                <span className="font-semibold text-ce-beige">← vorige week</span>
              )}
              {nextWeek ? (
                <ActionLink variant="statusRow" onClick={() => goToWeek(nextWeek.weekKey)}>
                  {nextWeek.weekLabel} →
                </ActionLink>
              ) : (
                <span className="font-semibold text-ce-beige">volgende week →</span>
              )}
            </div>
          )}

          {showProgressNotice || showTopSuccessNotice ? (
            <ActionNotice
              message={actionMessage}
              kind={actionKind}
              fading={actionFading}
              className="mb-0"
            />
          ) : null}
          {error && <p className="mt-[12px] mb-0 text-[14px] leading-[18px] text-ce-red">{error}</p>}

          {visibleThemes.length === 0 && !weekComplete && !awaitingThemePoll && (
            <p className="m-0 pt-[20px] text-[14px] leading-[18px] text-ce-black">
              {needsThemeGeneration
                ? weekLabel !== 'Thema'
                  ? `Nog geen thema-voorstellen voor ${weekLabel} — klik op Genereer thema-voorstellen, of vul een eigen titel in.`
                  : 'Nog geen thema-voorstellen — klik op Genereer thema-voorstellen, of vul een eigen titel in.'
                : meta && meta.weekCount === 0
                  ? "Geen open thema-weken — ingediende thema's staan in Onderzoek."
                  : "Geen open thema's — ingediende thema's staan in Onderzoek."}
            </p>
          )}

          {awaitingThemePoll && visibleThemes.length === 0 && (
            <div className="pt-[20px]">
              <LoadingDots label="Thema-voorstellen genereren" className="text-ce-black" />
            </div>
          )}

          {awaitingThemePoll && visibleThemes.length > 0 && visibleThemes.length < MAX_CANDIDATES && (
            <p className="m-0 pt-[12px] text-[14px] leading-[18px] text-ce-black">
              Nog meer voorstellen worden geladen…
            </p>
          )}

          {needsThemeGeneration && !usingMock && !weekComplete && visibleThemes.length === 0 && !awaitingThemePoll && (
            <div className="pt-[16px]">
              <PillButton
                variant="main"
                onClick={() => void handleGenerateThemes()}
                disabled={busy}
                loading={generating}
              >
                Genereer thema-voorstellen
              </PillButton>
            </div>
          )}

          {visibleThemes.map((theme, index) => {
            const draft = drafts[theme.id] ?? draftFromTheme(theme)
            const verdict = verdicts[theme.id]
            return (
              <div key={theme.id}>
              <ThemeReviewRow
                themeId={theme.id}
                index={index + 1}
                title={draft.title}
                motivation={draft.motivation}
                rationale={theme.rationale}
                sourceArticleUrl={draft.sourceArticleUrl}
                editorSuggested={draft.editorSuggested}
                editorOptions={theme.editorOptions}
                feedback={draft.feedback}
                busy={busy}
                verdictLabel={verdict?.label ?? null}
                verdictToelichting={themeVerdictToelichtingForDisplay(
                  verdict?.toelichting,
                  positioningArchetype,
                )}
                rhythmRoleLabel={theme.rhythmRoleLabel ?? null}
                onFeedbackChange={(feedback) => handleThemeFeedback(theme.id, feedback)}
                onEditorChange={(editor) => void handleThemeEditor(theme.id, editor)}
              />
              </div>
            )
          })}

          {visibleThemes.some((theme) => verdicts[theme.id]) ? (
            <p className="m-0 pt-[20px] text-[14px] leading-[18px] text-ce-beige">
              Je kiest zelf welk thema je pakt — dit is advies, geen verplichting.
            </p>
          ) : null}

          {canSubmitThemes && (
            <>
              <div className="border-t border-ce-beige pt-[18px]">
                <ThemeCustomCard
                  title={customDraft.title}
                  motivation={customDraft.motivation}
                  sourceArticleUrl={customDraft.sourceArticleUrl}
                  disabled={busy}
                  onTitleChange={(title) => setCustomDraft((prev) => ({ ...prev, title }))}
                  onMotivationChange={(motivation) =>
                    setCustomDraft((prev) => ({ ...prev, motivation }))
                  }
                  onSourceUrlChange={(sourceArticleUrl) =>
                    setCustomDraft((prev) => ({ ...prev, sourceArticleUrl }))
                  }
                />
              </div>

              <div className="mt-[18px]">
                {visibleThemes.length > 0 && (
                  <p className="m-0 mb-[12px] text-[14px] leading-[18px] text-ce-beige">
                    Niet tevreden met de voorstellen? Zet ze allemaal op verwijderen en klik op Indienen voor een
                    nieuwe scan.
                  </p>
                )}
                <PillButton
                  variant="main"
                  onClick={() => void handleSubmitTheme()}
                  disabled={busy}
                  loading={submitting}
                >
                  {themesStillRequired > 1 ? "Thema's indienen" : 'Thema indienen'}
                </PillButton>
                {showSubmitSuccessNotice ? (
                  <ActionNotice
                    message={actionMessage}
                    kind={actionKind}
                    fading={actionFading}
                    className="mb-0"
                  />
                ) : null}
              </div>
            </>
          )}

          <ThemeGenerationTips />
        </div>
      }
    />
  )
}

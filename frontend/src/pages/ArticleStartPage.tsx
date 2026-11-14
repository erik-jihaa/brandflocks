import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { Link, Navigate, useNavigate, useParams } from 'react-router-dom'
import { GridShell } from '../layout/GridShell'
import { NavBreadcrumb } from '../components/NavBreadcrumb'
import { Pulldown } from '../components/ui/Pulldown'
import { PillButton, pillActionsRowClass } from '../components/PillButton'
import { FieldSingle } from '../components/ui/Field'
import { borderedSectionClass, designStackClass, pageNavLinkClass } from '../design/identity.constants'
import {
  ARTICLE_START_EIGEN_NOTE,
  ARTICLE_START_FORK_NOTE_EEN_MEERDERE,
  ARTICLE_START_MODE_LABELS,
  ARTICLE_START_PAGE_LEAD,
  ARTICLE_START_STEPS,
  type ArticleStartMode,
} from '../articleStart/articleStartCopy'
import { useArticleStartDraft } from '../articleStart/useArticleStartDraft'
import { ArticleStartStepVragenFeiten } from '../articleStart/ArticleStartStepVragenFeiten'
import { ArticleStartStepKanalen } from '../articleStart/ArticleStartStepKanalen'
import { ArticleStartStepInvalshoeken } from '../articleStart/ArticleStartStepInvalshoeken'
import { ArticleStartStepPeriode } from '../articleStart/ArticleStartStepPeriode'
import { ArticleStartStepKlopt } from '../articleStart/ArticleStartStepKlopt'
import { ARTICLE_START_CHANNELS } from '../articleStart/articleStartMerk'
import { syncPlanItems, type ArticleStartPlanItem } from '../articleStart/articleStartPlan'
import { confirmArticleStartPlan } from '../articleStart/confirmArticleStartPlan'
import { isApiConfigured } from '../api/client'

const SECTION = borderedSectionClass

function parseStep(raw: string | undefined): number {
  const n = Number(raw)
  if (!Number.isFinite(n) || n < 1 || n > 7) return 1
  return Math.floor(n)
}

function ArticleStartStepBar({ step }: { step: number }) {
  const pct = Math.round((step / 7) * 1000) / 10
  const meta = ARTICLE_START_STEPS[step - 1]

  return (
    <div className="mb-[14px]">
      <div className="h-[6px] w-full overflow-hidden rounded-full bg-ce-beige">
        <div className="h-full bg-ce-blue transition-[width]" style={{ width: `${pct}%` }} />
      </div>
      <p className="m-0 mt-[8px] text-[14px] leading-[18px] text-ce-beige">
        Stap {step} van 7 — {meta.title}
      </p>
    </div>
  )
}

/** Weekplan stap 7 — artikel-wizard (klikmodel `p-artikelstart*`). */
export function ArticleStartPage() {
  const navigate = useNavigate()
  const { wizardStep } = useParams<{ wizardStep?: string }>()
  const step = wizardStep === undefined ? 1 : parseStep(wizardStep)
  const { draft, setDraft, resetDraft } = useArticleStartDraft()
  const [stepError, setStepError] = useState<string | null>(null)
  const [stepBusy, setStepBusy] = useState(false)
  const persistStep3Ref = useRef<(() => Promise<boolean>) | null>(null)

  const registerPersistStep3 = useCallback((fn: (() => Promise<boolean>) | null) => {
    persistStep3Ref.current = fn
  }, [])

  const modeOptions = useMemo(() => Object.values(ARTICLE_START_MODE_LABELS), [])
  const modeValue = draft.mode ? ARTICLE_START_MODE_LABELS[draft.mode] : ''
  const planItems = useMemo(() => syncPlanItems(draft), [draft])

  useEffect(() => {
    if (wizardStep === undefined) {
      navigate('/article/nieuw/stap/1', { replace: true })
    }
  }, [wizardStep, navigate])

  useEffect(() => {
    if (step < 5) return
    const synced = syncPlanItems(draft)
    const same =
      synced.length === draft.planItems.length &&
      synced.every(
        (item, i) =>
          item.id === draft.planItems[i]?.id &&
          item.angle === draft.planItems[i]?.angle &&
          item.publishDate === draft.planItems[i]?.publishDate,
      )
    if (!same) setDraft({ planItems: synced, articleCount: synced.length })
    // Alleen syncen bij stap/count/mode — niet bij elke draft-keystroke.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [step, draft.articleCount, draft.mode, draft.theme, draft.planItems.length, setDraft])

  if (wizardStep === undefined) {
    return null
  }

  if (step < 1 || step > 7) {
    return <Navigate to="/article/nieuw/stap/1" replace />
  }

  const copy = ARTICLE_START_STEPS[step - 1]
  const breadcrumb =
    step === 1
      ? [
          { label: 'Publicatie', to: '/agenda' },
          { label: 'Artikel' },
        ]
      : [
          { label: 'Publicatie', to: '/agenda' },
          { label: 'Artikel', to: '/article/nieuw/stap/1' },
        ]

  function goStep(next: number) {
    navigate(`/article/nieuw/stap/${next}`)
  }

  function onModeChange(label: string) {
    const entry = (Object.entries(ARTICLE_START_MODE_LABELS) as [ArticleStartMode, string][]).find(
      ([, text]) => text === label,
    )
    if (!entry) return
    const mode = entry[0]
    setDraft({
      mode,
      articleCount: mode === 'een' ? 1 : Math.max(2, draft.articleCount),
    })
  }

  function onChangePlanItem(id: string, patch: Partial<ArticleStartPlanItem>) {
    const next = planItems.map((item) => (item.id === id ? { ...item, ...patch } : item))
    setDraft({ planItems: next })
  }

  function canContinueStep1(): boolean {
    if (!draft.mode) return false
    if (draft.mode === 'eigen') return draft.ownText.trim().length > 20
    return true
  }

  function channelsSelected(): boolean {
    return ARTICLE_START_CHANNELS.some((c) => draft.channels[c.key])
  }

  function planAnglesOk(): boolean {
    return planItems.length > 0 && planItems.every((item) => item.angle.trim().length > 0)
  }

  function planDatesOk(): boolean {
    return planItems.length > 0 && planItems.every((item) => /^\d{4}-\d{2}-\d{2}$/.test(item.publishDate))
  }

  function canContinue(): boolean {
    if (step === 1) return canContinueStep1()
    if (step === 2) return draft.theme.trim().length > 0
    if (step === 3) return draft.merkOnderdelen.length > 0
    if (step === 4) return channelsSelected() && draft.articleCount >= 1
    if (step === 5) return planAnglesOk()
    if (step === 6) return planDatesOk()
    return true
  }

  function handleStep1Continue() {
    if (draft.mode === 'eigen') {
      navigate('/fastlane', { state: { prefillContent: draft.ownText } })
      return
    }
    if (draft.mode === 'een') {
      setDraft({ articleCount: 1 })
    }
    goStep(2)
  }

  async function handleVerder() {
    setStepError(null)
    if (step === 1) {
      handleStep1Continue()
      return
    }
    if (step === 2) {
      if (!draft.theme.trim()) return
      goStep(3)
      return
    }
    if (step === 3) {
      setStepBusy(true)
      const ok = persistStep3Ref.current ? await persistStep3Ref.current() : true
      setStepBusy(false)
      if (!ok) return
      goStep(4)
      return
    }
    if (step === 4) {
      const synced = syncPlanItems({
        ...draft,
        articleCount: draft.mode === 'een' ? 1 : draft.articleCount,
      })
      setDraft({ planItems: synced, articleCount: synced.length })
      goStep(5)
      return
    }
    if (step < 7) {
      goStep(step + 1)
    }
  }

  async function handleConfirm() {
    setStepError(null)
    if (!planAnglesOk() || !planDatesOk()) {
      setStepError('Vul invalshoeken en datums in (stap 5–6).')
      return
    }
    setStepBusy(true)
    try {
      const items = syncPlanItems(draft)
      const result = await confirmArticleStartPlan(draft, items)
      resetDraft()
      if (!isApiConfigured()) {
        navigate('/agenda', { state: { articleStartConfirmed: true, demo: true } })
        return
      }
      if (result.slotIds.length === 1) {
        navigate(`/research/${result.slotIds[0]}`, { state: { pollResearch: true } })
        return
      }
      navigate('/agenda', {
        state: {
          articleStartConfirmed: true,
          slotIds: result.slotIds,
        },
      })
    } catch (err) {
      setStepError(err instanceof Error ? err.message : 'Artikelen bevestigen mislukt')
    } finally {
      setStepBusy(false)
    }
  }

  return (
    <GridShell
      pageTitle="Artikel"
      activeNav="article"
      mainLayout="grid2"
      breadcrumb={<NavBreadcrumb items={breadcrumb} />}
      main={
        <div className={designStackClass}>
          <ArticleStartStepBar step={step} />
          <h1 className="m-0 text-[24px] leading-[28px] font-semibold text-ce-blue">
            {step === 1 ? 'Artikel' : copy.question}
          </h1>
          {step === 1 ? (
            <p className="m-0 text-[14px] leading-[21px] text-ce-black">{ARTICLE_START_PAGE_LEAD}</p>
          ) : null}

          {step === 1 && (
            <section className={SECTION}>
              <h2 className="m-0 text-[18px] leading-[22px] font-semibold text-ce-black">Hoeveel artikelen?</h2>
              <div className="mt-[12px]">
                <Pulldown
                  options={modeOptions}
                  value={modeValue}
                  placeholder="Selecteer"
                  onChange={onModeChange}
                />
              </div>
              {draft.mode === 'een' || draft.mode === 'meerdere' ? (
                <p className="m-0 mt-[10px] text-[14px] leading-[21px] text-ce-beige">
                  {ARTICLE_START_FORK_NOTE_EEN_MEERDERE}
                </p>
              ) : null}
              {draft.mode === 'eigen' ? (
                <div className="mt-[14px]">
                  <h2 className="m-0 text-[18px] leading-[22px] font-semibold text-ce-black">Je eigen tekst</h2>
                  <textarea
                    value={draft.ownText}
                    onChange={(e) => setDraft({ ownText: e.target.value })}
                    placeholder="Plak hier je artikeltekst…"
                    className="mt-[10px] min-h-[120px] w-full border border-ce-beige bg-ce-offwhite px-[12px] py-[10px] text-[14px] leading-[21px] text-ce-black outline-none"
                  />
                  <p className="m-0 mt-[8px] text-[14px] leading-[21px] text-ce-beige">{ARTICLE_START_EIGEN_NOTE}</p>
                </div>
              ) : null}
            </section>
          )}

          {step === 2 && (
            <section className={SECTION}>
              <h2 className="m-0 text-[18px] leading-[22px] font-semibold text-ce-black">Onderwerp</h2>
              <div className="mt-[12px] space-y-[12px]">
                <FieldSingle
                  id="article-start-theme"
                  variant="title"
                  showPencil
                  label="Thema"
                  value={draft.theme}
                  onChange={(v) => setDraft({ theme: v })}
                  placeholder="Waar gaat het over?"
                />
                <FieldSingle
                  id="article-start-extra"
                  showPencil
                  label="Extra gedachten"
                  value={draft.extraThoughts}
                  onChange={(v) => setDraft({ extraThoughts: v })}
                  placeholder="Wat moet er in elk geval in staan, of juist niet?"
                />
              </div>
              <p className="m-0 mt-[12px] text-[14px] leading-[21px] text-ce-beige">
                Vaste bronnen staan onder{' '}
                <Link to="/sources" className={pageNavLinkClass}>
                  Kennis → Bronnen
                </Link>
                .
              </p>
            </section>
          )}

          {step === 3 ? (
            <ArticleStartStepVragenFeiten
              draft={draft}
              setDraft={setDraft}
              registerPersist={registerPersistStep3}
              onError={setStepError}
            />
          ) : null}

          {step === 4 ? <ArticleStartStepKanalen draft={draft} setDraft={setDraft} /> : null}

          {step === 5 ? (
            <ArticleStartStepInvalshoeken
              draft={draft}
              planItems={planItems}
              onChangeItem={onChangePlanItem}
            />
          ) : null}

          {step === 6 ? (
            <ArticleStartStepPeriode planItems={planItems} onChangeItem={onChangePlanItem} />
          ) : null}

          {step === 7 ? <ArticleStartStepKlopt draft={draft} planItems={planItems} /> : null}

          {stepError ? (
            <p className="m-0 text-[14px] leading-[18px] text-ce-red" role="alert">
              {stepError}
            </p>
          ) : null}

          <div className={pillActionsRowClass}>
            {step < 7 ? (
              <PillButton
                variant="main"
                disabled={!canContinue() || stepBusy}
                loading={stepBusy}
                onClick={() => void handleVerder()}
              >
                Verder
              </PillButton>
            ) : (
              <PillButton
                variant="main"
                disabled={stepBusy || !planAnglesOk() || !planDatesOk()}
                loading={stepBusy}
                onClick={() => void handleConfirm()}
              >
                Artikelen bevestigen
              </PillButton>
            )}
            {step > 1 ? (
              <PillButton variant="second" onClick={() => goStep(step - 1)}>
                Terug
              </PillButton>
            ) : null}
            {step === 1 ? (
              <PillButton variant="second" onClick={() => resetDraft()}>
                Ongedaan maken
              </PillButton>
            ) : null}
          </div>
        </div>
      }
    />
  )
}

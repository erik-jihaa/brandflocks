import { useEffect, useState } from 'react'
import {
  acceptClaimEvidence,
  confirmGeenBronMerkBewering,
  searchClaimEvidence,
  softenClaim,
  substantiateClaim,
  type ClaimEvidenceResult,
} from '../api/articles'
import { createSource, fetchSourceInspiration } from '../api/sources'
import type { SourceInspirationSuggestion } from '../types/sources'
import { MetaSection } from './MetaSection'
import { PillButton } from './PillButton'
import { PinkChoiceFrame, PinkChoiceOption } from './ui/PinkChoice'

type SourceSelection = { itemKey: string; use: boolean; add: boolean }

export type ArticleSelfReviewDto = {
  weakestClaim: string
  unsupportedAssumption: string
  missedCounterpoint: string
  regelbreuk?: { regel: string; citaat: string } | null
  themeConflict?: boolean
  themeConflictResolution?: string
  themeConflictWhy?: string
  themePremisse?: boolean
  themePremisseWat?: string
  themePremisseActie?: string
  merkBeweringen?: {
    citaat: string
    bron: 'merkarchitectuur' | 'bewijsveld' | 'research' | 'geen_bron'
    klantBevestiging?: { type: string; waarde: string; bevestigdOp?: string }
  }[]
  /** CE-03 stap D mislukt — geen stille null meer (v1.9 stap 1). */
  ce03SelfReviewError?: string
}

type ArticleSelfReviewSectionProps = {
  selfReview: ArticleSelfReviewDto | null | undefined
  defaultOpen?: boolean
  slotId?: string
  content?: string
  onContentChange?: (
    content: string,
    options?: { incrementalCorrection?: boolean },
  ) => void
  readonly?: boolean
}

type PointKey = 'weakestClaim' | 'unsupportedAssumption' | 'missedCounterpoint'

type PanelState =
  | { kind: 'source'; items: SourceInspirationSuggestion[] }
  | { kind: 'evidence'; items: ClaimEvidenceResult[] }
  | { kind: 'notice'; message: string; appliedPassage?: string }

function regelbreukZin(regel: string): string {
  const bijzin = regel
    .trim()
    .replace(/^dat\s+/i, '')
    .replace(/[.\s]+$/, '')
  return `Dit stuk gaat in tegen je regel dat ${bijzin}.`
}

function hasContent(review: ArticleSelfReviewDto): boolean {
  return (
    Boolean(review.ce03SelfReviewError?.trim()) ||
    Boolean(review.regelbreuk?.regel.trim()) ||
    Boolean(review.themePremisse) ||
    Boolean(review.themePremisseWat?.trim()) ||
    Boolean(review.themePremisseActie?.trim()) ||
    (review.merkBeweringen?.length ?? 0) > 0 ||
    Boolean(review.themeConflictWhy?.trim()) ||
    review.weakestClaim.trim() !== '' ||
    review.unsupportedAssumption.trim() !== '' ||
    review.missedCounterpoint.trim() !== ''
  )
}

const POINT_LABELS: Record<PointKey, string> = {
  weakestClaim: 'Zwakste claim',
  unsupportedAssumption: 'Ongefundeerde aanname',
  missedCounterpoint: 'Gemist tegengeluid',
}

const BRON_LABELS: Record<string, string> = {
  merkarchitectuur: 'Merkarchitectuur',
  bewijsveld: 'Bewijsveld',
  research: 'Onderzoek',
  geen_bron: 'Geen bron',
}

function sourceItemKey(item: SourceInspirationSuggestion, index: number): string {
  return `source:${index}:${item.url || item.title}`
}

function evidenceItemKey(item: ClaimEvidenceResult, index: number): string {
  return `evidence:${index}:${item.url}`
}

/** CE-03 stap D — aandachtspunten met acties (briefing aandachtspunten-onderbouwing). */
export function ArticleSelfReviewSection({
  selfReview,
  defaultOpen = true,
  slotId,
  content = '',
  onContentChange,
  readonly = false,
}: ArticleSelfReviewSectionProps) {
  const [open, setOpen] = useState(defaultOpen)
  const [busyKey, setBusyKey] = useState<string | null>(null)
  const [panel, setPanel] = useState<{ pointKey: PointKey; state: PanelState } | null>(null)
  const [selectedSourceKey, setSelectedSourceKey] = useState<string | null>(null)
  const [sourceSelection, setSourceSelection] = useState<SourceSelection | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [review, setReview] = useState(selfReview)
  const [bronDraft, setBronDraft] = useState<Record<string, { type: string; waarde: string }>>({})

  useEffect(() => {
    setReview(selfReview)
  }, [selfReview])

  if (!review || !hasContent(review)) {
    return null
  }

  const points = (
    [
      { key: 'weakestClaim' as const, value: review.weakestClaim },
      { key: 'unsupportedAssumption' as const, value: review.unsupportedAssumption },
      { key: 'missedCounterpoint' as const, value: review.missedCounterpoint },
    ] as const
  ).filter((point) => point.value.trim() !== '')

  const actionsEnabled = Boolean(slotId && content.trim() && onContentChange && !readonly)

  function claimForPanel(): string {
    if (!panel) return ''
    const point = points.find((p) => p.key === panel.pointKey)
    return point?.value.trim() ?? ''
  }

  async function runAction(pointKey: PointKey, claim: string, action: 'source' | 'evidence' | 'soften') {
    if (!slotId || !onContentChange) return
    const busyId = `${pointKey}:${action}`
    setBusyKey(busyId)
    setError(null)
    setSelectedSourceKey(null)
    setSourceSelection(null)
    try {
      if (action === 'source') {
        const result = await fetchSourceInspiration(50, claim)
        setPanel({ pointKey, state: { kind: 'source', items: result.suggestions ?? [] } })
        return
      }
      if (action === 'evidence') {
        const result = await searchClaimEvidence(slotId, claim)
        setPanel({ pointKey, state: { kind: 'evidence', items: result.results ?? [] } })
        return
      }
      const result = await softenClaim(slotId, { claim, content })
      onContentChange(result.content, { incrementalCorrection: true })
      setPanel({
        pointKey,
        state: {
          kind: 'notice',
          message: 'Zin voorzichtiger geformuleerd. Nieuwe tekst staat blauw in het artikel.',
        },
      })
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Actie mislukt')
    } finally {
      setBusyKey(null)
    }
  }

  function toggleSourceAction(itemKey: string, action: 'use' | 'add', next: boolean) {
    setSourceSelection((prev) => {
      if (!prev || prev.itemKey !== itemKey) {
        return {
          itemKey,
          use: action === 'use' ? next : false,
          add: action === 'add' ? next : false,
        }
      }
      const updated = {
        ...prev,
        use: action === 'use' ? next : prev.use,
        add: action === 'add' ? next : prev.add,
      }
      if (!updated.use && !updated.add) return null
      return updated
    })
  }

  async function handleApplySelectedSource() {
    if (!slotId || !onContentChange || !panel) return

    if (panel.state.kind === 'source') {
      if (!sourceSelection || (!sourceSelection.use && !sourceSelection.add)) return
      const index = panel.state.items.findIndex(
        (item, i) => sourceItemKey(item, i) === sourceSelection.itemKey,
      )
      const item = index >= 0 ? panel.state.items[index] : null
      if (!item) return

      const wantUse = sourceSelection.use
      const wantAdd = sourceSelection.add
      if (wantUse) {
        const claim = claimForPanel()
        if (!claim) return
      }

      setBusyKey('apply-source')
      setError(null)
      try {
        let appliedPassage = ''
        const parts: string[] = []

        if (wantUse) {
          const claim = claimForPanel()
          const result = await substantiateClaim(slotId, {
            claim,
            content,
            sourceTitle: item.title,
            sourceUrl: item.url,
            sourceSummary: item.wat ?? '',
          })
          onContentChange(result.content, { incrementalCorrection: true })
          appliedPassage = (result.replacement ?? '').trim()
          parts.push('Bron verwerkt in de artikeltekst')
        }

        if (wantAdd) {
          await createSource({
            category: item.category || 'URL/RSS',
            title: item.title,
            url: item.url,
            description: item.wat || item.description || '',
            sourceTier: 'supplementary',
          })
          parts.push(
            wantUse
              ? 'en toegevoegd aan je bronnen'
              : `“${item.title}” is toegevoegd aan je bronnen`,
          )
        }

        const message = wantUse
          ? `${parts.join(' ')}. Nieuwe tekst staat blauw in het artikel — controleer en sla op.`
          : `${parts.join(' ')}. Je blijft op dit artikel — de bron staat klaar voor volgende research.`

        setPanel({
          pointKey: panel.pointKey,
          state: {
            kind: 'notice',
            message,
            appliedPassage: appliedPassage || undefined,
          },
        })
        setSourceSelection(null)
        if (wantUse) {
          window.requestAnimationFrame(() => {
            document.getElementById('content')?.scrollIntoView({ behavior: 'smooth', block: 'center' })
          })
        }
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Verwerken mislukt')
      } finally {
        setBusyKey(null)
      }
      return
    }

    if (!selectedSourceKey) return
    const claim = claimForPanel()
    if (!claim) return

    let sourceTitle = ''
    let sourceUrl = ''
    let sourceSummary = ''
    let evidenceItem: ClaimEvidenceResult | null = null

    if (panel.state.kind === 'evidence') {
      const index = panel.state.items.findIndex((item, i) => evidenceItemKey(item, i) === selectedSourceKey)
      const item = index >= 0 ? panel.state.items[index] : null
      if (!item) return
      evidenceItem = item
      sourceTitle = item.title
      sourceUrl = item.url
      sourceSummary = [item.quote, item.source].filter(Boolean).join('\n')
    } else {
      return
    }

    setBusyKey('apply-source')
    setError(null)
    try {
      const result = await substantiateClaim(slotId, {
        claim,
        content,
        sourceTitle,
        sourceUrl,
        sourceSummary,
      })
      onContentChange(result.content, { incrementalCorrection: true })

      if (evidenceItem) {
        try {
          await acceptClaimEvidence(slotId, evidenceItem)
        } catch {
          // Tekst is al verwerkt; research-toevoeging is secundair.
        }
      }

      const appliedPassage = (result.replacement ?? '').trim()
      setPanel({
        pointKey: panel.pointKey,
        state: {
          kind: 'notice',
          message: evidenceItem
            ? 'Onderbouwing verwerkt in de artikeltekst en toegevoegd aan research. Nieuwe tekst staat blauw in het artikel.'
            : 'Bron verwerkt in de artikeltekst. Nieuwe tekst staat blauw in het artikel — controleer en sla op.',
          appliedPassage: appliedPassage || undefined,
        },
      })
      setSelectedSourceKey(null)

      window.requestAnimationFrame(() => {
        document.getElementById('content')?.scrollIntoView({ behavior: 'smooth', block: 'center' })
      })
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Verwerken mislukt')
    } finally {
      setBusyKey(null)
    }
  }

  async function handleAcceptEvidence(item: ClaimEvidenceResult) {
    if (!slotId) return
    setBusyKey('accept-evidence')
    setError(null)
    try {
      await acceptClaimEvidence(slotId, item)
      setPanel({
        pointKey: panel?.pointKey ?? 'weakestClaim',
        state: {
          kind: 'notice',
          message:
            'Ad-hoc bron toegevoegd aan de research van dit artikel. Kies “Verwerk in artikel” als je de passage ook in de tekst wilt zetten.',
        },
      })
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Accepteren mislukt')
    } finally {
      setBusyKey(null)
    }
  }

  const selectedEvidenceItem =
    panel?.state.kind === 'evidence' && selectedSourceKey
      ? panel.state.items.find((item, i) => evidenceItemKey(item, i) === selectedSourceKey)
      : undefined

  const showSourceApply =
    panel &&
    (panel.state.kind === 'source' || panel.state.kind === 'evidence') &&
    panel.state.items.length > 0

  const sourceApplyReady =
    panel?.state.kind === 'source'
      ? Boolean(sourceSelection && (sourceSelection.use || sourceSelection.add))
      : Boolean(selectedSourceKey)

  return (
    <MetaSection label="Aandachtspunten" open={open} onToggle={() => setOpen((v) => !v)}>
      {error ? (
        <p className="m-0 mt-[8px] text-[14px] leading-[18px] text-ce-red">{error}</p>
      ) : null}
      <ul className="m-0 mt-[8px] list-none space-y-[16px] p-0">
        {review.ce03SelfReviewError?.trim() ? (
          <li>
            <p className="m-0 text-[14px] font-semibold leading-[18px] text-ce-red">Zelfcontrole mislukt</p>
            <p className="m-0 pt-[4px] text-[13px] leading-[17px] text-ce-black">
              {review.ce03SelfReviewError}
            </p>
          </li>
        ) : null}
        {review.regelbreuk?.regel.trim() ? (
          <li>
            <p className="m-0 text-[14px] font-semibold leading-[18px] text-ce-red">Tegen je eigen regel</p>
            <p className="m-0 pt-[4px] text-[14px] leading-[18px] text-ce-black">
              {regelbreukZin(review.regelbreuk.regel)}
            </p>
            {review.regelbreuk.citaat.trim() ? (
              <blockquote className="m-0 mt-[6px] border-l-2 border-ce-red pl-[10px] text-[13px] leading-[17px] text-ce-black">
                {review.regelbreuk.citaat}
              </blockquote>
            ) : null}
          </li>
        ) : null}
        {review.themePremisse || review.themePremisseWat?.trim() ? (
          <li>
            <p className="m-0 text-[14px] font-semibold leading-[18px] text-ce-black">
              Premisse van het thema
            </p>
            {review.themePremisseWat?.trim() ? (
              <p className="m-0 pt-[4px] text-[14px] leading-[18px] text-ce-black">
                {review.themePremisseWat}
              </p>
            ) : (
              <p className="m-0 pt-[4px] text-[14px] leading-[18px] text-ce-black">
                Dit thema neemt een resultaat of samenwerking aan die je merk niet heeft.
              </p>
            )}
            {review.themePremisseActie?.trim() ? (
              <p className="m-0 pt-[4px] text-[13px] leading-[17px] text-ce-black">
                {review.themePremisseActie}
              </p>
            ) : null}
          </li>
        ) : null}
        {review.merkBeweringen && review.merkBeweringen.length > 0 ? (
          <li>
            <p className="m-0 text-[14px] font-semibold leading-[18px] text-ce-black">
              Bron per merkbewering
            </p>
            <ul className="m-0 mt-[8px] list-none space-y-[10px] p-0">
              {review.merkBeweringen.map((item, index) => {
                const draftKey = `${index}:${item.citaat.slice(0, 40)}`
                const draft = bronDraft[draftKey] ?? { type: 'bewijsveld', waarde: '' }
                const confirmed = Boolean(item.klantBevestiging?.waarde?.trim())
                return (
                  <li key={`${item.bron}:${index}:${item.citaat.slice(0, 40)}`}>
                    <p
                      className={
                        item.bron === 'geen_bron' && !confirmed
                          ? 'm-0 text-[13px] font-semibold leading-[17px] text-ce-red'
                          : 'm-0 text-[13px] font-semibold leading-[17px] text-ce-black'
                      }
                    >
                      {item.bron === 'geen_bron' && confirmed
                        ? `Bevestigd (${item.klantBevestiging?.type ?? 'bron'})`
                        : (BRON_LABELS[item.bron] ?? item.bron)}
                    </p>
                    <blockquote className="m-0 mt-[4px] border-l-2 border-ce-beige pl-[10px] text-[13px] leading-[17px] text-ce-black">
                      {item.citaat}
                    </blockquote>
                    {item.bron === 'geen_bron' && confirmed && item.klantBevestiging ? (
                      <p className="m-0 mt-[6px] text-[13px] leading-[17px] text-ce-black">
                        {item.klantBevestiging.waarde}
                      </p>
                    ) : null}
                    {item.bron === 'geen_bron' && !confirmed && slotId && !readonly ? (
                      <div className="mt-[8px] flex flex-col gap-[8px]">
                        <select
                          className="w-full rounded-[20px] border border-ce-beige bg-white px-[16px] py-[8px] text-[13px]"
                          value={draft.type}
                          onChange={(e) =>
                            setBronDraft((prev) => ({
                              ...prev,
                              [draftKey]: { ...draft, type: e.target.value },
                            }))
                          }
                        >
                          <option value="bewijsveld">Bewijsveld</option>
                          <option value="merkarchitectuur">Merkarchitectuur</option>
                          <option value="url">URL</option>
                          <option value="document">Document</option>
                        </select>
                        <input
                          type="text"
                          className="w-full rounded-[20px] border border-ce-beige bg-white px-[16px] py-[8px] text-[13px]"
                          placeholder="Welke bron onderbouwt deze zin?"
                          value={draft.waarde}
                          onChange={(e) =>
                            setBronDraft((prev) => ({
                              ...prev,
                              [draftKey]: { ...draft, waarde: e.target.value },
                            }))
                          }
                        />
                        <PillButton
                          variant="second"
                          className="self-start !mb-0 h-[32px] px-[16px] text-[13px]"
                          disabled={busyKey !== null || draft.waarde.trim() === ''}
                          loading={busyKey === draftKey}
                          onClick={() => {
                            void (async () => {
                              setBusyKey(draftKey)
                              setError(null)
                              try {
                                const res = await confirmGeenBronMerkBewering(slotId, {
                                  citaat: item.citaat,
                                  bronType: draft.type,
                                  bronWaarde: draft.waarde.trim(),
                                })
                                if (res.selfReview && typeof res.selfReview === 'object') {
                                  setReview(res.selfReview as ArticleSelfReviewDto)
                                }
                              } catch (err) {
                                setError(err instanceof Error ? err.message : 'Bevestigen mislukt')
                              } finally {
                                setBusyKey(null)
                              }
                            })()
                          }}
                        >
                          Bron bevestigen
                        </PillButton>
                      </div>
                    ) : null}
                  </li>
                )
              })}
            </ul>
          </li>
        ) : null}
        {points.map((point) => (
          <li key={point.key}>
            <p className="m-0 text-[14px] font-semibold leading-[18px] text-ce-black">
              {POINT_LABELS[point.key]}
            </p>
            <p className="m-0 pt-[4px] text-[14px] leading-[18px] text-ce-black">{point.value}</p>
            {actionsEnabled ? (
              <div className="mt-[8px] flex flex-wrap gap-[8px]">
                <PillButton
                  variant="second"
                  className="!mb-0 h-[32px] px-[16px] text-[13px]"
                  disabled={busyKey !== null}
                  loading={busyKey === `${point.key}:source`}
                  onClick={() => void runAction(point.key, point.value, 'source')}
                >
                  Zoek een bron hiervoor
                </PillButton>
                <PillButton
                  variant="second"
                  className="!mb-0 h-[32px] px-[16px] text-[13px]"
                  disabled={busyKey !== null}
                  loading={busyKey === `${point.key}:evidence`}
                  onClick={() => void runAction(point.key, point.value, 'evidence')}
                >
                  Onderbouw deze claim
                </PillButton>
                <PillButton
                  variant="second"
                  className="!mb-0 h-[32px] px-[16px] text-[13px]"
                  disabled={busyKey !== null}
                  loading={busyKey === `${point.key}:soften`}
                  onClick={() => void runAction(point.key, point.value, 'soften')}
                >
                  Formuleer voorzichtiger
                </PillButton>
              </div>
            ) : null}
            {panel?.pointKey === point.key ? (
              <div className="mt-[10px]">
                {panel.state.kind === 'notice' ? (
                  <PinkChoiceFrame>
                    <p className="m-0 text-[13px] leading-[17px] text-ce-black">{panel.state.message}</p>
                    {panel.state.appliedPassage ? (
                      <blockquote className="m-0 border-l-2 border-ce-blue pl-[10px] text-[13px] leading-[17px] text-ce-blue">
                        {panel.state.appliedPassage}
                      </blockquote>
                    ) : null}
                  </PinkChoiceFrame>
                ) : null}
                {panel.state.kind === 'source' ? (
                  <PinkChoiceFrame intro="Vink één of beide opties aan — daarna Verwerk in artikel. Je blijft op deze pagina.">
                    {panel.state.items.map((item, index) => {
                      const key = sourceItemKey(item, index)
                      const selected = sourceSelection?.itemKey === key
                      return (
                        <PinkChoiceOption
                          key={key}
                          name={`self-review-source-${point.key}`}
                          value={key}
                          checked={selected}
                          selectedActions={
                            selected
                              ? [
                                  ...(sourceSelection.use ? ['use'] : []),
                                  ...(sourceSelection.add ? ['add'] : []),
                                ]
                              : []
                          }
                          onToggleAction={(itemKey, action, next) =>
                            toggleSourceAction(itemKey, action === 'add' ? 'add' : 'use', next)
                          }
                          actions={[
                            { value: 'use', label: 'gebruik dit artikel' },
                            { value: 'add', label: 'voeg toe aan bronnen' },
                          ]}
                        >
                          <span className="block font-semibold">{item.title}</span>
                          {item.wat ? <span className="mt-[4px] block">{item.wat}</span> : null}
                          {item.url ? (
                            <a
                              href={item.url}
                              className="mt-[4px] block break-all text-ce-blue underline"
                              target="_blank"
                              rel="noopener noreferrer"
                              onClick={(e) => e.stopPropagation()}
                            >
                              {item.url}
                            </a>
                          ) : null}
                        </PinkChoiceOption>
                      )
                    })}
                  </PinkChoiceFrame>
                ) : null}
                {panel.state.kind === 'evidence' ? (
                  <PinkChoiceFrame intro="Ad-hoc bronnen — kies wat past en verwerk het in de tekst, of voeg toe aan research.">
                    {panel.state.items.map((item, index) => {
                      const key = evidenceItemKey(item, index)
                      return (
                        <PinkChoiceOption
                          key={key}
                          name={`self-review-evidence-${point.key}`}
                          value={key}
                          checked={selectedSourceKey === key}
                          onChange={setSelectedSourceKey}
                          actionLabel="gebruik deze tekst"
                        >
                          <span className="block font-semibold">
                            {item.title}
                            {item.source ? (
                              <span className="font-normal text-ce-beige"> — {item.source}</span>
                            ) : null}
                          </span>
                          <blockquote className="m-0 mt-[6px] border-l-2 border-ce-blue pl-[10px]">
                            {item.quote}
                          </blockquote>
                        </PinkChoiceOption>
                      )
                    })}
                  </PinkChoiceFrame>
                ) : null}
                {showSourceApply && panel.pointKey === point.key ? (
                  <div className="mt-[10px] flex flex-wrap gap-[8px]">
                    <PillButton
                      variant="main"
                      className="!mb-0 h-[32px] px-[16px] text-[13px]"
                      disabled={busyKey !== null || !sourceApplyReady}
                      loading={busyKey === 'apply-source'}
                      onClick={() => void handleApplySelectedSource()}
                    >
                      Verwerk in artikel
                    </PillButton>
                    {selectedEvidenceItem ? (
                      <PillButton
                        variant="second"
                        className="!mb-0 h-[32px] px-[16px] text-[13px]"
                        disabled={busyKey !== null}
                        loading={busyKey === 'accept-evidence'}
                        onClick={() => void handleAcceptEvidence(selectedEvidenceItem)}
                      >
                        Toevoegen aan research
                      </PillButton>
                    ) : null}
                  </div>
                ) : null}
              </div>
            ) : null}
          </li>
        ))}
      </ul>
    </MetaSection>
  )
}

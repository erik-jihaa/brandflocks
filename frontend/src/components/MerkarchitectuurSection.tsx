import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { StrategyField, StrategySection, FieldSingle } from './ui/Field'
import { borderedSectionClass, borderedSectionStackClass, designStackClass } from '../design/identity.constants'
import { Pulldown } from './ui/Pulldown'
import { PillButton, pillActionsRowClass, pillActionsRowInnerClass } from './PillButton'
import { RichTextDisplay } from './RichTextDisplay'
import { IdentityCalibrationStep } from './IdentityCalibrationStep'
import { fetchStrategy, patchStrategy } from '../api/onboarding'
import { clearHomeDashboardCache } from '../api/home'
import { deriveHbaArchitecture, fetchHbaBranches, fetchHbaWizardDocumentStatus, uploadHbaWizardDocument, resolveHbaBranche, runHbaArchitectureCheck, type BrancheOption, type HbaWizardDocumentSuggestions, type HbaArchitectureCheckResult } from '../api/hbaV2'
import { apiFetch } from '../api/client'
import {
  brandNarrativeFromDto,
  brandNarrativeToPayload,
  emptyBrandNarrative,
  narrativeBewijsveldenForSave,
  narrativeMechanismeForSave,
  narrativeSpanningForSave,
  parseWordListInput,
  wordListToInput,
} from '../utils/brandNarrative'
import type { BrandNarrativeCompleteness, BrandNarrativeDto } from '../types/onboarding'
import { hbaNarrativeFieldLabel } from '../constants/hba'
import { HBA_ARCHETYPES, HBA_CONTEXT_PROFILES } from '../constants/hba'
import { buildHbaStrategistMailto } from '../utils/hbaStrategistReport'
import { usableHbaExample } from '../utils/hbaCalibrationExamples'
import { ActionNotice } from './ui/ActionNotice'
import { ACTION_NOTICE_VISIBLE_MS, useActionNotice } from '../hooks/useActionNotice'
import { HbaWizardDocumentUpload, type HbaWizardDocumentUploadOptions } from './HbaWizardDocumentUpload'
import {
  HbaArchitectureCheckPanel,
  type HbaVoorbeeldSuggestionPick,
} from './HbaArchitectureCheckPanel'

const STEPS = [
  { title: 'Wie jullie zijn en in welke markt' },
  { title: 'Wat jullie willen bereiken' },
  { title: 'Wat maakt jullie anders' },
  { title: 'Wat jullie nooit over jezelf willen lezen' },
  { title: 'Jullie regels' },
  { title: 'Jullie merkstem' },
  { title: 'Welke voelt als jullie' },
  { title: 'Bevestigen' },
] as const

type ExampleTriple = [string, string, string]

type WizardDraft = {
  identityIntro: string
  brancheCode: string
  brancheInput: string
  contextprofiel: string
  doel: string
  distinctiviteit: string
  watBewustNiet: string
  regels: string[]
  archetype: string
  foe: string
  toneOfBehaviour: string
  aanspreekvorm: 'je' | 'u' | ''
  goedeVoorbeelden: ExampleTriple
  zwakkeVoorbeelden: ExampleTriple
  kernformuleringen: string[]
  woordenDiePassenInput: string
  woordenDieNietPassenInput: string
}

function emptyExamples(): ExampleTriple {
  return ['', '', '']
}

const emptyDraft = (): WizardDraft => ({
  identityIntro: '',
  brancheCode: '',
  brancheInput: '',
  contextprofiel: 'groeiend',
  doel: '',
  distinctiviteit: '',
  watBewustNiet: '',
  regels: [''],
  archetype: '',
  foe: '',
  toneOfBehaviour: '',
  aanspreekvorm: '',
  goedeVoorbeelden: emptyExamples(),
  zwakkeVoorbeelden: emptyExamples(),
  kernformuleringen: ['', '', ''],
  woordenDiePassenInput: '',
  woordenDieNietPassenInput: '',
})

function examplesFromNarrative(good: string[], weak: string[]): { good: ExampleTriple; weak: ExampleTriple } {
  return {
    good: [usableHbaExample(good[0]), usableHbaExample(good[1]), usableHbaExample(good[2])],
    weak: [usableHbaExample(weak[0]), usableHbaExample(weak[1]), usableHbaExample(weak[2])],
  }
}

function examplesComplete(good: ExampleTriple, weak: ExampleTriple): boolean {
  return good.every((line) => line.trim() !== '') && weak.every((line) => line.trim() !== '')
}

function applyArchitectureCheckSelections(
  current: WizardDraft,
  toneSuggestion: string | null,
  voorbeeldPicks: HbaVoorbeeldSuggestionPick[],
): WizardDraft {
  const next: WizardDraft = {
    ...current,
    goedeVoorbeelden: [...current.goedeVoorbeelden],
    zwakkeVoorbeelden: [...current.zwakkeVoorbeelden],
  }

  const tone = toneSuggestion?.trim() ?? ''
  if (tone !== '') {
    next.toneOfBehaviour = next.toneOfBehaviour.trim()
      ? `${next.toneOfBehaviour.trim()}\n${tone}`
      : tone
  }

  const applyExample = (type: 'goed' | 'zwak', tekst: string) => {
    const key = type === 'goed' ? 'goedeVoorbeelden' : 'zwakkeVoorbeelden'
    const list = [...next[key]]
    const index = list.findIndex((line) => !line.trim())
    if (index >= 0) {
      list[index] = tekst
    } else if (list.length < 3) {
      list.push(tekst)
    }
    next[key] = list as ExampleTriple
  }

  for (const pick of voorbeeldPicks) {
    applyExample(pick.type, pick.tekst)
  }

  return next
}

function wizardDraftHasAnswers(draft: WizardDraft): boolean {
  if (
    draft.identityIntro.trim() ||
    draft.doel.trim() ||
    draft.distinctiviteit.trim() ||
    draft.watBewustNiet.trim() ||
    draft.archetype.trim() ||
    draft.foe.trim() ||
    draft.toneOfBehaviour.trim() ||
    draft.brancheCode.trim() ||
    draft.brancheInput.trim() ||
    draft.woordenDiePassenInput.trim() ||
    draft.woordenDieNietPassenInput.trim()
  ) {
    return true
  }
  if (draft.regels.some((rule) => rule.trim())) return true
  if (draft.kernformuleringen.some((line) => line.trim())) return true
  if (draft.goedeVoorbeelden.some((line) => line.trim()) || draft.zwakkeVoorbeelden.some((line) => line.trim())) {
    return true
  }
  return false
}

function contextprofielLabel(value: string): string {
  return HBA_CONTEXT_PROFILES.find((profile) => profile.value === value)?.label ?? ''
}

/**
 * Merkidentiteit mét HBA — gelicentieerd IP van Hero Brands, daarom één
 * afgebakende component. Twee toestanden: leeg → acht stappen, ingevuld → overzicht.
 */
export function MerkarchitectuurSection() {
  const [step, setStep] = useState(0)
  const [draft, setDraft] = useState<WizardDraft>(emptyDraft())
  const [narrative, setNarrative] = useState<BrandNarrativeDto>(emptyBrandNarrative())
  const [branches, setBranches] = useState<BrancheOption[]>([])
  const [deriving, setDeriving] = useState(false)
  const [deriveError, setDeriveError] = useState<string | null>(null)
  const [saving, setSaving] = useState(false)
  const [loading, setLoading] = useState(true)
  const [flowMode, setFlowMode] = useState<'wizard' | 'overview'>('wizard')
  const {
    message: notice,
    kind: noticeKind,
    fading: noticeFading,
    showSuccess: showNotice,
    clear: clearNotice,
  } = useActionNotice()
  const [draftSaveError, setDraftSaveError] = useState<string | null>(null)
  const [draftSaved, setDraftSaved] = useState(false)
  const [companyName, setCompanyName] = useState('')
  const [recalibrating, setRecalibrating] = useState(false)
  const [calibrationSessionKey, setCalibrationSessionKey] = useState(0)
  const [docSuggestions, setDocSuggestions] = useState<HbaWizardDocumentSuggestions | null>(null)
  const [docUploadBusy, setDocUploadBusy] = useState(false)
  const [docUploadError, setDocUploadError] = useState<string | null>(null)
  const [docUploadSkipped, setDocUploadSkipped] = useState(false)
  const [uploadedDocumentFilename, setUploadedDocumentFilename] = useState<string | null>(null)
  const [brancheResolving, setBrancheResolving] = useState(false)
  const [brancheResolveError, setBrancheResolveError] = useState<string | null>(null)
  const [architectureCheck, setArchitectureCheck] = useState<HbaArchitectureCheckResult | null>(null)
  const [architectureCheckLoading, setArchitectureCheckLoading] = useState(false)
  const [architectureCheckError, setArchitectureCheckError] = useState<string | null>(null)
  const [confirmToneSuggestion, setConfirmToneSuggestion] = useState<string | null>(null)
  const [confirmVoorbeeldPicks, setConfirmVoorbeeldPicks] = useState<HbaVoorbeeldSuggestionPick[]>([])
  const [narrativeCompleteness, setNarrativeCompleteness] = useState<BrandNarrativeCompleteness | null>(null)
  const scoringFieldsEnriched = useRef(false)

  const load = useCallback(async () => {
    setLoading(true)
    const [strategyRes, branchRes, docStatusRes] = await Promise.all([
      fetchStrategy(),
      fetchHbaBranches(),
      fetchHbaWizardDocumentStatus().catch(() => ({
        ok: false,
        hasUpload: false,
        filename: undefined,
        suggestions: undefined,
      })),
    ])
    setCompanyName(strategyRes.strategy?.companyName?.trim() ?? '')
    setNarrativeCompleteness(strategyRes.strategy?.brandNarrativeCompleteness ?? null)
    const bn = strategyRes.strategy?.brandNarrative
    const branchList = branchRes.branches ?? []
    setBranches(branchList)
    if (bn) {
      const mapped = brandNarrativeFromDto(bn)
      const examples = examplesFromNarrative(mapped.goedeVoorbeelden, mapped.zwakkeVoorbeelden)
      const brancheInput =
        branchList.find((branch) => branch.code === (bn.brancheCode ?? ''))?.label ??
        (bn.brancheCode ?? '')
      setNarrative(mapped)
      setDraft({
        identityIntro: bn.identityIntro ?? '',
        brancheCode: bn.brancheCode ?? '',
        brancheInput,
        contextprofiel: bn.contextprofiel?.trim() || 'groeiend',
        doel: mapped.doel,
        distinctiviteit: bn.distinctiviteit ?? '',
        watBewustNiet: bn.watBewustNiet ?? mapped.verbodenLogica?.[0] ?? '',
        regels: mapped.geloofwaardigheidstest
          ? mapped.geloofwaardigheidstest.split('\n').filter(Boolean)
          : [''],
        archetype: mapped.archetype,
        foe: mapped.foe,
        toneOfBehaviour: mapped.toneOfBehaviour,
        aanspreekvorm: mapped.aanspreekvorm === 'u' ? 'u' : mapped.aanspreekvorm === 'je' ? 'je' : '',
        goedeVoorbeelden: examples.good,
        zwakkeVoorbeelden: examples.weak,
        kernformuleringen: mapped.kernformuleringen ?? ['', '', ''],
        woordenDiePassenInput: wordListToInput(mapped.woordenDiePassen),
        woordenDieNietPassenInput: wordListToInput(mapped.woordenDieNietPassen),
      })
      const hasArchitecture = Boolean(mapped.archetype && mapped.foe && mapped.doel)
      const hasExamples = examples.good.every(Boolean) && examples.weak.every(Boolean)
      if (hasArchitecture && hasExamples) {
        setFlowMode('overview')
      } else {
        setFlowMode('wizard')
        setStep(hasArchitecture ? 6 : 0)
      }
    }
    if (docStatusRes.hasUpload) {
      setUploadedDocumentFilename(docStatusRes.filename?.trim() || null)
      if (docStatusRes.suggestions) {
        setDocSuggestions(docStatusRes.suggestions)
      }
    }
    setLoading(false)
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  useEffect(() => {
    if (loading || flowMode !== 'overview' || scoringFieldsEnriched.current) {
      return
    }
    const spanning = (narrative.spanning || '').trim()
    const foe = draft.foe.trim()
    const mechanisme = narrative.mechanisme || ''
    const needsEnrich =
      (spanning !== '' && spanning === foe) ||
      /Schrijf hieronder/i.test(mechanisme) ||
      ((narrative.bewijsvelden?.length ?? 0) === 1 &&
        (narrative.bewijsvelden?.[0] || '').trim() === mechanisme.trim())
    if (!needsEnrich) {
      return
    }
    scoringFieldsEnriched.current = true
    void persistDraft({ silent: true }).catch(() => {
      scoringFieldsEnriched.current = false
    })
  }, [loading, flowMode, narrative, draft.foe])

  useEffect(() => {
    if (docSuggestions) {
      applyDocumentRegelSuggestions(docSuggestions)
      applyDocumentKernSuggestions(docSuggestions)
    }
  }, [docSuggestions])

  function applyDocumentRegelSuggestions(suggestions: HbaWizardDocumentSuggestions) {
    const regelHints = suggestions.regels?.filter((rule): rule is string => Boolean(rule?.trim())) ?? []
    if (regelHints.length === 0) {
      return
    }
    setDraft((current) => {
      const nextCount = Math.max(current.regels.length, regelHints.length, 1)
      const nextRegels = Array.from({ length: nextCount }, (_, index) => current.regels[index] ?? '')
      return { ...current, regels: nextRegels }
    })
  }

  function applyDocumentKernSuggestions(suggestions: HbaWizardDocumentSuggestions) {
    const kernHints =
      suggestions.kernformuleringen?.filter((line): line is string => Boolean(line?.trim())) ?? []
    if (kernHints.length === 0) {
      return
    }
    setDraft((current) => {
      const nextCount = Math.max(current.kernformuleringen.length, kernHints.length, 3)
      const nextKern = Array.from({ length: nextCount }, (_, index) => current.kernformuleringen[index] ?? '')
      return { ...current, kernformuleringen: nextKern }
    })
  }

  function resetWizardAnswersForNewDocument() {
    setDraft(emptyDraft())
    setNarrative(emptyBrandNarrative())
    setStep(0)
    setFlowMode('wizard')
    setRecalibrating(false)
    setCalibrationSessionKey((key) => key + 1)
    setArchitectureCheck(null)
    setArchitectureCheckError(null)
    setDeriveError(null)
    setDraftSaved(false)
    setDraftSaveError(null)
    clearNotice()
  }

  async function handleWizardDocumentUpload(file: File, options: HbaWizardDocumentUploadOptions) {
    setDocUploadBusy(true)
    setDocUploadError(null)
    try {
      const result = await uploadHbaWizardDocument(file)
      if (!result.ok || !result.suggestions) {
        setDocUploadError('Suggesties ophalen mislukt.')
        return
      }
      if (options.clearPreviousAnswers) {
        resetWizardAnswersForNewDocument()
      }
      setDocSuggestions(result.suggestions)
      applyDocumentRegelSuggestions(result.suggestions)
      applyDocumentKernSuggestions(result.suggestions)
      setUploadedDocumentFilename(result.filename?.trim() || file.name)
      setDocUploadSkipped(false)
      showNotice(
        options.clearPreviousAnswers
          ? 'Strategiedocument bijgewerkt — antwoorden gewist, begin opnieuw met de suggesties.'
          : 'Strategiedocument bijgewerkt.',
      )
    } catch (err) {
      setDocUploadError(err instanceof Error ? err.message : 'Upload mislukt')
    } finally {
      setDocUploadBusy(false)
    }
  }

  function skipWizardDocumentUpload() {
    setDocUploadSkipped(true)
  }

  const showWizardDocumentUpload =
    step === 0 && !recalibrating && (!docUploadSkipped || Boolean(uploadedDocumentFilename))

  const hasExistingAnswers = useMemo(() => wizardDraftHasAnswers(draft), [draft])

  const brancheLabel = useMemo(() => draft.brancheInput.trim(), [draft.brancheInput])

  const selectedBranch = useMemo(
    () => branches.find((branch) => branch.code === draft.brancheCode) ?? null,
    [branches, draft.brancheCode],
  )

  function buildArchitectureCheckPayload(currentDraft: WizardDraft) {
    return {
      archetype: currentDraft.archetype,
      doel: currentDraft.doel,
      foe: currentDraft.foe,
      distinctiviteit: currentDraft.distinctiviteit,
      watBewustNiet: currentDraft.watBewustNiet,
      regels: currentDraft.regels.filter(Boolean),
      toneOfBehaviour: currentDraft.toneOfBehaviour,
      goedeVoorbeelden: currentDraft.goedeVoorbeelden,
      zwakkeVoorbeelden: currentDraft.zwakkeVoorbeelden,
      brancheCode: currentDraft.brancheCode,
    }
  }

  async function ensureBrancheResolved(currentDraft: WizardDraft): Promise<WizardDraft> {
    const input = currentDraft.brancheInput.trim()
    if (input === '') {
      return currentDraft
    }

    const exact = branches.find((branch) => branch.label.toLowerCase() === input.toLowerCase())
    if (exact) {
      return { ...currentDraft, brancheCode: exact.code, brancheInput: exact.label }
    }

    setBrancheResolving(true)
    setBrancheResolveError(null)
    try {
      const result = await resolveHbaBranche({
        brancheNaam: input,
        brancheCode: currentDraft.brancheCode || undefined,
      })
      if (!result.ok || !result.branch) {
        throw new Error(result.error ?? 'Branche kon niet worden afgeleid')
      }
      if (result.derived) {
        setBranches((prev) => {
          if (prev.some((branch) => branch.code === result.branch!.code)) {
            return prev
          }
          return [...prev, result.branch!].sort((a, b) => a.label.localeCompare(b.label, 'nl'))
        })
      }
      return {
        ...currentDraft,
        brancheCode: result.branch.code,
        brancheInput: result.branch.label,
      }
    } finally {
      setBrancheResolving(false)
    }
  }

  async function runArchitectureCheckForDraft(currentDraft: WizardDraft) {
    setArchitectureCheckLoading(true)
    setArchitectureCheckError(null)
    try {
      const result = await runHbaArchitectureCheck(buildArchitectureCheckPayload(currentDraft))
      if (!result.ok || !result.check) {
        throw new Error(result.error ?? 'Architectuur-check mislukt')
      }
      setArchitectureCheck(result.check)
    } catch (err) {
      setArchitectureCheck(null)
      setArchitectureCheckError(err instanceof Error ? err.message : 'Architectuur-check mislukt')
    } finally {
      setArchitectureCheckLoading(false)
    }
  }

  const calibrationArchitecture = useMemo(
    () => ({
      archetype: draft.archetype,
      doel: draft.doel,
      foe: draft.foe,
      distinctiviteit: draft.distinctiviteit,
      watBewustNiet: draft.watBewustNiet,
      regels: draft.regels.filter(Boolean).join('\n'),
    }),
    [draft.archetype, draft.doel, draft.foe, draft.distinctiviteit, draft.watBewustNiet, draft.regels],
  )

  const overviewExamplesDirty = useMemo(() => {
    const savedGood = narrative.goedeVoorbeelden ?? emptyExamples()
    const savedWeak = narrative.zwakkeVoorbeelden ?? emptyExamples()
    return (
      draft.goedeVoorbeelden.some((value, index) => value.trim() !== (savedGood[index] ?? '').trim()) ||
      draft.zwakkeVoorbeelden.some((value, index) => value.trim() !== (savedWeak[index] ?? '').trim())
    )
  }, [draft.goedeVoorbeelden, draft.zwakkeVoorbeelden, narrative.goedeVoorbeelden, narrative.zwakkeVoorbeelden])

  const overviewKernDirty = useMemo(() => {
    const saved = (narrative.kernformuleringen ?? []).map((s) => s.trim()).filter(Boolean)
    const current = draft.kernformuleringen.map((s) => s.trim()).filter(Boolean)
    if (saved.length !== current.length) {
      return true
    }
    return current.some((value, index) => value !== (saved[index] ?? ''))
  }, [draft.kernformuleringen, narrative.kernformuleringen])

  const overviewWordsDirty = useMemo(() => {
    const listsEqual = (saved: string[], currentInput: string) => {
      const current = parseWordListInput(currentInput)
      const a = saved.map((s) => s.trim()).filter(Boolean)
      if (a.length !== current.length) return true
      return current.some((value, index) => value !== (a[index] ?? ''))
    }
    return (
      listsEqual(narrative.woordenDiePassen ?? [], draft.woordenDiePassenInput) ||
      listsEqual(narrative.woordenDieNietPassen ?? [], draft.woordenDieNietPassenInput)
    )
  }, [
    draft.woordenDiePassenInput,
    draft.woordenDieNietPassenInput,
    narrative.woordenDiePassen,
    narrative.woordenDieNietPassen,
  ])

  const overviewDirty = overviewExamplesDirty || overviewKernDirty || overviewWordsDirty

  function buildNarrativePayload(currentDraft: WizardDraft): BrandNarrativeDto {
    const regels = currentDraft.regels.filter(Boolean)
    const kern = currentDraft.kernformuleringen.map((s) => s.trim()).filter(Boolean).slice(0, 5)
    const mechanisme = narrativeMechanismeForSave(
      currentDraft.distinctiviteit,
      narrative.mechanisme || currentDraft.distinctiviteit,
    )
    const spanning = narrativeSpanningForSave(
      currentDraft.doel,
      currentDraft.foe,
      narrative.spanning || '',
    )
    const bewijsvelden = narrativeBewijsveldenForSave(
      currentDraft.distinctiviteit,
      regels,
      kern,
      narrative.bewijsvelden ?? [],
      mechanisme,
    )

    return {
      ...narrative,
      archetype: currentDraft.archetype,
      doel: currentDraft.doel,
      foe: currentDraft.foe,
      distinctiviteit: currentDraft.distinctiviteit,
      watBewustNiet: currentDraft.watBewustNiet,
      brancheCode: currentDraft.brancheCode,
      identityIntro: currentDraft.identityIntro,
      contextprofiel: currentDraft.contextprofiel,
      geloofwaardigheidstest: regels.join('\n'),
      verbodenLogica: currentDraft.watBewustNiet ? [currentDraft.watBewustNiet] : [],
      toneOfBehaviour: currentDraft.toneOfBehaviour,
      aanspreekvorm: currentDraft.aanspreekvorm,
      goedeVoorbeelden: [...currentDraft.goedeVoorbeelden],
      zwakkeVoorbeelden: [...currentDraft.zwakkeVoorbeelden],
      woordenDiePassen: parseWordListInput(currentDraft.woordenDiePassenInput),
      woordenDieNietPassen: parseWordListInput(currentDraft.woordenDieNietPassenInput),
      kernformuleringen: kern,
      spanning,
      mechanisme,
      bewijsvelden,
    }
  }

  async function persistDraft(options?: {
    complete?: boolean
    silent?: boolean
    draftOverride?: WizardDraft
  }) {
    const payload = buildNarrativePayload(options?.draftOverride ?? draft)
    const result = await patchStrategy({ brandNarrative: brandNarrativeToPayload(payload) })
    if (result.strategy?.brandNarrative) {
      setNarrative(brandNarrativeFromDto(result.strategy.brandNarrative))
    }
    if (result.strategy?.brandNarrativeCompleteness) {
      setNarrativeCompleteness(result.strategy.brandNarrativeCompleteness)
    }
    if (options?.complete) {
      await apiFetch('/hba/architecture/complete', { method: 'POST' })
      clearHomeDashboardCache()
    }
    if (!options?.silent) {
      showNotice(
        options?.complete ? 'Merkarchitectuur opgeslagen.' : 'Voortgang opgeslagen.',
      )
    } else {
      setDraftSaved(true)
    }
  }

  async function runDerive(): Promise<Partial<WizardDraft> | null> {
    setDeriving(true)
    setDeriveError(null)
    const result = await deriveHbaArchitecture({
      identityIntro: draft.identityIntro,
      brancheCode: draft.brancheCode,
      brancheLabel,
      doel: draft.doel,
      distinctiviteit: draft.distinctiviteit,
      watBewustNiet: draft.watBewustNiet,
      regels: draft.regels.filter(Boolean),
    })
    setDeriving(false)
    if (!result.ok || !result.derivation) {
      setDeriveError(result.error ?? 'Afleiding mislukt')
      return null
    }
    const updates = {
      archetype: result.derivation?.archetype_voorstel ?? draft.archetype,
      foe: result.derivation?.foe_afgeleid ?? draft.foe,
      toneOfBehaviour: result.derivation?.tone_of_behaviour ?? draft.toneOfBehaviour,
    }
    setDraft((d) => ({ ...d, ...updates }))
    return updates
  }

  useEffect(() => {
    if (!draftSaved) return
    const timer = window.setTimeout(() => setDraftSaved(false), ACTION_NOTICE_VISIBLE_MS)
    return () => window.clearTimeout(timer)
  }, [draftSaved])

  async function saveArchitecture() {
    setSaving(true)
    clearNotice()
    setDraftSaveError(null)
    try {
      const draftToSave = applyArchitectureCheckSelections(
        draft,
        confirmToneSuggestion,
        confirmVoorbeeldPicks,
      )
      setDraft(draftToSave)
      await persistDraft({ complete: true, draftOverride: draftToSave })
      setFlowMode('overview')
      void load()
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Opslaan mislukt'
      setDraftSaveError(message)
    } finally {
      setSaving(false)
    }
  }

  async function saveOverviewExamples() {
    setSaving(true)
    clearNotice()
    setDraftSaveError(null)
    try {
      await persistDraft()
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Opslaan mislukt'
      setDraftSaveError(message)
    } finally {
      setSaving(false)
    }
  }

  useEffect(() => {
    if (step !== 7 || recalibrating) {
      return
    }
    setArchitectureCheck(null)
    setArchitectureCheckError(null)
    setConfirmToneSuggestion(null)
    setConfirmVoorbeeldPicks([])
    void runArchitectureCheckForDraft(draft)
  }, [step, recalibrating])

  useEffect(() => {
    setConfirmToneSuggestion(null)
    setConfirmVoorbeeldPicks([])
  }, [architectureCheck])

  async function advanceStep() {
    setDraftSaveError(null)
    setDraftSaved(false)
    try {
      let draftForSave = draft
      if (step === 0) {
        try {
          draftForSave = await ensureBrancheResolved(draftForSave)
          setDraft(draftForSave)
        } catch (err) {
          const message = err instanceof Error ? err.message : 'Branche kon niet worden afgeleid'
          setBrancheResolveError(message)
          setDraftSaveError(message)
          return
        }
      }
      if (step === 5 && !draft.archetype) {
        const updates = await runDerive()
        if (!updates) return
        draftForSave = { ...draft, ...updates }
      }
      await persistDraft({ silent: true, draftOverride: draftForSave })
      setStep((s) => s + 1)
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Opslaan mislukt'
      setDraftSaveError(message)
    }
  }

  function startRecalibration() {
    clearNotice()
    setDraftSaveError(null)
    setDraft((d) => ({
      ...d,
      goedeVoorbeelden: emptyExamples(),
      zwakkeVoorbeelden: emptyExamples(),
    }))
    setCalibrationSessionKey((key) => key + 1)
    setRecalibrating(true)
    setFlowMode('wizard')
    setStep(6)
  }

  function cancelRecalibration() {
    const examples = examplesFromNarrative(
      narrative.goedeVoorbeelden ?? [],
      narrative.zwakkeVoorbeelden ?? [],
    )
    setDraft((d) => ({
      ...d,
      goedeVoorbeelden: examples.good,
      zwakkeVoorbeelden: examples.weak,
    }))
    setRecalibrating(false)
    setFlowMode('overview')
  }

  async function finishRecalibration() {
    setSaving(true)
    clearNotice()
    setDraftSaveError(null)
    try {
      await persistDraft()
      setRecalibrating(false)
      setFlowMode('overview')
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Opslaan mislukt'
      setDraftSaveError(message)
    } finally {
      setSaving(false)
    }
  }

  function openStrategistMail() {
    window.location.href = buildHbaStrategistMailto({
      companyName,
      clientId: narrative.clientId,
      identityIntro: draft.identityIntro,
      brancheLabel,
      contextprofielLabel: contextprofielLabel(draft.contextprofiel),
      doel: draft.doel,
      distinctiviteit: draft.distinctiviteit,
      watBewustNiet: draft.watBewustNiet,
      regels: draft.regels.filter(Boolean),
      archetype: draft.archetype,
      foe: draft.foe,
      toneOfBehaviour: draft.toneOfBehaviour,
      goedeVoorbeelden: draft.goedeVoorbeelden,
      zwakkeVoorbeelden: draft.zwakkeVoorbeelden,
      woordenDiePassen: narrative.woordenDiePassen,
      woordenDieNietPassen: narrative.woordenDieNietPassen,
    })
  }

  if (loading) {
    return <p className="m-0 text-[14px] leading-[18px] text-ce-beige">Laden…</p>
  }

  const scoringReady = narrativeCompleteness?.complete ?? false
  const missingForScoring = narrativeCompleteness?.missing ?? []

  if (flowMode === 'overview') {
    return (
      <section className={`${designStackClass} ${borderedSectionClass}`}>
        {scoringReady ? (
          <p className="m-0 text-[14px] leading-[18px] text-ce-black">Jullie merkarchitectuur staat — merkcontrole op artikelen kan draaien.</p>
        ) : (
          <div className="rounded-[12px] bg-ce-pink px-[12px] py-[10px]">
            <p className="m-0 text-[14px] font-semibold leading-[18px] text-ce-red">
              Basis staat, maar scoring op artikelen kan nog niet.
            </p>
            {missingForScoring.length > 0 ? (
              <p className="m-0 pt-[6px] text-[14px] leading-[18px] text-ce-black">
                Ontbreekt nog: {missingForScoring.map(hbaNarrativeFieldLabel).join(', ')}.
              </p>
            ) : null}
            <p className="m-0 pt-[6px] text-[14px] leading-[18px] text-ce-black">
              Vul onderstaande secties aan (vooral{' '}
              <a href="#grenzen-taal" className="font-semibold text-ce-blue underline">
                Grenzen &amp; taal
              </a>
              ) en sla op.
            </p>
          </div>
        )}
        <RichTextDisplay
          text={`**Wie jullie zijn:** ${draft.identityIntro || '—'}\n\n**Wat jullie willen bereiken:** ${draft.doel}\n\n**Waar jullie tegen vechten:** ${draft.foe}\n\n**Wat jullie nooit over jezelf willen lezen:** ${draft.watBewustNiet}\n\n**Jullie regels:** ${draft.regels.filter(Boolean).join('; ')}`}
        />

        <StrategySection
          title="Mechanisme & bewijs"
          hint="Drie tot vijf zinnen die de kern van je mechanisme in gewone woorden zeggen. Deze komen bewust terug in je content, in steeds andere onderwerpen."
          hintTone="black"
          filled={draft.kernformuleringen.some((line) => line.trim() !== '')}
        >
          <div className={borderedSectionStackClass}>
            {draft.kernformuleringen.map((line, index) => (
              <StrategyField
                key={`kern-${index}`}
                id={`overview-kern-${index}`}
                title={index === 0 ? 'Hoe jullie werken' : `Hoe jullie werken (${index + 1})`}
                documentSuggestion={docSuggestions?.kernformuleringen?.[index] ?? undefined}
                value={line}
                onChange={(v) => {
                  const next = [...draft.kernformuleringen]
                  next[index] = v
                  setDraft((d) => ({ ...d, kernformuleringen: next }))
                }}
                minHeight={60}
              />
            ))}
          </div>
          {draft.kernformuleringen.length < 5 ? (
            <button
              type="button"
              className="self-start border-0 bg-transparent text-[14px] font-semibold text-ce-blue underline"
              onClick={() =>
                setDraft((d) => ({
                  ...d,
                  kernformuleringen: [...d.kernformuleringen, ''],
                }))
              }
            >
              + Voeg formulering toe
            </button>
          ) : null}
        </StrategySection>

        <StrategySection
          id="grenzen-taal"
          title="Grenzen & taal"
          hint="Minimaal drie woorden of korte zinnen per lijst, gescheiden door komma's. Zonder deze lijsten kan merkcontrole op artikelen niet draaien."
          hintTone="black"
          filled={
            parseWordListInput(draft.woordenDiePassenInput).length >= 3 &&
            parseWordListInput(draft.woordenDieNietPassenInput).length >= 3
          }
        >
          <div className={borderedSectionStackClass}>
            <StrategyField
              id="overview-woorden-passen"
              title="Woorden die passen"
              hint="Minimaal drie items. Scheid woorden met komma's."
              value={draft.woordenDiePassenInput}
              onChange={(v) => setDraft((d) => ({ ...d, woordenDiePassenInput: v }))}
              minHeight={72}
              placeholder="backcasting, uitvoerbaar, bouwen, onvermijdelijk"
            />
            <StrategyField
              id="overview-woorden-niet-passen"
              title="Woorden die niet passen"
              hint="Minimaal drie items. Scheid woorden met komma's."
              value={draft.woordenDieNietPassenInput}
              onChange={(v) => setDraft((d) => ({ ...d, woordenDieNietPassenInput: v }))}
              minHeight={72}
              placeholder="rapport, theoretisch, synergy, best practice"
            />
          </div>
        </StrategySection>

        <StrategySection
          title="Kalibratie-voorbeelden"
          hint="Deze zinnen kalibreren de merkmeting — pas ze aan als iets niet klopt."
          hintTone="black"
        >
          <div className="flex flex-wrap items-start gap-[12px] [&_button]:mb-0">
            <PillButton type="button" variant="second" onClick={startRecalibration}>
              Opnieuw kalibreren
            </PillButton>
          </div>
        </StrategySection>

        <div className={borderedSectionStackClass}>
          {[0, 1, 2].flatMap((index) => [
            <StrategyField
              key={`overview-good-${index}`}
              id={`overview-good-${index}`}
              title={`Sterk voorbeeld ${index + 1}`}
              value={draft.goedeVoorbeelden[index] ?? ''}
              onChange={(v) => {
                const next = [...draft.goedeVoorbeelden] as ExampleTriple
                next[index] = v
                setDraft((d) => ({ ...d, goedeVoorbeelden: next }))
              }}
              minHeight={60}
            />,
            <StrategyField
              key={`overview-weak-${index}`}
              id={`overview-weak-${index}`}
              title={`Zwak voorbeeld ${index + 1}`}
              value={draft.zwakkeVoorbeelden[index] ?? ''}
              onChange={(v) => {
                const next = [...draft.zwakkeVoorbeelden] as ExampleTriple
                next[index] = v
                setDraft((d) => ({ ...d, zwakkeVoorbeelden: next }))
              }}
              minHeight={60}
            />,
          ])}
        </div>

        <HbaWizardDocumentUpload
          busy={docUploadBusy}
          error={docUploadError}
          currentFilename={uploadedDocumentFilename}
          hasExistingAnswers={hasExistingAnswers}
          mode="maintenance"
          onUpload={(file, options) => void handleWizardDocumentUpload(file, options)}
        />

        <ActionNotice message={notice} kind={noticeKind} fading={noticeFading} className="m-0" />
        {draftSaveError ? <p className="m-0 text-[14px] text-ce-red">{draftSaveError}</p> : null}

        <div className={`${pillActionsRowClass} flex-nowrap`}>
          <PillButton
            type="button"
            className="shrink-0"
            disabled={saving || !overviewDirty}
            loading={saving}
            onClick={() => void saveOverviewExamples()}
          >
            Opslaan
          </PillButton>
          <PillButton
            type="button"
            variant="second"
            className="shrink-0"
            onClick={() => {
              setFlowMode('wizard')
              setStep(0)
            }}
          >
            Opnieuw invullen
          </PillButton>
          <PillButton
            type="button"
            variant="second"
            className="shrink-0"
            onClick={() => {
              openStrategistMail()
            }}
          >
            laat een strateeg meekijken
          </PillButton>
        </div>
        <p className="m-0 text-[12px] leading-[16px] text-ce-beige">
          We nemen binnen één werkdag contact op.
        </p>
      </section>
    )
  }

  const current = STEPS[step]
  const examplesReady = examplesComplete(draft.goedeVoorbeelden, draft.zwakkeVoorbeelden)

  return (
    <section className={`${designStackClass} ${borderedSectionClass}`}>
      <p className="m-0 text-[14px] leading-[18px] font-semibold text-ce-black">
        {recalibrating
          ? 'Kalibratie opnieuw — kies drie paren'
          : `Stap ${step + 1} van ${STEPS.length} — ${current.title}`}
      </p>

      {step === 0 && (
        <div className={borderedSectionStackClass}>
          {showWizardDocumentUpload ? (
            <HbaWizardDocumentUpload
              busy={docUploadBusy}
              error={docUploadError}
              currentFilename={uploadedDocumentFilename}
              hasExistingAnswers={hasExistingAnswers}
              onUpload={(file, options) => void handleWizardDocumentUpload(file, options)}
              onSkip={uploadedDocumentFilename ? undefined : skipWizardDocumentUpload}
            />
          ) : docUploadSkipped && !uploadedDocumentFilename ? (
            <StrategySection title="Strategiedocument (optioneel)" hint="Geen strategiedocument geüpload.">
              <div className={pillActionsRowInnerClass}>
                <PillButton type="button" variant="second" onClick={() => setDocUploadSkipped(false)}>
                  Strategiedocument uploaden
                </PillButton>
              </div>
            </StrategySection>
          ) : null}
          <StrategyField
              id="identity-intro"
              title="Wie zijn jullie en in welke markt opereren jullie?"
              documentSuggestion={docSuggestions?.identityIntro ?? undefined}
              value={draft.identityIntro}
              onChange={(v) => setDraft((d) => ({ ...d, identityIntro: v }))}
              minHeight={100}
            />
            <StrategySection title="In welke markt" filled={Boolean(draft.brancheInput.trim())}>
              <div className="grid gap-[18px]">
                <Pulldown
                  placeholder="— kies uit lijst —"
                  value={
                    branches.find((branch) => branch.code === draft.brancheCode)?.label ?? ''
                  }
                  options={branches.map((branch) => branch.label)}
                  onChange={(label) => {
                    setBrancheResolveError(null)
                    const match = branches.find((branch) => branch.label === label)
                    setDraft((current) => ({
                      ...current,
                      brancheInput: label,
                      brancheCode: match?.code ?? '',
                    }))
                  }}
                />
                <FieldSingle
                  id="hba-branche"
                  value={
                    branches.some((branch) => branch.code === draft.brancheCode)
                      ? ''
                      : draft.brancheInput
                  }
                  placeholder="Of typ je eigen branche"
                  onChange={(value) => {
                    setBrancheResolveError(null)
                    const match = branches.find(
                      (branch) => branch.label.toLowerCase() === value.trim().toLowerCase(),
                    )
                    setDraft((current) => ({
                      ...current,
                      brancheInput: value,
                      brancheCode: match?.code ?? '',
                    }))
                  }}
                />
              </div>
              {brancheResolving ? (
                <p className="m-0 mt-[8px] text-[14px] leading-[18px] text-ce-black">Branche afleiden…</p>
              ) : null}
              {brancheResolveError ? (
                <p className="m-0 mt-[8px] text-[14px] leading-[18px] text-ce-red" role="alert">
                  {brancheResolveError}
                </p>
              ) : null}
              {selectedBranch?.bron === 'afgeleid' ? (
                <p className="m-0 mt-[8px] text-[14px] leading-[18px] text-ce-beige">
                  Deze branche is automatisch afgeleid; een strateeg kan de definitie nog aanscherpen.
                </p>
              ) : null}
            </StrategySection>
            <StrategySection
              title="Waar staat jullie merk nu?"
              filled={Boolean(draft.contextprofiel)}
            >
              <Pulldown
                value={contextprofielLabel(draft.contextprofiel)}
                options={HBA_CONTEXT_PROFILES.map((profile) => profile.label)}
                onChange={(label) => {
                  const match = HBA_CONTEXT_PROFILES.find((profile) => profile.label === label)
                  setDraft((d) => ({ ...d, contextprofiel: match?.value ?? d.contextprofiel }))
                }}
              />
            </StrategySection>
        </div>
      )}

      {step === 1 && (
        <StrategyField
          id="hba-doel"
          title="Wat willen jullie bereiken?"
          hint="Concreet waar jullie voor gaan — geen mooie missiezin nodig."
          documentSuggestion={docSuggestions?.doel ?? undefined}
          value={draft.doel}
          onChange={(v) => setDraft((d) => ({ ...d, doel: v }))}
          minHeight={120}
        />
      )}

      {step === 2 && (
        <StrategyField
          id="hba-distinct"
          title="Wat maakt jullie anders dan de rest?"
          hint="Schrijf het zoals je het aan een vriend zou vertellen."
          documentSuggestion={docSuggestions?.distinctiviteit ?? undefined}
          value={draft.distinctiviteit}
          onChange={(v) => setDraft((d) => ({ ...d, distinctiviteit: v }))}
          minHeight={120}
        />
      )}

      {step === 3 && (
        <StrategyField
          id="hba-niet"
          title="Waar geloven jullie niet in, ook al doet de hele markt het?"
          documentSuggestion={docSuggestions?.watBewustNiet ?? undefined}
          value={draft.watBewustNiet}
          onChange={(v) => setDraft((d) => ({ ...d, watBewustNiet: v }))}
          minHeight={120}
        />
      )}

      {step === 4 && (
        <div className="grid gap-[12px]">
          <p className="m-0 text-[14px] leading-[18px] font-semibold text-ce-black">
            Waar zouden jullie nee tegen zeggen, ook als het geld zou opleveren?
          </p>
          <div className={borderedSectionStackClass}>
            {draft.regels.map((rule, index) => (
            <StrategyField
              key={index}
              id={`rule-${index}`}
              title={`Regel ${index + 1}`}
              documentSuggestion={docSuggestions?.regels?.[index] ?? undefined}
              value={rule}
              onChange={(v) => {
                const next = [...draft.regels]
                next[index] = v
                setDraft((d) => ({ ...d, regels: next }))
              }}
              minHeight={60}
            />
            ))}
          </div>
          {draft.regels.length < 5 ? (
            <button
              type="button"
              className="self-start border-0 bg-transparent text-[14px] font-semibold text-ce-blue underline"
              onClick={() => setDraft((d) => ({ ...d, regels: [...d.regels, ''] }))}
            >
              + Voeg regel toe
            </button>
          ) : null}
        </div>
      )}

      {step === 5 && (
        <div className="grid gap-[12px]">
          {deriving ? <p className="m-0 text-[14px] text-ce-black">We stellen iets voor…</p> : null}
          {deriveError ? <p className="m-0 text-[14px] text-ce-red">{deriveError}</p> : null}
          {!draft.archetype && !deriving ? (
            <PillButton type="button" onClick={() => void runDerive()}>
              Stel persoonlijkheid voor
            </PillButton>
          ) : null}
          {draft.archetype ? (
            <>
              <div className={borderedSectionStackClass}>
                <StrategyField
                  id="hba-foe"
                  title="Waar jullie tegen vechten"
                  value={draft.foe}
                  onChange={(v) => setDraft((d) => ({ ...d, foe: v }))}
                  minHeight={80}
                />
                <StrategyField
                  id="hba-tone"
                  title="Jullie toon"
                  value={draft.toneOfBehaviour}
                  onChange={(v) => setDraft((d) => ({ ...d, toneOfBehaviour: v }))}
                  minHeight={80}
                />
                <StrategySection title="Aanspreekvorm" filled={Boolean(draft.aanspreekvorm)}>
                  <Pulldown
                    value={draft.aanspreekvorm === 'u' ? 'u' : draft.aanspreekvorm === 'je' ? 'je' : 'je'}
                    options={['je', 'u']}
                    onChange={(value) =>
                      setDraft((d) => ({ ...d, aanspreekvorm: value === 'u' ? 'u' : 'je' }))
                    }
                  />
                </StrategySection>
                <StrategySection title="Andere persoonlijkheid?" filled={Boolean(draft.archetype)}>
                  <Pulldown
                    value={draft.archetype}
                    options={[...HBA_ARCHETYPES]}
                    onChange={(value) => setDraft((d) => ({ ...d, archetype: value }))}
                  />
                </StrategySection>
              </div>
            </>
          ) : null}
        </div>
      )}

      {step === 6 && (
        <IdentityCalibrationStep
          key={calibrationSessionKey}
          goedeVoorbeelden={draft.goedeVoorbeelden}
          zwakkeVoorbeelden={draft.zwakkeVoorbeelden}
          architecture={calibrationArchitecture}
          onChange={(good, weak) =>
            setDraft((d) => ({ ...d, goedeVoorbeelden: good, zwakkeVoorbeelden: weak }))
          }
        />
      )}

      {step === 7 && (
        <div className="grid w-full gap-[12px]">
          <RichTextDisplay
            className="w-full"
            text={`**Wie jullie zijn:** ${draft.identityIntro || '—'}\n\n**Wat jullie willen bereiken:** ${draft.doel}\n\n**Waar jullie tegen vechten:** ${draft.foe}`}
          />
          <HbaArchitectureCheckPanel
            check={architectureCheck}
            loading={architectureCheckLoading}
            error={architectureCheckError}
            onRetry={() => void runArchitectureCheckForDraft(draft)}
            onAanscherpen={(targetStep) => setStep(targetStep)}
            onStrategist={openStrategistMail}
            selectedToneSuggestion={confirmToneSuggestion}
            onSelectToneSuggestion={setConfirmToneSuggestion}
            selectedVoorbeeldPicks={confirmVoorbeeldPicks}
            onToggleVoorbeeldPick={(pick, selected) => {
              setConfirmVoorbeeldPicks((current) => {
                if (!selected) {
                  return current.filter(
                    (item) => !(item.type === pick.type && item.tekst === pick.tekst),
                  )
                }
                if (current.some((item) => item.type === pick.type && item.tekst === pick.tekst)) {
                  return current
                }
                return [...current, pick]
              })
            }}
          />
          {draftSaveError ? (
            <p className="m-0 w-full text-[14px] text-ce-red">{draftSaveError}</p>
          ) : null}
          <div className={pillActionsRowClass}>
            <PillButton
              type="button"
              disabled={saving || !examplesReady}
              loading={saving}
              onClick={() => void saveArchitecture()}
            >
              Vastleggen en beginnen
            </PillButton>
            <PillButton
              type="button"
              variant="second"
              className="mb-0 shrink-0"
              onClick={() => {
                openStrategistMail()
              }}
            >
              laat een strateeg meekijken
            </PillButton>
          </div>
        </div>
      )}

      <div className={pillActionsRowClass}>
        {draftSaveError && (recalibrating || step < STEPS.length - 1) ? (
          <p className="m-0 w-full basis-full text-[14px] text-ce-red">{draftSaveError}</p>
        ) : null}
        {draftSaved && !recalibrating && step < STEPS.length - 1 ? (
          <p className="m-0 w-full basis-full text-[14px] text-ce-green-text">Voortgang opgeslagen</p>
        ) : null}
        {recalibrating && step === 6 ? (
          <>
            <PillButton type="button" variant="second" onClick={cancelRecalibration}>
              Annuleren
            </PillButton>
            <PillButton
              type="button"
              disabled={!examplesReady || saving}
              loading={saving}
              onClick={() => void finishRecalibration()}
            >
              Opslaan
            </PillButton>
          </>
        ) : step < STEPS.length - 1 ? (
          <>
            {step > 0 ? (
              <PillButton type="button" variant="second" onClick={() => setStep((s) => s - 1)}>
                Vorige
              </PillButton>
            ) : null}
            <PillButton
              type="button"
              disabled={step === 6 && !examplesReady}
              onClick={() => void advanceStep()}
            >
              Volgende
            </PillButton>
          </>
        ) : null}
      </div>
    </section>
  )
}

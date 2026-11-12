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
  { title: 'Wie je bent en in welke markt' },
  { title: 'Wat je wilt bereiken' },
  { title: 'Wat je anders maakt' },
  { title: 'Wat je nooit over jezelf wilt lezen' },
  { title: 'Je regels' },
  { title: 'Je merkstem' },
  { title: 'Wat voelt als jouw merk' },
  { title: 'Bevestigen' },
] as const

const WIZARD_ENTRY_STORAGE_KEY = 'ce-merkarchitectuur-wizard-entry'

type WizardEntryMode = 'structuur' | 'vragen'

type OverviewEditPanel = 'wie' | 'hoe' | 'taal' | 'voorbeelden'

type StructuurWizardPhase = 'stappen' | 'bevestigen'

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

function readWizardEntryMode(): WizardEntryMode {
  try {
    const raw = localStorage.getItem(WIZARD_ENTRY_STORAGE_KEY)
    if (raw === 'vragen' || raw === 'structuur') return raw
  } catch {
    /* ignore */
  }
  return 'structuur'
}

function writeWizardEntryMode(mode: WizardEntryMode): void {
  try {
    localStorage.setItem(WIZARD_ENTRY_STORAGE_KEY, mode)
  } catch {
    /* ignore */
  }
}

function cloneWizardDraft(source: WizardDraft): WizardDraft {
  return {
    ...source,
    regels: [...source.regels],
    goedeVoorbeelden: [...source.goedeVoorbeelden] as ExampleTriple,
    zwakkeVoorbeelden: [...source.zwakkeVoorbeelden] as ExampleTriple,
    kernformuleringen: [...source.kernformuleringen],
  }
}

const overviewDimClass = 'opacity-45 pointer-events-none transition-opacity'

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

  const [wizardEntryMode, setWizardEntryMode] = useState<WizardEntryMode>(() => readWizardEntryMode())
  const [structuurWizardPhase, setStructuurWizardPhase] = useState<StructuurWizardPhase>('stappen')
  const [overviewEditing, setOverviewEditing] = useState<OverviewEditPanel | null>(null)
  const [overviewEditBuffer, setOverviewEditBuffer] = useState<WizardDraft | null>(null)
  const structuurScrollRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    writeWizardEntryMode(wizardEntryMode)
  }, [wizardEntryMode])

  const showStructuurScroll =
    flowMode === 'wizard' &&
    wizardEntryMode === 'structuur' &&
    structuurWizardPhase === 'stappen' &&
    !recalibrating

  useEffect(() => {
    if (showStructuurScroll && structuurScrollRef.current) {
      structuurScrollRef.current.scrollTop = 0
    }
  }, [showStructuurScroll])

  const overviewPanelDimmed = useCallback(
    (panel: OverviewEditPanel) => overviewEditing !== null && overviewEditing !== panel,
    [overviewEditing],
  )

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
    if (loading || flowMode !== 'overview') {
      return
    }
    void runArchitectureCheckForDraft(draft)
    // Alleen bij binnenkomst overzicht — niet bij elke wijziging; Opslaan haalt de check opnieuw op.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [loading, flowMode])

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

  /** Zelfde als ensureBrancheResolved, maar zonder laadstatus — voor stille saves (overzicht). */
  async function resolveBrancheQuiet(currentDraft: WizardDraft): Promise<WizardDraft> {
    const input = currentDraft.brancheInput.trim()
    if (input === '') {
      return currentDraft
    }
    const exact = branches.find((branch) => branch.label.toLowerCase() === input.toLowerCase())
    if (exact) {
      return { ...currentDraft, brancheCode: exact.code, brancheInput: exact.label }
    }
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

  async function fetchArchitectureDerivation(
    source: WizardDraft,
    brancheLabelForApi: string,
  ): Promise<Partial<WizardDraft> | null> {
    const result = await deriveHbaArchitecture({
      identityIntro: source.identityIntro,
      brancheCode: source.brancheCode,
      brancheLabel: brancheLabelForApi,
      doel: source.doel,
      distinctiviteit: source.distinctiviteit,
      watBewustNiet: source.watBewustNiet,
      regels: source.regels.filter(Boolean),
    })
    if (!result.ok || !result.derivation) {
      return null
    }
    return {
      archetype: result.derivation?.archetype_voorstel ?? source.archetype,
      foe: result.derivation?.foe_afgeleid ?? source.foe,
      toneOfBehaviour: result.derivation?.tone_of_behaviour ?? source.toneOfBehaviour,
    }
  }

  async function runDerive(): Promise<Partial<WizardDraft> | null> {
    setDeriving(true)
    setDeriveError(null)
    try {
      const updates = await fetchArchitectureDerivation(draft, brancheLabel)
      if (!updates) {
        setDeriveError('Afleiding mislukt')
        return null
      }
      setDraft((d) => ({ ...d, ...updates }))
      return updates
    } finally {
      setDeriving(false)
    }
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
      setOverviewEditing(null)
      setOverviewEditBuffer(null)
      setFlowMode('overview')
      void load()
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

  async function advanceStructuurToConfirm() {
    setDeriveError(null)
    setBrancheResolveError(null)
    try {
      let draftForSave = draft
      draftForSave = await ensureBrancheResolved(draftForSave)
      setDraft(draftForSave)
      const brancheNameForApi =
        draftForSave.brancheInput.trim() ||
        branches.find((b) => b.code === draftForSave.brancheCode)?.label?.trim() ||
        ''
      if (!draftForSave.archetype.trim()) {
        const updates = await fetchArchitectureDerivation(draftForSave, brancheNameForApi)
        if (updates) {
          draftForSave = { ...draftForSave, ...updates }
          setDraft(draftForSave)
        } else {
          setDeriveError('Afleiding mislukt — kies een archetype bij stap 6 of probeer opnieuw.')
          setDraftSaveError('Persoonlijkheid kon niet worden afgeleid.')
          return
        }
      }
      await persistDraft({ silent: true, draftOverride: draftForSave })
      setStructuurWizardPhase('bevestigen')
      setStep(7)
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Opslaan mislukt'
      setBrancheResolveError(err instanceof Error ? err.message : null)
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
    setOverviewEditing(null)
    setOverviewEditBuffer(null)
  }

  async function finishRecalibration() {
    setSaving(true)
    clearNotice()
    setDraftSaveError(null)
    try {
      await persistDraft()
      setRecalibrating(false)
      setFlowMode('overview')
      setOverviewEditing(null)
      setOverviewEditBuffer(null)
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

  function beginOverviewEdit(panel: OverviewEditPanel) {
    setOverviewEditing(panel)
    setOverviewEditBuffer(cloneWizardDraft(draft))
    setDraftSaveError(null)
    clearNotice()
  }

  function cancelOverviewEdit() {
    setOverviewEditing(null)
    setOverviewEditBuffer(null)
  }

  function patchOverviewBuffer(updater: (current: WizardDraft) => WizardDraft) {
    setOverviewEditBuffer((prev) => (prev ? updater(prev) : null))
  }

  function overviewWieBufferDirty(buf: WizardDraft): boolean {
    const a = buf.regels.filter(Boolean)
    const b = draft.regels.filter(Boolean)
    if (a.length !== b.length) return true
    if (a.some((rule, i) => rule !== (b[i] ?? ''))) return true
    return (
      buf.identityIntro !== draft.identityIntro ||
      buf.brancheCode !== draft.brancheCode ||
      buf.brancheInput !== draft.brancheInput ||
      buf.contextprofiel !== draft.contextprofiel ||
      buf.doel !== draft.doel ||
      buf.distinctiviteit !== draft.distinctiviteit ||
      buf.watBewustNiet !== draft.watBewustNiet ||
      buf.archetype !== draft.archetype ||
      buf.foe !== draft.foe ||
      buf.toneOfBehaviour !== draft.toneOfBehaviour ||
      buf.aanspreekvorm !== draft.aanspreekvorm
    )
  }

  function overviewHoeBufferDirty(buf: WizardDraft): boolean {
    const a = buf.kernformuleringen.map((s) => s.trim())
    const b = draft.kernformuleringen.map((s) => s.trim())
    if (a.length !== b.length) return true
    return a.some((line, i) => line !== (b[i] ?? ''))
  }

  function overviewTaalBufferDirty(buf: WizardDraft): boolean {
    return (
      buf.woordenDiePassenInput !== draft.woordenDiePassenInput ||
      buf.woordenDieNietPassenInput !== draft.woordenDieNietPassenInput
    )
  }

  function overviewVoorbeeldenBufferDirty(buf: WizardDraft): boolean {
    return (
      buf.goedeVoorbeelden.some((v, i) => v.trim() !== (draft.goedeVoorbeelden[i] ?? '').trim()) ||
      buf.zwakkeVoorbeelden.some((v, i) => v.trim() !== (draft.zwakkeVoorbeelden[i] ?? '').trim())
    )
  }

  function brancheLabelForDraftState(d: WizardDraft): string {
    return (
      d.brancheInput.trim() || branches.find((b) => b.code === d.brancheCode)?.label?.trim() || ''
    )
  }

  async function saveOverviewPanel(panel: OverviewEditPanel) {
    const buf = overviewEditBuffer
    if (!buf) return
    let dirty = false
    if (panel === 'wie') dirty = overviewWieBufferDirty(buf)
    if (panel === 'hoe') dirty = overviewHoeBufferDirty(buf)
    if (panel === 'taal') dirty = overviewTaalBufferDirty(buf)
    if (panel === 'voorbeelden') dirty = overviewVoorbeeldenBufferDirty(buf)
    if (!dirty) {
      cancelOverviewEdit()
      return
    }
    setSaving(true)
    clearNotice()
    setDraftSaveError(null)
    try {
      let toSave = buf
      if (panel === 'wie') {
        toSave = await resolveBrancheQuiet(buf)
        if (!toSave.archetype.trim()) {
          setDeriving(true)
          setDeriveError(null)
          try {
            const derived = await fetchArchitectureDerivation(
              toSave,
              brancheLabelForDraftState(toSave),
            )
            if (!derived) {
              setDeriveError('Afleiding mislukt')
              setDraftSaveError(
                'Persoonlijkheid kon niet worden afgeleid — kies een archetype of probeer opnieuw.',
              )
              return
            }
            toSave = { ...toSave, ...derived }
          } finally {
            setDeriving(false)
          }
        }
      }
      setDraft(toSave)
      await persistDraft({ draftOverride: toSave })
      cancelOverviewEdit()
      void runArchitectureCheckForDraft(toSave)
      showNotice('Opgeslagen.')
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Opslaan mislukt'
      setDraftSaveError(message)
    } finally {
      setSaving(false)
    }
  }

  if (loading) {
    return <p className="m-0 text-[14px] leading-[18px] text-ce-beige">Laden…</p>
  }

  const scoringReady = narrativeCompleteness?.complete ?? false
  const missingForScoring = narrativeCompleteness?.missing ?? []
  const filledRegels = draft.regels.filter((r) => r.trim())
  const hoofdregel = filledRegels[0] ?? ''
  const subregels = filledRegels.slice(1)
  const marktArchetypeLabel = selectedBranch?.marktArchetype?.trim() ?? ''

  if (flowMode === 'overview') {
    const wieJeBentRichText = `**Rol:** ${draft.identityIntro || '—'}

**Branche:** ${draft.brancheInput.trim() || '—'}

**Markt-archetype:** ${marktArchetypeLabel || '—'}

**Doel:** ${draft.doel || '—'}

**Tegenkracht:** ${draft.foe || '—'}

**Wat je bewust niet doet:** ${draft.watBewustNiet || '—'}

**Persoonlijkheid:** ${draft.archetype || '—'}

**Toon:** ${draft.toneOfBehaviour || '—'}

**Hoofdregel:** ${hoofdregel || '—'}

**Subregels:** ${
      subregels.length > 0 ? subregels.map((r, i) => `${i + 1}. ${r}`).join('\n') : '—'
    }`

    const buf = overviewEditBuffer
    const editWieBranch =
      buf && overviewEditing === 'wie'
        ? branches.find((b) => b.code === buf.brancheCode) ?? null
        : null
    const editWieMarktArchetype = editWieBranch?.marktArchetype?.trim() ?? ''
    const overviewHintBlack = 'm-0 mt-[8px] text-[14px] leading-[18px] text-ce-black'

    return (
      <div className={designStackClass}>
        <section className={borderedSectionClass}>
          {scoringReady ? (
            <p className="m-0 text-[14px] leading-[18px] text-ce-black">
              Je merkarchitectuur staat — merkcontrole op artikelen kan draaien.
            </p>
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
                <a href="#blk-taal" className="font-semibold text-ce-blue underline">
                  Taal
                </a>
                ) en sla op.
              </p>
            </div>
          )}
        </section>

        <section className={borderedSectionClass}>
          <h3 className="m-0 text-[24px] leading-[28px] font-semibold text-ce-blue">
            Wie je bent — hoe wil je het invullen?
          </h3>
          <p className={overviewHintBlack}>
            Heb je je Story Triangle (rol, doel, tegenkracht, regel) al scherp? Vul dan je structuur direct
            in. Weet je dat nog niet precies, dan helpen acht simpele vragen je om het op te bouwen — zonder dat
            je de hele HBA-theorie hoeft te kennen.
          </p>
          <div className="mt-[18px] grid gap-[12px] sm:grid-cols-2">
            <button
              type="button"
              onClick={() => {
                cancelOverviewEdit()
                setWizardEntryMode('structuur')
                setStructuurWizardPhase('stappen')
                setFlowMode('wizard')
                setStep(0)
                setRecalibrating(false)
              }}
              className={`rounded-[14px] border px-[16px] py-[14px] text-left transition-colors ${
                wizardEntryMode === 'structuur'
                  ? 'border-ce-blue bg-ce-pink'
                  : 'border-ce-beige bg-ce-offwhite hover:border-ce-blue'
              }`}
            >
              <p className="m-0 text-[15px] font-semibold text-ce-black">Vul structuur in</p>
              <p className="m-0 mt-[6px] text-[14px] leading-[18px] text-ce-beige">
                Alleen als je het al helder hebt — alles op één scherm, daarna bevestigen.
              </p>
            </button>
            <button
              type="button"
              onClick={() => {
                cancelOverviewEdit()
                setWizardEntryMode('vragen')
                setStructuurWizardPhase('stappen')
                setFlowMode('wizard')
                setStep(0)
                setRecalibrating(false)
              }}
              className={`rounded-[14px] border px-[16px] py-[14px] text-left transition-colors ${
                wizardEntryMode === 'vragen'
                  ? 'border-ce-blue bg-ce-pink'
                  : 'border-ce-beige bg-ce-offwhite hover:border-ce-blue'
              }`}
            >
              <p className="m-0 text-[15px] font-semibold text-ce-black">Beantwoord de vragen</p>
              <p className="m-0 mt-[6px] text-[14px] leading-[18px] text-ce-beige">
                Acht stappen — de app bouwt je architectuur mee op.
              </p>
            </button>
          </div>
        </section>

        <div className={`${borderedSectionClass} ${overviewEditing ? overviewDimClass : ''}`}>
          <div className="flex flex-wrap items-start justify-between gap-[12px]">
            <h3 className="m-0 text-[24px] leading-[28px] font-semibold text-ce-blue">Wie je bent</h3>
            {overviewEditing !== 'wie' ? (
              <button
                type="button"
                className="border-0 bg-transparent p-0 text-[14px] font-semibold text-ce-blue underline"
                onClick={() => beginOverviewEdit('wie')}
              >
                Bewerken
              </button>
            ) : null}
          </div>
          <p className={overviewHintBlack}>
            Je identiteit: de Story Triangle (rol, doel, tegenkracht) plus je regel(s). Met mechanisme
            (hieronder) en regel(s) erbij heet dit je volledige merkarchitectuur.
          </p>
          {overviewEditing === 'wie' && buf ? (
            <>
              <div className="mt-[18px] space-y-[18px]">
                <StrategyField
                  id="overview-wie-identity"
                  title="Rol"
                  value={buf.identityIntro}
                  onChange={(v) => patchOverviewBuffer((d) => ({ ...d, identityIntro: v }))}
                  minHeight={80}
                />
                <StrategySection title="Branche" filled={Boolean(buf.brancheInput.trim())}>
                  <div className="grid gap-[18px]">
                    <Pulldown
                      placeholder="— kies uit lijst —"
                      value={branches.find((branch) => branch.code === buf.brancheCode)?.label ?? ''}
                      options={branches.map((branch) => branch.label)}
                      onChange={(label) => {
                        setBrancheResolveError(null)
                        const match = branches.find((branch) => branch.label === label)
                        patchOverviewBuffer((current) => ({
                          ...current,
                          brancheInput: label,
                          brancheCode: match?.code ?? '',
                        }))
                      }}
                    />
                    <FieldSingle
                      id="overview-wie-branche-free"
                      value={
                        branches.some((branch) => branch.code === buf.brancheCode)
                          ? ''
                          : buf.brancheInput
                      }
                      placeholder="Of typ je eigen branche"
                      onChange={(value) => {
                        setBrancheResolveError(null)
                        const match = branches.find(
                          (branch) => branch.label.toLowerCase() === value.trim().toLowerCase(),
                        )
                        patchOverviewBuffer((current) => ({
                          ...current,
                          brancheInput: value,
                          brancheCode: match?.code ?? '',
                        }))
                      }}
                    />
                  </div>
                  {editWieBranch?.marktArchetype ? (
                    <p className="m-0 mt-[10px] text-[14px] leading-[18px] text-ce-black">
                      Markt-archetype: {editWieMarktArchetype || '—'}
                      {editWieBranch.bron === 'afgeleid' ? (
                        <span className="text-ce-beige"> (automatisch afgeleid)</span>
                      ) : (
                        <span className="text-ce-beige"> (gecureerd)</span>
                      )}
                    </p>
                  ) : buf.brancheInput.trim() ? (
                    <p className="m-0 mt-[10px] text-[14px] leading-[18px] text-ce-beige">
                      Markt-archetype: nog niet vastgelegd voor deze branche.
                    </p>
                  ) : null}
                </StrategySection>
                <StrategySection title="Waar staat je merk nu?" filled={Boolean(buf.contextprofiel)}>
                  <Pulldown
                    value={contextprofielLabel(buf.contextprofiel)}
                    options={HBA_CONTEXT_PROFILES.map((profile) => profile.label)}
                    onChange={(label) => {
                      const match = HBA_CONTEXT_PROFILES.find((profile) => profile.label === label)
                      patchOverviewBuffer((current) => ({
                        ...current,
                        contextprofiel: match?.value ?? current.contextprofiel,
                      }))
                    }}
                  />
                </StrategySection>
                <StrategyField
                  id="overview-wie-doel"
                  title="Doel"
                  value={buf.doel}
                  onChange={(v) => patchOverviewBuffer((d) => ({ ...d, doel: v }))}
                  minHeight={72}
                />
                <StrategyField
                  id="overview-wie-foe"
                  title="Tegenkracht"
                  value={buf.foe}
                  onChange={(v) => patchOverviewBuffer((d) => ({ ...d, foe: v }))}
                  minHeight={72}
                />
                <StrategyField
                  id="overview-wie-distinct"
                  title="Wat je anders maakt"
                  value={buf.distinctiviteit}
                  onChange={(v) => patchOverviewBuffer((d) => ({ ...d, distinctiviteit: v }))}
                  minHeight={72}
                />
                <StrategyField
                  id="overview-wie-niet"
                  title="Wat je bewust niet doet"
                  value={buf.watBewustNiet}
                  onChange={(v) => patchOverviewBuffer((d) => ({ ...d, watBewustNiet: v }))}
                  minHeight={72}
                />
                <div className="grid gap-[12px]">
                  <p className="m-0 text-[14px] font-semibold leading-[18px] text-ce-black">
                    Regels — eerste regel is je hoofdregel, daarna subregels
                  </p>
                  <div className={borderedSectionStackClass}>
                    {buf.regels.map((rule, index) => (
                      <StrategyField
                        key={`overview-wie-regel-${index}`}
                        id={`overview-wie-regel-${index}`}
                        title={index === 0 ? 'Hoofdregel' : `Subregel ${index}`}
                        value={rule}
                        onChange={(v) => {
                          patchOverviewBuffer((current) => {
                            const next = [...current.regels]
                            next[index] = v
                            return { ...current, regels: next }
                          })
                        }}
                        minHeight={56}
                      />
                    ))}
                  </div>
                  {buf.regels.length < 5 ? (
                    <button
                      type="button"
                      className="self-start border-0 bg-transparent text-[14px] font-semibold text-ce-blue underline"
                      onClick={() =>
                        patchOverviewBuffer((current) => ({
                          ...current,
                          regels: [...current.regels, ''],
                        }))
                      }
                    >
                      + Subregel toevoegen
                    </button>
                  ) : null}
                </div>
                <div className={borderedSectionStackClass}>
                  <StrategyField
                    id="overview-wie-tone"
                    title="Je toon"
                    value={buf.toneOfBehaviour}
                    onChange={(v) => patchOverviewBuffer((d) => ({ ...d, toneOfBehaviour: v }))}
                    minHeight={72}
                  />
                  <StrategySection title="Aanspreekvorm" filled={Boolean(buf.aanspreekvorm)}>
                    <Pulldown
                      value={buf.aanspreekvorm === 'u' ? 'u' : 'je'}
                      options={['je', 'u']}
                      onChange={(value) =>
                        patchOverviewBuffer((d) => ({
                          ...d,
                          aanspreekvorm: value === 'u' ? 'u' : 'je',
                        }))
                      }
                    />
                  </StrategySection>
                  <StrategySection title="Persoonlijkheid (archetype)" filled={Boolean(buf.archetype)}>
                    <Pulldown
                      value={buf.archetype}
                      options={[...HBA_ARCHETYPES]}
                      onChange={(value) => patchOverviewBuffer((d) => ({ ...d, archetype: value }))}
                    />
                  </StrategySection>
                </div>
              </div>
              <div className={`${pillActionsRowClass} mt-[18px]`}>
                <PillButton
                  type="button"
                  loading={saving || deriving}
                  disabled={saving || deriving}
                  onClick={() => void saveOverviewPanel('wie')}
                >
                  Opslaan
                </PillButton>
                <PillButton
                  type="button"
                  variant="second"
                  disabled={saving || deriving}
                  onClick={cancelOverviewEdit}
                >
                  Annuleren
                </PillButton>
              </div>
            </>
          ) : (
            <div className="mt-[18px]">
              <RichTextDisplay text={wieJeBentRichText} />
            </div>
          )}
        </div>

        <div className={overviewEditing ? overviewDimClass : ''}>
          <StrategySection
            title="Hoe sterk staat je architectuur?"
            hint="Dit wordt automatisch opnieuw bekeken na opslaan — blokkeert niets, maar laat zien waar je architectuur zwakker kan zijn."
            hintTone="black"
          >
            <HbaArchitectureCheckPanel
              check={architectureCheck}
              loading={architectureCheckLoading}
              error={architectureCheckError}
              onRetry={() => void runArchitectureCheckForDraft(draft)}
              onStrategist={openStrategistMail}
              showSummaryHeading={false}
              selectionMode={false}
            />
          </StrategySection>
        </div>

        <div className={`${borderedSectionClass} ${overviewPanelDimmed('hoe') ? overviewDimClass : ''}`}>
          <div className="flex flex-wrap items-start justify-between gap-[12px]">
            <h3 className="m-0 text-[24px] leading-[28px] font-semibold text-ce-blue">Hoe je werkt</h3>
            {overviewEditing !== 'hoe' ? (
              <button
                type="button"
                className="border-0 bg-transparent p-0 text-[14px] font-semibold text-ce-blue underline"
                onClick={() => beginOverviewEdit('hoe')}
              >
                Bewerken
              </button>
            ) : null}
          </div>
          <p className={overviewHintBlack}>
            Het mechanisme: hoe je regel in de praktijk werkt, met bewijs. Drie tot vijf zinnen die de kern in
            gewone woorden zeggen — deze komen bewust terug in je content.
          </p>
          {overviewEditing === 'hoe' && buf ? (
            <>
              <div className="mt-[18px] space-y-[12px]">
                {buf.kernformuleringen.map((line, index) => (
                  <StrategyField
                    key={`overview-kern-edit-${index}`}
                    id={`overview-kern-edit-${index}`}
                    title={index === 0 ? 'Formulering 1' : `Formulering ${index + 1}`}
                    documentSuggestion={docSuggestions?.kernformuleringen?.[index] ?? undefined}
                    value={line}
                    onChange={(v) => {
                      patchOverviewBuffer((current) => {
                        const next = [...current.kernformuleringen]
                        next[index] = v
                        return { ...current, kernformuleringen: next }
                      })
                    }}
                    minHeight={60}
                  />
                ))}
                {buf.kernformuleringen.length < 5 ? (
                  <button
                    type="button"
                    className="self-start border-0 bg-transparent text-[14px] font-semibold text-ce-blue underline"
                    onClick={() =>
                      patchOverviewBuffer((current) => ({
                        ...current,
                        kernformuleringen: [...current.kernformuleringen, ''],
                      }))
                    }
                  >
                    + Voeg formulering toe
                  </button>
                ) : null}
              </div>
              <div className={`${pillActionsRowClass} mt-[18px]`}>
                <PillButton
                  type="button"
                  loading={saving}
                  disabled={saving}
                  onClick={() => void saveOverviewPanel('hoe')}
                >
                  Opslaan
                </PillButton>
                <PillButton type="button" variant="second" disabled={saving} onClick={cancelOverviewEdit}>
                  Annuleren
                </PillButton>
              </div>
            </>
          ) : (
            <div className="mt-[18px] space-y-[8px]">
              {draft.kernformuleringen.map((line, index) => (
                <p key={`kern-view-${index}`} className="m-0 text-[14px] leading-[18px] text-ce-black">
                  <span className="font-semibold">
                    {index === 0 ? 'Formulering 1' : `Formulering ${index + 1}`}:{' '}
                  </span>
                  {line.trim() ? line : '—'}
                </p>
              ))}
            </div>
          )}
        </div>

        <div className={`${borderedSectionClass} ${overviewPanelDimmed('taal') ? overviewDimClass : ''}`} id="blk-taal">
          <div className="flex flex-wrap items-start justify-between gap-[12px]">
            <h3 className="m-0 text-[24px] leading-[28px] font-semibold text-ce-blue">Taal</h3>
            {overviewEditing !== 'taal' ? (
              <button
                type="button"
                className="border-0 bg-transparent p-0 text-[14px] font-semibold text-ce-blue underline"
                onClick={() => beginOverviewEdit('taal')}
              >
                Bewerken
              </button>
            ) : null}
          </div>
          {overviewEditing === 'taal' && buf ? (
            <>
              <div className="mt-[18px] space-y-[12px]">
                <StrategyField
                  id="overview-taal-passen"
                  title="Woorden die passen"
                  hint="Minimaal drie items. Scheid woorden met komma's."
                  value={buf.woordenDiePassenInput}
                  onChange={(v) => patchOverviewBuffer((d) => ({ ...d, woordenDiePassenInput: v }))}
                  minHeight={72}
                  placeholder="backcasting, uitvoerbaar, bouwen, onvermijdelijk"
                />
                <StrategyField
                  id="overview-taal-niet"
                  title="Woorden die niet passen"
                  hint="Minimaal drie items. Scheid woorden met komma's."
                  value={buf.woordenDieNietPassenInput}
                  onChange={(v) => patchOverviewBuffer((d) => ({ ...d, woordenDieNietPassenInput: v }))}
                  minHeight={72}
                  placeholder="rapport, theoretisch, synergy, best practice"
                />
              </div>
              <div className={`${pillActionsRowClass} mt-[18px]`}>
                <PillButton
                  type="button"
                  loading={saving}
                  disabled={saving}
                  onClick={() => void saveOverviewPanel('taal')}
                >
                  Opslaan
                </PillButton>
                <PillButton type="button" variant="second" disabled={saving} onClick={cancelOverviewEdit}>
                  Annuleren
                </PillButton>
              </div>
            </>
          ) : (
            <div className="mt-[18px] space-y-[8px]">
              <p className="m-0 text-[14px] leading-[18px] text-ce-black">
                <span className="font-semibold">Woorden die passen: </span>
                {parseWordListInput(draft.woordenDiePassenInput).length > 0
                  ? parseWordListInput(draft.woordenDiePassenInput).join(' · ')
                  : '—'}
              </p>
              <p className="m-0 text-[14px] leading-[18px] text-ce-black">
                <span className="font-semibold">Woorden die niet passen: </span>
                {parseWordListInput(draft.woordenDieNietPassenInput).length > 0
                  ? parseWordListInput(draft.woordenDieNietPassenInput).join(' · ')
                  : '—'}
              </p>
            </div>
          )}
        </div>

        <div className={`${borderedSectionClass} ${overviewPanelDimmed('voorbeelden') ? overviewDimClass : ''}`}>
          <div className="flex flex-wrap items-start justify-between gap-[12px]">
            <h3 className="m-0 text-[24px] leading-[28px] font-semibold text-ce-blue">Voorbeelden voor de merkmeting</h3>
            {overviewEditing !== 'voorbeelden' ? (
              <button
                type="button"
                className="border-0 bg-transparent p-0 text-[14px] font-semibold text-ce-blue underline"
                onClick={() => beginOverviewEdit('voorbeelden')}
              >
                Bewerken
              </button>
            ) : null}
          </div>
          <p className={overviewHintBlack}>Deze zinnen ijken de merkmeting — pas ze aan als iets niet klopt.</p>
          {overviewEditing === 'voorbeelden' && buf ? (
            <>
              <div className="mt-[12px] flex flex-wrap items-start gap-[12px] [&_button]:mb-0">
                <PillButton type="button" variant="second" onClick={startRecalibration}>
                  Opnieuw kalibreren
                </PillButton>
              </div>
              <div className={`${borderedSectionStackClass} mt-[12px]`}>
                {[0, 1, 2].flatMap((index) => [
                  <StrategyField
                    key={`overview-good-edit-${index}`}
                    id={`overview-good-edit-${index}`}
                    title={`Sterk voorbeeld ${index + 1}`}
                    value={buf.goedeVoorbeelden[index] ?? ''}
                    onChange={(v) => {
                      patchOverviewBuffer((current) => {
                        const next = [...current.goedeVoorbeelden] as ExampleTriple
                        next[index] = v
                        return { ...current, goedeVoorbeelden: next }
                      })
                    }}
                    minHeight={60}
                  />,
                  <StrategyField
                    key={`overview-weak-edit-${index}`}
                    id={`overview-weak-edit-${index}`}
                    title={`Zwak voorbeeld ${index + 1}`}
                    value={buf.zwakkeVoorbeelden[index] ?? ''}
                    onChange={(v) => {
                      patchOverviewBuffer((current) => {
                        const next = [...current.zwakkeVoorbeelden] as ExampleTriple
                        next[index] = v
                        return { ...current, zwakkeVoorbeelden: next }
                      })
                    }}
                    minHeight={60}
                  />,
                ])}
              </div>
              <div className={`${pillActionsRowClass} mt-[18px]`}>
                <PillButton
                  type="button"
                  loading={saving}
                  disabled={saving}
                  onClick={() => void saveOverviewPanel('voorbeelden')}
                >
                  Opslaan
                </PillButton>
                <PillButton type="button" variant="second" disabled={saving} onClick={cancelOverviewEdit}>
                  Annuleren
                </PillButton>
              </div>
            </>
          ) : (
            <div className="mt-[18px] space-y-[6px]">
              {[0, 1, 2].map((index) => (
                <div key={`ex-pair-${index}`} className="space-y-[4px]">
                  <p className="m-0 text-[14px] leading-[18px] text-ce-black">
                    <span className="font-semibold">Sterk {index + 1}: </span>
                    {draft.goedeVoorbeelden[index]?.trim() ? draft.goedeVoorbeelden[index] : '—'}
                  </p>
                  <p className="m-0 text-[14px] leading-[18px] text-ce-black">
                    <span className="font-semibold">Zwak {index + 1}: </span>
                    {draft.zwakkeVoorbeelden[index]?.trim() ? draft.zwakkeVoorbeelden[index] : '—'}
                  </p>
                </div>
              ))}
            </div>
          )}
        </div>

        <div className={overviewEditing ? overviewDimClass : ''}>
          <StrategySection
            title="Strategiedocument"
            hint="Upload een strategiedocument — we gebruiken het voor suggesties."
            hintTone="black"
          >
            <HbaWizardDocumentUpload
              busy={docUploadBusy}
              error={docUploadError}
              currentFilename={uploadedDocumentFilename}
              hasExistingAnswers={hasExistingAnswers}
              mode="maintenance"
              onUpload={(file, options) => void handleWizardDocumentUpload(file, options)}
            />
          </StrategySection>
        </div>

        <section className={`${borderedSectionClass} ${overviewEditing ? overviewDimClass : ''}`}>
          <ActionNotice message={notice} kind={noticeKind} fading={noticeFading} className="m-0" />
          {draftSaveError ? <p className="m-0 text-[14px] text-ce-red">{draftSaveError}</p> : null}

          <div className={`${pillActionsRowClass} flex-nowrap`}>
            <PillButton
              type="button"
              variant="second"
              className="shrink-0"
              disabled={Boolean(overviewEditing)}
              onClick={() => {
                cancelOverviewEdit()
                setStructuurWizardPhase('stappen')
                setFlowMode('wizard')
                setStep(0)
                setRecalibrating(false)
              }}
            >
              Opnieuw invullen
            </PillButton>
            <PillButton
              type="button"
              variant="second"
              className="shrink-0"
              disabled={Boolean(overviewEditing)}
              onClick={() => {
                openStrategistMail()
              }}
            >
              Laat een HBA-strateeg meekijken
            </PillButton>
          </div>
          <p className="m-0 mt-[8px] text-[12px] leading-[16px] text-ce-beige">
            We nemen binnen één werkdag contact op.
          </p>
        </section>
      </div>
    )
  }

  const current = STEPS[step]
  const examplesReady = examplesComplete(draft.goedeVoorbeelden, draft.zwakkeVoorbeelden)

  const stepTitleLine = recalibrating
    ? 'Kalibratie opnieuw — kies drie paren'
    : showStructuurScroll
      ? 'Vul je merkarchitectuur in — daarna bevestig je op het volgende scherm.'
      : `Stap ${step + 1} van ${STEPS.length} — ${current.title}`

  if (showStructuurScroll) {
    return (
      <section className={`${designStackClass} ${borderedSectionClass}`}>
        <p className="m-0 text-[14px] leading-[18px] font-semibold text-ce-black">{stepTitleLine}</p>
        <div
          ref={structuurScrollRef}
          className="max-h-[min(70vh,720px)] overflow-y-auto pr-[4px]"
        >
          <div className={`${borderedSectionStackClass} pt-[12px]`}>
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
              id="identity-intro-structuur"
              title="Wie ben je en in welke markt opereer je?"
              documentSuggestion={docSuggestions?.identityIntro ?? undefined}
              value={draft.identityIntro}
              onChange={(v) => setDraft((d) => ({ ...d, identityIntro: v }))}
              minHeight={100}
            />
            <StrategySection title="In welke markt" filled={Boolean(draft.brancheInput.trim())}>
              <div className="grid gap-[18px]">
                <Pulldown
                  placeholder="— kies uit lijst —"
                  value={branches.find((branch) => branch.code === draft.brancheCode)?.label ?? ''}
                  options={branches.map((branch) => branch.label)}
                  onChange={(label) => {
                    setBrancheResolveError(null)
                    const match = branches.find((branch) => branch.label === label)
                    setDraft((cur) => ({
                      ...cur,
                      brancheInput: label,
                      brancheCode: match?.code ?? '',
                    }))
                  }}
                />
                <FieldSingle
                  id="hba-branche-structuur"
                  value={
                    branches.some((branch) => branch.code === draft.brancheCode) ? '' : draft.brancheInput
                  }
                  placeholder="Of typ je eigen branche"
                  onChange={(value) => {
                    setBrancheResolveError(null)
                    const match = branches.find(
                      (branch) => branch.label.toLowerCase() === value.trim().toLowerCase(),
                    )
                    setDraft((cur) => ({
                      ...cur,
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
            <StrategySection title="Waar staat je merk nu?" filled={Boolean(draft.contextprofiel)}>
              <Pulldown
                value={contextprofielLabel(draft.contextprofiel)}
                options={HBA_CONTEXT_PROFILES.map((profile) => profile.label)}
                onChange={(label) => {
                  const match = HBA_CONTEXT_PROFILES.find((profile) => profile.label === label)
                  setDraft((d) => ({ ...d, contextprofiel: match?.value ?? d.contextprofiel }))
                }}
              />
            </StrategySection>
            <StrategyField
              id="hba-doel-structuur"
              title="Wat wil je bereiken?"
              hint="Concreet waar je voor gaat — geen mooie missiezin nodig."
              documentSuggestion={docSuggestions?.doel ?? undefined}
              value={draft.doel}
              onChange={(v) => setDraft((d) => ({ ...d, doel: v }))}
              minHeight={120}
            />
            <StrategyField
              id="hba-distinct-structuur"
              title="Wat maakt je anders dan de rest?"
              hint="Schrijf het zoals je het aan een vriend zou vertellen."
              documentSuggestion={docSuggestions?.distinctiviteit ?? undefined}
              value={draft.distinctiviteit}
              onChange={(v) => setDraft((d) => ({ ...d, distinctiviteit: v }))}
              minHeight={120}
            />
            <StrategyField
              id="hba-niet-structuur"
              title="Waar geloof je niet in, ook al doet de hele markt het?"
              documentSuggestion={docSuggestions?.watBewustNiet ?? undefined}
              value={draft.watBewustNiet}
              onChange={(v) => setDraft((d) => ({ ...d, watBewustNiet: v }))}
              minHeight={120}
            />
            <div className="grid gap-[12px]">
              <p className="m-0 text-[14px] leading-[18px] font-semibold text-ce-black">
                Waar zou je nee tegen zeggen, ook als het geld zou opleveren?
              </p>
              <p className="m-0 text-[13px] leading-[17px] text-ce-beige">
                De eerste regel is je hoofdregel; daarna zijn het subregels.
              </p>
              <div className={borderedSectionStackClass}>
                {draft.regels.map((rule, index) => (
                  <StrategyField
                    key={`struct-regel-${index}`}
                    id={`struct-rule-${index}`}
                    title={index === 0 ? 'Hoofdregel' : `Subregel ${index}`}
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
            <div className="grid gap-[12px]">
              {deriving ? <p className="m-0 text-[14px] text-ce-black">We stellen iets voor…</p> : null}
              {deriveError ? <p className="m-0 text-[14px] text-ce-red">{deriveError}</p> : null}
              {!draft.archetype && !deriving ? (
                <PillButton type="button" onClick={() => void runDerive()}>
                  Stel persoonlijkheid voor
                </PillButton>
              ) : null}
              {draft.archetype ? (
                <div className={borderedSectionStackClass}>
                  <StrategyField
                    id="hba-foe-structuur"
                    title="Waar je tegen vecht"
                    value={draft.foe}
                    onChange={(v) => setDraft((d) => ({ ...d, foe: v }))}
                    minHeight={80}
                  />
                  <StrategyField
                    id="hba-tone-structuur"
                    title="Je toon"
                    value={draft.toneOfBehaviour}
                    onChange={(v) => setDraft((d) => ({ ...d, toneOfBehaviour: v }))}
                    minHeight={80}
                  />
                  <StrategySection title="Aanspreekvorm" filled={Boolean(draft.aanspreekvorm)}>
                    <Pulldown
                      value={draft.aanspreekvorm === 'u' ? 'u' : 'je'}
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
              ) : null}
            </div>
            <IdentityCalibrationStep
              key={calibrationSessionKey}
              goedeVoorbeelden={draft.goedeVoorbeelden}
              zwakkeVoorbeelden={draft.zwakkeVoorbeelden}
              architecture={calibrationArchitecture}
              onChange={(good, weak) =>
                setDraft((d) => ({ ...d, goedeVoorbeelden: good, zwakkeVoorbeelden: weak }))
              }
            />
          </div>
        </div>
        <div className={pillActionsRowClass}>
          {draftSaveError ? <p className="m-0 w-full basis-full text-[14px] text-ce-red">{draftSaveError}</p> : null}
          <PillButton
            type="button"
            loading={saving || deriving || brancheResolving}
            disabled={saving || deriving || brancheResolving || !examplesReady}
            onClick={() => void advanceStructuurToConfirm()}
          >
            Naar bevestigen
          </PillButton>
        </div>
      </section>
    )
  }

  return (
    <section className={`${designStackClass} ${borderedSectionClass}`}>
      <p className="m-0 text-[14px] leading-[18px] font-semibold text-ce-black">{stepTitleLine}</p>

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
            title="Wie ben je en in welke markt opereer je?"
            documentSuggestion={docSuggestions?.identityIntro ?? undefined}
            value={draft.identityIntro}
            onChange={(v) => setDraft((d) => ({ ...d, identityIntro: v }))}
            minHeight={100}
          />
          <StrategySection title="In welke markt" filled={Boolean(draft.brancheInput.trim())}>
            <div className="grid gap-[18px]">
              <Pulldown
                placeholder="— kies uit lijst —"
                value={branches.find((branch) => branch.code === draft.brancheCode)?.label ?? ''}
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
                  branches.some((branch) => branch.code === draft.brancheCode) ? '' : draft.brancheInput
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
          <StrategySection title="Waar staat je merk nu?" filled={Boolean(draft.contextprofiel)}>
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
          title="Wat wil je bereiken?"
          hint="Concreet waar je voor gaat — geen mooie missiezin nodig."
          documentSuggestion={docSuggestions?.doel ?? undefined}
          value={draft.doel}
          onChange={(v) => setDraft((d) => ({ ...d, doel: v }))}
          minHeight={120}
        />
      )}

      {step === 2 && (
        <StrategyField
          id="hba-distinct"
          title="Wat maakt je anders dan de rest?"
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
          title="Waar geloof je niet in, ook al doet de hele markt het?"
          documentSuggestion={docSuggestions?.watBewustNiet ?? undefined}
          value={draft.watBewustNiet}
          onChange={(v) => setDraft((d) => ({ ...d, watBewustNiet: v }))}
          minHeight={120}
        />
      )}

      {step === 4 && (
        <div className="grid gap-[12px]">
          <p className="m-0 text-[14px] leading-[18px] font-semibold text-ce-black">
            Waar zou je nee tegen zeggen, ook als het geld zou opleveren?
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
                  title="Waar je tegen vecht"
                  value={draft.foe}
                  onChange={(v) => setDraft((d) => ({ ...d, foe: v }))}
                  minHeight={80}
                />
                <StrategyField
                  id="hba-tone"
                  title="Je toon"
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
          {wizardEntryMode === 'structuur' && structuurWizardPhase === 'bevestigen' ? (
            <div className={pillActionsRowClass}>
              <PillButton
                type="button"
                variant="second"
                onClick={() => {
                  setStructuurWizardPhase('stappen')
                  setStep(0)
                }}
              >
                Terug naar invullen
              </PillButton>
            </div>
          ) : null}
          <RichTextDisplay
            className="w-full"
            text={`**Wie je bent:** ${draft.identityIntro || '—'}\n\n**Wat je wilt bereiken:** ${draft.doel}\n\n**Waar je tegen vecht:** ${draft.foe}`}
          />
          <HbaArchitectureCheckPanel
            check={architectureCheck}
            loading={architectureCheckLoading}
            error={architectureCheckError}
            onRetry={() => void runArchitectureCheckForDraft(draft)}
            onAanscherpen={(targetStep) => {
              setStructuurWizardPhase('stappen')
              setStep(targetStep)
            }}
            onStrategist={openStrategistMail}
            selectedToneSuggestion={confirmToneSuggestion}
            onSelectToneSuggestion={setConfirmToneSuggestion}
            selectedVoorbeeldPicks={confirmVoorbeeldPicks}
            onToggleVoorbeeldPick={(pick, selected) => {
              setConfirmVoorbeeldPicks((cur) => {
                if (!selected) {
                  return cur.filter((item) => !(item.type === pick.type && item.tekst === pick.tekst))
                }
                if (cur.some((item) => item.type === pick.type && item.tekst === pick.tekst)) {
                  return cur
                }
                return [...cur, pick]
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
              Laat een HBA-strateeg meekijken
            </PillButton>
          </div>
        </div>
      )}

      <div className={pillActionsRowClass}>
        {draftSaveError && (recalibrating || step < 7) ? (
          <p className="m-0 w-full basis-full text-[14px] text-ce-red">{draftSaveError}</p>
        ) : null}
        {draftSaved && !recalibrating && step < 7 ? (
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
        ) : step < 7 ? (
          <>
            {step > 0 ? (
              <PillButton
                type="button"
                variant="second"
                onClick={() => {
                  if (
                    wizardEntryMode === 'structuur' &&
                    structuurWizardPhase === 'bevestigen' &&
                    step === 7
                  ) {
                    setStructuurWizardPhase('stappen')
                  }
                  setStep((s) => s - 1)
                }}
              >
                Vorige
              </PillButton>
            ) : null}
            <PillButton
              type="button"
              disabled={step === 6 && !examplesReady}
              onClick={() => void advanceStep()}
            >
              {wizardEntryMode === 'structuur' && structuurWizardPhase === 'stappen' && step === 6
                ? 'Naar bevestigen'
                : 'Volgende'}
            </PillButton>
          </>
        ) : null}
      </div>
    </section>
  )
}

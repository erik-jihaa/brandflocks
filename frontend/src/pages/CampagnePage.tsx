import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react'
import { Navigate, useLocation, useNavigate, useParams } from 'react-router-dom'
import { GridShell } from '../layout/GridShell'
import { FieldSingle } from '../components/ui/Field'
import { Pulldown } from '../components/ui/Pulldown'
import { PillButton, pillActionsRowClass } from '../components/PillButton'
import { CampagneListRow } from '../components/StatusRow'
import { ActionSep } from '../components/ui/ActionLink'
import { CampagneBankEditor, type CampagneBankRow } from '../components/CampagneBankEditor'
import { CommaWordsField, feitRowsWithCommaWords } from '../components/CommaWordsField'
import { CheckboxOption } from '../components/ui/RadioOption'
import { StrategySection } from '../components/ui/Field'
import { GRID2_REVIEW_MAIN_WIDTH, LIST_SECTION } from '../layout/grid.constants'
import { campagnePhaseLabel, groupCampagnesByStartWeek } from '../utils/campagneActions'
import {
  borderedSectionStackClass,
  designStackClass,
  pageNavLinkClass,
  pillActionsRowWithNavLinkClass,
} from '../design/identity.constants'
import { scheduleAgendaSlot } from '../api/agenda'
import { PlanReview, type PlanReviewSectionKey } from './CampagnePlanReview'
import { PeriodAgendaOverview } from '../components/PeriodAgendaOverview'
import { ApiError, isApiConfigured } from '../api/client'
import {
  CAMPAGNE_TEMPLATE_FALLBACKS,
  CAMPAGNE_MERK_ONDERDELEN,
  archiveCampagnePreview,
  createCampagnePreview,
  deleteCampagnePreview,
  fetchCampagneBank,
  fetchCampagnePreview,
  fetchCampagneTemplates,
  listCampagnePreviews,
  patchCampagnePreview,
  runCampagnePlanCheck,
  saveCampagneBank,
  type CampagneBankFeit,
  type CampagneBankVraag,
  type CampagneGoal,
  type CampagnePlanCheck,
  type CampagnePlanDecisions,
  type CampagnePlanProgress,
  type CampagnePreviewCampaign,
  type CampagnePreviewSlot,
  type CampagneProductExtract,
  type CampagnePhase,
  type CampagnePhasePlan,
  type CampagneTemplate,
  type CampagneTemplateKey,
} from '../api/campagnePreview'

const STEP_COPY: { title: string; question: string; hint: string }[] = [
  {
    title: 'Soort',
    question: 'Wat voor campagne wil je maken?',
    hint: 'Kies Touch, Tell of Sell. De keuze gaat meteen verder.',
  },
  {
    title: 'Onderwerp',
    question: 'Waar gaat het over?',
    hint: 'Sell leest de productpagina. Tell heeft een vaste dag. Touch blijft licht.',
  },
  {
    title: 'Vragen en feiten',
    question: 'Welke vragen en feiten gaan mee?',
    hint: 'Merkonderdelen, vragen en feiten. Woorden vul je komma-gescheiden in — die komen in de feitenbank.',
  },
  {
    title: 'Periode',
    question: 'Wanneer?',
    hint: 'Start- en einddatum. Bij Tell: de vaste weekdag.',
  },
  {
    title: 'Kanalen',
    question: 'Waar, en hoeveel?',
    hint: 'Aantal posts per kanaal. Maximaal 100. Eén slot is één thema.',
  },
  {
    title: 'Opbouw',
    question: 'In welke fases?',
    hint: 'Fases van het sjabloon. Weglaten mag, minstens één fase blijft.',
  },
  {
    title: 'Plan',
    question: 'Klopt het plan?',
    hint: 'Eerst Maak plan. De stappen komen uit de check. Elk slot is een thema; het artikel schrijf je daarna.',
  },
]

const WEEKDAYS = ['Maandag', 'Dinsdag', 'Woensdag', 'Donderdag', 'Vrijdag', 'Zaterdag', 'Zondag'] as const

const MERK_ONDERDEEL_LABELS: Record<string, string> = {
  wat_je_doet: 'Wat je doet',
  waarom: 'Waarom je bestaat',
  hoe_je_werkt: 'Hoe je werkt',
  waar_je_nee_tegen_zegt: 'Waar je nee tegen zegt',
  waar_je_tegen_bent: 'Waar je tegen bent',
}

const TEMPLATE_PILL_LABELS: Record<CampagneTemplateKey, string> = {
  lancering: 'Sell',
  serie: 'Tell',
  touch: 'Touch',
}

const MODE_ORDER: CampagneTemplateKey[] = ['touch', 'serie', 'lancering']

function modeFromTemplate(key: string | null | undefined): CampagneTemplateKey {
  if (key === 'serie' || key === 'tell') return 'serie'
  if (key === 'touch') return 'touch'
  return 'lancering'
}

type FormState = {
  templateKey: CampagneTemplateKey
  title: string
  productPageUrl: string
  goal: CampagneGoal
  startDate: string
  endDate: string
  weekday: number
  linkedin: string
  instagram: string
  disabledKeys: string[]
  geoQuestionIds: string[]
  vraagIds: string[]
  feitIds: string[]
  merkOnderdelen: string[]
  /** Alleen afwijkingen t.o.v. het sjabloon per fase-key */
  phaseMerkOnderdeel: Record<string, string | null>
}

function isoDate(d: Date): string {
  const y = d.getFullYear()
  const m = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${y}-${m}-${day}`
}

function addDays(d: Date, days: number): Date {
  const next = new Date(d)
  next.setDate(next.getDate() + days)
  return next
}

function emptyForm(geoQuestionIds: string[] = [], vraagIds: string[] = [], feitIds: string[] = []): FormState {
  const today = new Date()
  return {
    templateKey: 'lancering',
    title: '',
    productPageUrl: '',
    goal: 'beide',
    startDate: isoDate(today),
    endDate: isoDate(addDays(today, 27)),
    weekday: 2,
    linkedin: '8',
    instagram: '12',
    disabledKeys: [],
    geoQuestionIds,
    vraagIds,
    feitIds,
    merkOnderdelen: [],
    phaseMerkOnderdeel: {},
  }
}

function parseCount(raw: string): number {
  const n = Number.parseInt(raw.trim(), 10)
  return Number.isFinite(n) && n > 0 ? n : 0
}

function channelsFromForm(form: FormState): Record<string, number> {
  const channels: Record<string, number> = {}
  const linkedin = parseCount(form.linkedin)
  const instagram = parseCount(form.instagram)
  if (linkedin > 0) channels.linkedin = linkedin
  if (instagram > 0) channels.instagram = instagram
  return channels
}

function firstWeekdayOnOrAfter(start: Date, weekdayIso: number): Date {
  const jsDay = start.getDay()
  const currentIso = jsDay === 0 ? 7 : jsDay
  const delta = (weekdayIso - currentIso + 7) % 7
  return addDays(start, delta)
}

function endDateForWeeklyCount(startIso: string, weekdayIso: number, count: number): string {
  const start = new Date(`${startIso}T12:00:00`)
  const first = firstWeekdayOnOrAfter(start, weekdayIso)
  return isoDate(addDays(first, Math.max(0, count - 1) * 7))
}

function applyTemplateDefaults(prev: FormState, key: CampagneTemplateKey): FormState {
  if (key === 'serie') {
    const weeklyCount = Math.max(parseCount('6'), 1)
    return {
      ...prev,
      templateKey: key,
      goal: 'merk',
      productPageUrl: '',
      linkedin: '6',
      instagram: '0',
      endDate: endDateForWeeklyCount(prev.startDate, prev.weekday, weeklyCount),
      disabledKeys: [],
      phaseMerkOnderdeel: {},
    }
  }
  if (key === 'touch') {
    return {
      ...prev,
      templateKey: key,
      goal: 'merk',
      productPageUrl: '',
      linkedin: '3',
      instagram: '3',
      disabledKeys: [],
      phaseMerkOnderdeel: {},
    }
  }
  return {
    ...prev,
    templateKey: 'lancering',
    goal: 'verkopen',
    linkedin: '8',
    instagram: '12',
    disabledKeys: [],
    phaseMerkOnderdeel: {},
  }
}

function progressLabelFor(stages: CampagnePlanProgress[], key: string): string | null {
  return stages.find((stage) => stage.key === key)?.label ?? null
}

function merkOnderdeelLabel(key: string | null): string | null {
  if (!key) return null
  return MERK_ONDERDEEL_LABELS[key] ?? key.replaceAll('_', ' ')
}

function phaseMerkOnderdeelFor(phase: CampagnePhase, overrides: Record<string, string | null>): string | null {
  if (Object.prototype.hasOwnProperty.call(overrides, phase.key)) {
    return overrides[phase.key]
  }
  return phase.merkOnderdeel
}

function buildPhasePlan(form: FormState, template: CampagneTemplate | null): CampagnePhasePlan | undefined {
  const merkOnderdeelByKey: Record<string, string | null> = {}
  for (const [key, value] of Object.entries(form.phaseMerkOnderdeel)) {
    const templatePhase = template?.phases.find((p) => p.key === key)
    if (!templatePhase || value !== templatePhase.merkOnderdeel) {
      merkOnderdeelByKey[key] = value
    }
  }
  const hasMerk = Object.keys(merkOnderdeelByKey).length > 0
  const hasDisabled = form.disabledKeys.length > 0
  if (!hasMerk && !hasDisabled) return undefined
  return {
    ...(hasDisabled ? { disabledKeys: form.disabledKeys } : {}),
    ...(hasMerk ? { merkOnderdeelByKey } : {}),
  }
}

const sectionTitleClass = 'm-0 text-[18px] leading-[21px] font-semibold text-ce-blue'
const phaseRowLinkClass =
  'cursor-pointer border-0 bg-transparent p-0 text-[14px] font-normal leading-[18px] text-ce-blue no-underline hover:underline decoration-[1.5px] underline-offset-[3px]'

function errorMessage(err: unknown): string {
  if (err instanceof ApiError) return err.message
  if (err instanceof Error) return err.message
  return 'Verzoek mislukt'
}

const bodyClass = 'm-0 text-[14px] leading-[18px] text-ce-black'
const hintClass = 'm-0 text-[14px] leading-[18px] text-ce-beige'
/** Stap-intro + instructieregels: zwart, dicht op de kop. */
const stepLeadClass = 'm-0 text-[14px] leading-[18px] text-ce-black'
const stepQuestionClass = 'm-0 text-[18px] leading-[21px] font-semibold text-ce-black'

export function campagneStapPath(step: number, campaignId?: string | null): string {
  const stap = Math.min(7, Math.max(1, Math.round(step)))
  if (campaignId) return `/campagnes/${campaignId}/stap/${stap}`
  return `/campagnes/nieuw/stap/${stap}`
}

function campagneRouteStep(pathname: string, wizardStep?: string): { overview: boolean; step: number } {
  if (pathname === '/campagnes' || pathname === '/campagnes/') {
    return { overview: true, step: 0 }
  }
  const n = Number.parseInt(wizardStep ?? '', 10)
  return { overview: false, step: Number.isFinite(n) ? Math.min(7, Math.max(1, n)) : 1 }
}

/** Redirect /campagnes/:id → /campagnes/:id/stap/7 */
export function CampagneRedirectToStap({ defaultStep = '7' }: { defaultStep?: string }) {
  const { campaignId } = useParams()
  if (!campaignId || campaignId === 'nieuw') {
    return <Navigate to="/campagnes/nieuw/stap/1" replace />
  }
  return <Navigate to={`/campagnes/${campaignId}/stap/${defaultStep}`} replace />
}

function emptyBankDraftRows(): CampagneBankRow[] {
  return [{ id: `new-${Date.now()}`, text: '' }]
}

export function CampagnePage() {
  const navigate = useNavigate()
  const location = useLocation()
  const { campaignId: routeCampaignId, wizardStep } = useParams()
  const { overview: isOverview, step } = campagneRouteStep(location.pathname, wizardStep)
  const isNewWizardRoute = location.pathname.startsWith('/campagnes/nieuw')
  const routeId =
    routeCampaignId && routeCampaignId !== 'nieuw' && !isNewWizardRoute ? routeCampaignId : null
  const [form, setForm] = useState<FormState>(() => emptyForm())
  const [templates, setTemplates] = useState<CampagneTemplate[]>(CAMPAGNE_TEMPLATE_FALLBACKS)
  const [bankVragen, setBankVragen] = useState<CampagneBankVraag[]>([])
  const [bankFeiten, setBankFeiten] = useState<CampagneBankFeit[]>([])
  const [campaigns, setCampaigns] = useState<CampagnePreviewCampaign[]>([])
  const [campaignId, setCampaignId] = useState<string | null>(null)
  const [slots, setSlots] = useState<CampagnePreviewSlot[]>([])
  const [planCheck, setPlanCheck] = useState<CampagnePlanCheck | null>(null)
  const [planProgress, setPlanProgress] = useState<CampagnePlanProgress[]>([])
  const [progressLabel, setProgressLabel] = useState<string | null>(null)
  const [planDecisions, setPlanDecisions] = useState<CampagnePlanDecisions>({})
  const recheckLock = useRef(false)
  const recheckAgain = useRef(false)
  const loadedRouteCampaignRef = useRef<string | null>(null)
  const hydrateSeq = useRef(0)
  const [productExtract, setProductExtract] = useState<CampagneProductExtract | null>(null)
  const [extractError, setExtractError] = useState<string | null>(null)
  const [previewStatus, setPreviewStatus] = useState<string>('concept')
  const [loading, setLoading] = useState(true)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [sellFitHint, setSellFitHint] = useState<string | null>(null)
  const [draftVraagRows, setDraftVraagRows] = useState<CampagneBankRow[]>([{ id: 'draft-new', text: '' }])
  const [draftFeitRows, setDraftFeitRows] = useState<CampagneBankRow[]>([{ id: 'draft-new', text: '' }])
  const [removedBankVraagIds, setRemovedBankVraagIds] = useState<string[]>([])
  const [removedBankFeitIds, setRemovedBankFeitIds] = useState<string[]>([])
  const [editingPhaseKey, setEditingPhaseKey] = useState<string | null>(null)
  const [busyCampaignId, setBusyCampaignId] = useState<string | null>(null)
  const [missingWordsInput, setMissingWordsInput] = useState('')

  const loadCatalog = useCallback(async () => {
    setLoading(true)
    setError(null)
    if (!isApiConfigured()) {
      setTemplates(CAMPAGNE_TEMPLATE_FALLBACKS)
      setBankVragen([])
      setBankFeiten([])
      setCampaigns([])
      setLoading(false)
      return
    }
    const [tplResult, bankResult, listResult] = await Promise.allSettled([
      fetchCampagneTemplates(),
      fetchCampagneBank(),
      listCampagnePreviews(),
    ])
    const loaded = tplResult.status === 'fulfilled' ? (tplResult.value.templates ?? []) : []
    setTemplates(loaded.length > 0 ? loaded : CAMPAGNE_TEMPLATE_FALLBACKS)
    setSellFitHint(tplResult.status === 'fulfilled' ? (tplResult.value.sellFitHint ?? null) : null)
    setPlanProgress(tplResult.status === 'fulfilled' ? (tplResult.value.planProgress ?? []) : [])
    setBankVragen(
      bankResult.status === 'fulfilled'
        ? (bankResult.value.vragen ?? []).filter((q) => q.vraag.trim() !== '')
        : [],
    )
    setBankFeiten(
      bankResult.status === 'fulfilled'
        ? (bankResult.value.feiten ?? []).filter((f) => f.formulering.trim() !== '')
        : [],
    )
    setCampaigns(listResult.status === 'fulfilled' ? (listResult.value.campaigns ?? []) : [])
    const firstFail = [tplResult, bankResult, listResult].find((r) => r.status === 'rejected')
    if (firstFail && firstFail.status === 'rejected') {
      setError(errorMessage(firstFail.reason))
    }
    setLoading(false)
  }, [])

  useEffect(() => {
    void loadCatalog()
  }, [loadCatalog])

  function resetWizardPlanState() {
    setCampaignId(null)
    setSlots([])
    setPlanCheck(null)
    setPlanDecisions({})
    setProgressLabel(null)
    setProductExtract(null)
    setExtractError(null)
    setPreviewStatus('concept')
    loadedRouteCampaignRef.current = null
  }

  useEffect(() => {
    if (isOverview || isNewWizardRoute) {
      if (campaignId != null && isNewWizardRoute) {
        resetWizardPlanState()
      }
      if (isOverview) {
        loadedRouteCampaignRef.current = null
      }
    }
    // Only react to route shape — not campaignId (would loop).
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isOverview, isNewWizardRoute, location.pathname])

  const hydrateCampaign = useCallback(async (id: string) => {
    const seq = ++hydrateSeq.current
    setBusy(true)
    setError(null)
    try {
      const detail = await fetchCampagnePreview(id)
      if (seq !== hydrateSeq.current) return
      const campaign = detail.campaign
      const channels = campaign.channels ?? {}
      setCampaignId(campaign.id)
      setSlots(detail.slots ?? [])
      setPlanCheck(campaign.planCheck)
      setPlanDecisions(campaign.planDecisions ?? {})
      setProgressLabel(null)
      setProductExtract(campaign.productExtract)
      setExtractError(null)
      setPreviewStatus(campaign.previewStatus)
      setForm({
        templateKey: modeFromTemplate(campaign.templateKey),
        title: campaign.title,
        productPageUrl: campaign.productPageUrl ?? '',
        goal: (['verkopen', 'merk', 'beide'].includes(campaign.goal ?? '')
          ? campaign.goal
          : 'beide') as CampagneGoal,
        startDate: campaign.startDate ?? '',
        endDate: campaign.endDate ?? '',
        weekday: 2,
        linkedin: String(channels.linkedin ?? 0),
        instagram: String(channels.instagram ?? 0),
        disabledKeys: campaign.phasePlan?.disabledKeys ?? [],
        geoQuestionIds: campaign.geoQuestionIds ?? [],
        vraagIds: campaign.vraagIds ?? [],
        feitIds: campaign.feitIds ?? [],
        merkOnderdelen:
          campaign.merkOnderdelen && campaign.merkOnderdelen.length > 0
            ? campaign.merkOnderdelen
            : [...CAMPAGNE_MERK_ONDERDELEN],
        phaseMerkOnderdeel: { ...(campaign.phasePlan?.merkOnderdeelByKey ?? {}) },
      })
    } catch (err) {
      if (seq !== hydrateSeq.current) return
      setError(errorMessage(err))
    } finally {
      if (seq === hydrateSeq.current) setBusy(false)
    }
  }, [])

  useEffect(() => {
    if (isOverview || isNewWizardRoute || !routeId) return
    if (loadedRouteCampaignRef.current === routeId) return
    loadedRouteCampaignRef.current = routeId
    void hydrateCampaign(routeId)
  }, [isOverview, isNewWizardRoute, routeId, hydrateCampaign])

  useEffect(() => {
    if (step !== 3) return
    setDraftVraagRows(
      bankVragen.length > 0
        ? bankVragen.map((q) => ({ id: q.id, text: q.vraag }))
        : emptyBankDraftRows(),
    )
    setDraftFeitRows(
      bankFeiten.length > 0
        ? bankFeiten.map((f) => ({ id: f.id, text: f.formulering }))
        : emptyBankDraftRows(),
    )
    setRemovedBankVraagIds([])
    setRemovedBankFeitIds([])
  }, [step, bankVragen, bankFeiten])

  const activeTemplate = useMemo(
    () => templates.find((t) => t.key === form.templateKey) ?? templates[0] ?? null,
    [templates, form.templateKey],
  )
  const activePhases = useMemo(
    () => (activeTemplate?.phases ?? []).filter((p) => !form.disabledKeys.includes(p.key)),
    [activeTemplate, form.disabledKeys],
  )
  const totalPosts = parseCount(form.linkedin) + parseCount(form.instagram)
  const weekdayLabel = WEEKDAYS[form.weekday - 1] ?? 'Dinsdag'
  const copy = STEP_COPY[step - 1]
  /** Actieve campagne uit URL (bestaand) of state na Maak plan op /nieuw → redirect. */
  const activeCampaignId = routeId ?? (isNewWizardRoute ? null : campaignId)
  const canEditWizard = previewStatus === 'concept'
  const planReady = activeCampaignId != null && slots.length > 0

  function trackRemovedBankId(kind: 'vraag' | 'feit', id: string) {
    if (id.startsWith('new-') || id.startsWith('draft-') || id === 'draft-new') return
    if (kind === 'vraag') {
      setRemovedBankVraagIds((prev) => (prev.includes(id) ? prev : [...prev, id]))
    } else {
      setRemovedBankFeitIds((prev) => (prev.includes(id) ? prev : [...prev, id]))
    }
  }

  function remapSelectedIds(ids: string[], idMap: Record<string, string> | undefined): string[] {
    if (!idMap) return ids
    return ids.map((id) => idMap[id] ?? id)
  }

  async function persistBank(
    feitRowsOverride?: CampagneBankRow[],
  ): Promise<{ ok: true; vraagIds: string[]; feitIds: string[] } | { ok: false }> {
    if (!isApiConfigured()) {
      return { ok: true, vraagIds: form.vraagIds, feitIds: form.feitIds }
    }
    setError(null)
    try {
      const feitSource = feitRowsOverride ?? draftFeitRows
      const vragenPayload = draftVraagRows
        .filter((row) => row.text.trim() !== '')
        .map((row) => ({
          id: row.id,
          vraag: row.text.trim(),
          clientRowId: row.id,
        }))
      const feitenPayload = feitSource
        .filter((row) => row.text.trim() !== '')
        .map((row) => ({
          id: row.id,
          formulering: row.text.trim(),
          clientRowId: row.id,
        }))
      const saved = await saveCampagneBank({
        vragen: vragenPayload,
        feiten: feitenPayload,
        removedVraagIds: removedBankVraagIds,
        removedFeitIds: removedBankFeitIds,
      })
      setBankVragen((saved.vragen ?? []).filter((q) => q.vraag.trim() !== ''))
      setBankFeiten((saved.feiten ?? []).filter((f) => f.formulering.trim() !== ''))
      if (feitRowsOverride) {
        setDraftFeitRows(
          (saved.feiten ?? []).length > 0
            ? (saved.feiten ?? []).map((f) => ({ id: f.id, text: f.formulering }))
            : emptyBankDraftRows(),
        )
      }
      const vraagIds = remapSelectedIds(form.vraagIds, saved.idMap).filter((id) =>
        (saved.vragen ?? []).some((q) => q.id === id),
      )
      const feitIds = remapSelectedIds(form.feitIds, saved.idMap).filter((id) =>
        (saved.feiten ?? []).some((f) => f.id === id),
      )
      setForm((prev) => ({ ...prev, vraagIds, feitIds }))
      setRemovedBankVraagIds([])
      setRemovedBankFeitIds([])
      return { ok: true as const, vraagIds, feitIds }
    } catch (err) {
      setError(errorMessage(err))
      return { ok: false as const }
    }
  }

  async function syncCampaignAnkers(vraagIds: string[], feitIds: string[]): Promise<boolean> {
    if (!activeCampaignId || !canEditWizard) return true
    try {
      const detail = await patchCampagnePreview(activeCampaignId, { vraagIds, feitIds })
      setSlots(detail.slots ?? [])
      if (detail.campaign?.planCheck) setPlanCheck(detail.campaign.planCheck)
      return true
    } catch (err) {
      setError(errorMessage(err))
      return false
    }
  }

  async function syncCampaignPhases(): Promise<boolean> {
    if (!activeCampaignId || !canEditWizard) return true
    try {
      const phasePlan = buildPhasePlan(form, activeTemplate) ?? { disabledKeys: [] }
      const detail = await patchCampagnePreview(activeCampaignId, {
        phasePlan,
        reapplyPhases: true,
      })
      setSlots(detail.slots ?? [])
      if (detail.campaign?.phasePlan) {
        setForm((prev) => ({
          ...prev,
          disabledKeys: detail.campaign?.phasePlan?.disabledKeys ?? prev.disabledKeys,
          phaseMerkOnderdeel: {
            ...(detail.campaign?.phasePlan?.merkOnderdeelByKey ?? {}),
          },
        }))
      }
      return true
    } catch (err) {
      setError(errorMessage(err))
      return false
    }
  }

  async function goNextStep() {
    if (step === 3) {
      const mergedFeiten = feitRowsWithCommaWords(missingWordsInput, draftFeitRows)
      if (missingWordsInput.trim() !== '') {
        setMissingWordsInput('')
        setDraftFeitRows(mergedFeiten)
      }
      setBusy(true)
      const bankResult = await persistBank(mergedFeiten)
      if (!bankResult.ok) {
        setBusy(false)
        return
      }
      if (activeCampaignId) {
        const ankersOk = await syncCampaignAnkers(bankResult.vraagIds, bankResult.feitIds)
        setBusy(false)
        if (!ankersOk) return
      } else {
        setBusy(false)
      }
    }
    if (step === 6 && activeCampaignId) {
      setBusy(true)
      const ok = await syncCampaignPhases()
      setBusy(false)
      if (!ok) return
      if (activeCampaignId) void runStagedCheck(activeCampaignId)
    }
    setError(null)
    navigate(campagneStapPath(step + 1, activeCampaignId))
  }

  async function removeCampaign(id: string) {
    setBusyCampaignId(id)
    setBusy(true)
    setError(null)
    try {
      await deleteCampagnePreview(id)
      await loadCatalog()
    } catch (err) {
      setError(errorMessage(err))
    } finally {
      setBusyCampaignId(null)
      setBusy(false)
    }
  }

  async function archiveCampaign(id: string) {
    setBusyCampaignId(id)
    setBusy(true)
    setError(null)
    try {
      await archiveCampagnePreview(id)
      await loadCatalog()
    } catch (err) {
      setError(errorMessage(err))
    } finally {
      setBusyCampaignId(null)
      setBusy(false)
    }
  }

  const campaignWeekGroups = useMemo(() => groupCampagnesByStartWeek(campaigns), [campaigns])

  function editPlanSection(section: PlanReviewSectionKey) {
    if (section === 'gaps') {
      navigate(campagneStapPath(3, activeCampaignId))
      return
    }
    if (section === 'campaign') {
      navigate(campagneStapPath(6, activeCampaignId))
      return
    }
    // Advies / Check vooraf: blijven op plan-stap
    navigate(campagneStapPath(7, activeCampaignId))
  }

  function startNew() {
    const firstVraag = bankVragen[0]?.id
    const firstFeit = bankFeiten[0]?.id
    setForm(emptyForm([], firstVraag ? [firstVraag] : [], firstFeit ? [firstFeit] : []))
    resetWizardPlanState()
    setError(null)
    setEditingPhaseKey(null)
    setMissingWordsInput('')
    navigate('/campagnes/nieuw/stap/1')
  }

  function backToList() {
    resetWizardPlanState()
    setError(null)
    setEditingPhaseKey(null)
    navigate('/campagnes')
    void loadCatalog()
  }

  function openExisting(id: string) {
    loadedRouteCampaignRef.current = null
    navigate(campagneStapPath(7, id))
  }

  function channelProblem(): string | null {
    if (totalPosts > 100) return 'Maximaal 100 posts in één campagne.'
    if (totalPosts > 0 && activePhases.length > 0 && totalPosts < activePhases.length) {
      return `Te weinig posts (${totalPosts}) voor ${activePhases.length} fases.`
    }
    return null
  }

  function canContinue(current: number): boolean {
    if (current === 2) return form.title.trim() !== ''
    if (current === 3) {
      const filledVragen = draftVraagRows.filter((r) => r.text.trim() !== '')
      const filledFeiten = draftFeitRows.filter((r) => r.text.trim() !== '')
      const vragenOk =
        form.vraagIds.length <= 3 && (filledVragen.length === 0 || form.vraagIds.length >= 1)
      const feitenOk =
        form.feitIds.length <= 3 && (filledFeiten.length === 0 || form.feitIds.length >= 1)
      return vragenOk && feitenOk && form.merkOnderdelen.length > 0
    }
    if (current === 4) return form.startDate !== '' && form.endDate !== '' && form.startDate <= form.endDate
    if (current === 5) return totalPosts > 0 && channelProblem() === null
    if (current === 6) return activePhases.length > 0
    if (current === 7) return activePhases.length > 0 && totalPosts >= activePhases.length && totalPosts <= 100
    return true
  }

  function toggleId(list: string[], id: string, max: number): string[] {
    if (list.includes(id)) return list.filter((x) => x !== id)
    if (list.length >= max) return list
    return [...list, id]
  }

  function toggleVraag(id: string) {
    setForm((prev) => ({ ...prev, vraagIds: toggleId(prev.vraagIds, id, 3) }))
  }

  function toggleFeit(id: string) {
    setForm((prev) => ({ ...prev, feitIds: toggleId(prev.feitIds, id, 3) }))
  }

  function toggleOnderdeel(key: string) {
    setForm((prev) => {
      const has = prev.merkOnderdelen.includes(key)
      if (has) {
        if (prev.merkOnderdelen.length <= 1) return prev
        return { ...prev, merkOnderdelen: prev.merkOnderdelen.filter((x) => x !== key) }
      }
      return { ...prev, merkOnderdelen: [...prev.merkOnderdelen, key] }
    })
  }

  function togglePhase(key: string) {
    setEditingPhaseKey((current) => (current === key ? null : current))
    setForm((prev) => {
      const disabled = prev.disabledKeys.includes(key)
      if (disabled) return { ...prev, disabledKeys: prev.disabledKeys.filter((k) => k !== key) }
      const remaining = (activeTemplate?.phases ?? []).filter(
        (p) => p.key !== key && !prev.disabledKeys.includes(p.key),
      )
      if (remaining.length === 0) return prev
      return { ...prev, disabledKeys: [...prev.disabledKeys, key] }
    })
  }

  function setPhaseMerkOnderdeel(phaseKey: string, merkKey: string | null) {
    setForm((prev) => {
      const templatePhase = activeTemplate?.phases.find((p) => p.key === phaseKey)
      const next = { ...prev.phaseMerkOnderdeel }
      if (templatePhase && merkKey === templatePhase.merkOnderdeel) {
        delete next[phaseKey]
      } else {
        next[phaseKey] = merkKey
      }
      return { ...prev, phaseMerkOnderdeel: next }
    })
  }

  async function runStagedCheck(id: string) {
    if (recheckLock.current) {
      recheckAgain.current = true
      return
    }
    recheckLock.current = true
    setBusy(true)
    try {
      do {
        recheckAgain.current = false
        setProgressLabel(progressLabelFor(planProgress, 'check'))
        const checked = await runCampagnePlanCheck(id, 'check')
        setSlots(checked.slots ?? [])
        setPlanCheck(checked.planCheck ?? checked.campaign?.planCheck ?? null)
        setProgressLabel(progressLabelFor(planProgress, 'posts'))
        const judged = await runCampagnePlanCheck(id, 'posts')
        setSlots(judged.slots ?? [])
        setPlanCheck(judged.planCheck ?? judged.campaign?.planCheck ?? null)
        if (judged.campaign?.planDecisions) setPlanDecisions(judged.campaign.planDecisions)
      } while (recheckAgain.current)
    } finally {
      recheckLock.current = false
      setProgressLabel(null)
      setBusy(false)
    }
  }

  async function makePlan() {
    if (!canContinue(7)) {
      setError(
        totalPosts < activePhases.length
          ? `Te weinig posts (${totalPosts}) voor ${activePhases.length} fases.`
          : 'Vul de ontbrekende velden in.',
      )
      return
    }
    setBusy(true)
    setError(null)
    try {
      setProgressLabel(progressLabelFor(planProgress, 'plan'))
      const created = await createCampagnePreview({
        title: form.title.trim(),
        startDate: form.startDate,
        endDate: form.endDate,
        channels: channelsFromForm(form),
        goal: form.templateKey === 'lancering' ? 'verkopen' : 'merk',
        templateKey: form.templateKey,
        phasePlan: buildPhasePlan(form, activeTemplate),
        schedule: form.templateKey === 'serie' ? { mode: 'weekly', weekday: form.weekday } : undefined,
        productPageUrl:
          form.templateKey === 'lancering' && form.productPageUrl.trim() !== ''
            ? form.productPageUrl.trim()
            : undefined,
        vraagIds: form.vraagIds,
        feitIds: form.feitIds,
        merkOnderdelen: form.merkOnderdelen,
      })
      setCampaignId(created.campaignId)
      setSlots(created.slots ?? [])
      setPreviewStatus(created.campaign?.previewStatus ?? 'concept')
      setProductExtract(created.campaign?.productExtract ?? created.productExtract?.extract ?? null)
      setExtractError(
        created.productExtract && created.productExtract.ok === false
          ? (created.productExtract.error ?? 'Productpagina kon niet worden gelezen')
          : null,
      )
      await runStagedCheck(created.campaignId)
      loadedRouteCampaignRef.current = created.campaignId
      navigate(campagneStapPath(7, created.campaignId), { replace: true })
    } catch (err) {
      setError(errorMessage(err))
      setProgressLabel(null)
      setBusy(false)
    }
  }

  async function applyPlanChoice(
    body: Parameters<typeof patchCampagnePreview>[1],
    recheck: boolean,
  ) {
    if (!activeCampaignId) return
    setBusy(true)
    setError(null)
    try {
      const detail = await patchCampagnePreview(activeCampaignId, body)
      setSlots(detail.slots ?? [])
      setPlanDecisions(detail.campaign?.planDecisions ?? {})
      if (recheck) await runStagedCheck(activeCampaignId)
      else setBusy(false)
    } catch (err) {
      setError(errorMessage(err))
      setBusy(false)
    }
  }

  async function confirmPlan() {
    if (!activeCampaignId) return
    setBusy(true)
    setError(null)
    try {
      await patchCampagnePreview(activeCampaignId, { previewStatus: 'bevestigd' })
      navigate('/agenda')
    } catch (err) {
      setError(errorMessage(err))
      setBusy(false)
    }
  }

  let main: ReactNode
  if (loading) {
    main = <p className={bodyClass}>Laden…</p>
  } else if (isOverview) {
    main = (
      <div className={designStackClass}>
        <p className={bodyClass}>
          Een campagne plant thema's over een periode, per kanaal, met dezelfde merkopdracht. Kies Touch, Tell of Sell.
        </p>
        {campaigns.length > 0 ? (
          <>
            {campaignWeekGroups.map((group) => (
              <section key={group.key} className={LIST_SECTION.sectionClass}>
                <h2 className={`${LIST_SECTION.headerClass} whitespace-normal break-words text-ce-black`}>
                  {group.weekLabel}
                  {group.weekRange ? (
                    <span className="text-[14px] leading-[18px] font-normal"> _ {group.weekRange}</span>
                  ) : null}
                </h2>
                <div className={LIST_SECTION.bodyClass}>
                  <div className={LIST_SECTION.bodyStackClass}>
                    {group.campaigns.map((campaign) => (
                      <CampagneListRow
                        key={campaign.id}
                        title={campaign.title}
                        phaseLabel={campagnePhaseLabel(campaign)}
                        onEdit={() => void openExisting(campaign.id)}
                        onDelete={() => void removeCampaign(campaign.id)}
                        onArchive={() => void archiveCampaign(campaign.id)}
                        busy={busyCampaignId === campaign.id}
                      />
                    ))}
                  </div>
                </div>
              </section>
            ))}
          </>
        ) : (
          <p className={hintClass}>Nog geen campagnes.</p>
        )}
        {error && <p className="m-0 text-[14px] leading-[18px] text-ce-red">{error}</p>}
        <div className={pillActionsRowClass}>
          <PillButton variant="main" disabled={busy} onClick={startNew}>
            Nieuwe campagne
          </PillButton>
        </div>
      </div>
    )
  } else {
    const modeLabel = TEMPLATE_PILL_LABELS[form.templateKey] ?? 'Sell'
    main = (
      <div className={designStackClass}>
        <div className="flex flex-col gap-[8px]">
          <p className={stepLeadClass}>
            Stap {step} van 7 · {copy?.title}
          </p>
          <h2 className={stepQuestionClass}>
            {step === 2 ? `Waar gaat de ${modeLabel}-campagne over?` : copy?.question}
          </h2>
          {step === 3 ? (
            <p className={stepLeadClass}>
              Merkonderdelen — de fases stellen voor, click cirkels aan om te selecteren.
            </p>
          ) : null}
        </div>

        {step === 1 && (
          <Pulldown
            options={MODE_ORDER.map((key) => TEMPLATE_PILL_LABELS[key])}
            value={modeLabel}
            onChange={(label) => {
              const key = MODE_ORDER.find((item) => TEMPLATE_PILL_LABELS[item] === label)
              if (!key) return
              setForm((prev) => applyTemplateDefaults(prev, key))
              setError(null)
              navigate('/campagnes/nieuw/stap/2')
            }}
          />
        )}

        {step === 2 && (
          <>
            <FieldSingle
              id="campagne-title"
              variant="title"
              showPencil
              placeholderTone="blue"
              value={form.title}
              onChange={(title) => setForm((prev) => ({ ...prev, title }))}
              placeholder={form.templateKey === 'serie' ? 'Naam van de serie' : 'Onderwerp van de campagne'}
            />
            {form.templateKey === 'lancering' && sellFitHint ? (
              <p className={bodyClass}>{sellFitHint}</p>
            ) : null}
            {form.templateKey === 'lancering' && (
              <FieldSingle
                id="campagne-url"
                value={form.productPageUrl}
                onChange={(productPageUrl) => setForm((prev) => ({ ...prev, productPageUrl }))}
                placeholder="Productpagina (URL)"
                placeholderTone="black"
              />
            )}
          </>
        )}

        {step === 3 && (
          <>
            <div className="-mt-[8px] flex flex-col gap-[10px]">
              {CAMPAGNE_MERK_ONDERDELEN.map((key) => (
                <CheckboxOption
                  key={key}
                  checked={form.merkOnderdelen.includes(key)}
                  onChange={() => toggleOnderdeel(key)}
                  label={merkOnderdeelLabel(key) ?? key}
                />
              ))}
            </div>
            <div className={borderedSectionStackClass}>
              <StrategySection
                title="Vragen"
                hint="Kies 1 tot 3 vragen voor ankers in de campagne. Bewerk of voeg toe — het wordt opgeslagen in je merkbank."
                hintTone="black"
                filled={draftVraagRows.some((r) => r.text.trim() !== '')}
              >
                <CampagneBankEditor
                  kind="vraag"
                  rows={draftVraagRows}
                  selectedIds={form.vraagIds}
                  maxSelected={3}
                  onChange={setDraftVraagRows}
                  onToggleSelect={toggleVraag}
                  onRemoveRow={(id) => trackRemovedBankId('vraag', id)}
                  disabled={busy}
                />
              </StrategySection>
              <StrategySection
                title="Feiten"
                hint="Kies 1 tot 3 feiten. Bewerk of voeg toe — het wordt opgeslagen in je merkbank."
                hintTone="black"
                filled={draftFeitRows.some((r) => r.text.trim() !== '')}
              >
                <CampagneBankEditor
                  kind="feit"
                  rows={draftFeitRows}
                  selectedIds={form.feitIds}
                  maxSelected={3}
                  onChange={setDraftFeitRows}
                  onToggleSelect={toggleFeit}
                  onRemoveRow={(id) => trackRemovedBankId('feit', id)}
                  disabled={busy}
                />
              </StrategySection>
              <StrategySection
                title="Woorden"
                hint="Woorden of korte zinnen voor je feitenbank. Gescheiden door komma's — bij Verder worden ze opgeslagen als feiten."
                hintTone="black"
                filled={missingWordsInput.trim() !== ''}
              >
                <CommaWordsField
                  id="campagne-missing-words"
                  value={missingWordsInput}
                  onChange={setMissingWordsInput}
                  disabled={busy}
                />
              </StrategySection>
            </div>
          </>
        )}

        {step === 4 && (
          <>
            <FieldSingle
              id="campagne-start"
              type="date"
              label="Start"
              value={form.startDate}
              onChange={(startDate) => setForm((prev) => ({ ...prev, startDate }))}
              placeholder="Startdatum"
            />
            <FieldSingle
              id="campagne-end"
              type="date"
              label="Einde"
              value={form.endDate}
              onChange={(endDate) => setForm((prev) => ({ ...prev, endDate }))}
              placeholder="Einddatum"
            />
            {form.templateKey === 'serie' && (
              <div>
                <p className="mb-[6px] px-[30px] text-[14px] leading-[18px] font-semibold text-ce-black">Weekdag</p>
                <Pulldown
                  options={[...WEEKDAYS]}
                  value={weekdayLabel}
                  onChange={(label) => {
                    const index = WEEKDAYS.indexOf(label as (typeof WEEKDAYS)[number])
                    if (index >= 0) setForm((prev) => ({ ...prev, weekday: index + 1 }))
                  }}
                />
              </div>
            )}
            <PeriodAgendaOverview
              startDate={form.startDate}
              endDate={form.endDate}
              className="mt-[18px]"
            />
          </>
        )}

        {step === 5 && (
          <>
            <FieldSingle
              id="campagne-li"
              label="LinkedIn"
              value={form.linkedin}
              selectOnFocus
              onChange={(linkedin) => setForm((prev) => ({ ...prev, linkedin }))}
              placeholder="Aantal posts"
            />
            <FieldSingle
              id="campagne-ig"
              label="Instagram"
              value={form.instagram}
              selectOnFocus
              onChange={(instagram) => setForm((prev) => ({ ...prev, instagram }))}
              placeholder="Aantal posts"
            />
            {channelProblem() ? <p className="m-0 text-[14px] leading-[18px] text-ce-red">{channelProblem()}</p> : null}
          </>
        )}

        {step === 6 && (
          <ul className={`m-0 list-none p-0 ${LIST_SECTION.bodyStackClass}`}>
            {(activeTemplate?.phases ?? []).map((phase) => {
              const disabled = form.disabledKeys.includes(phase.key)
              const merkKey = phaseMerkOnderdeelFor(phase, form.phaseMerkOnderdeel)
              const merkLabel = merkOnderdeelLabel(merkKey)
              const editing = editingPhaseKey === phase.key
              const onderdeelOptionKeys = [
                ...new Set([
                  ...form.merkOnderdelen,
                  ...(merkKey && !form.merkOnderdelen.includes(merkKey) ? [merkKey] : []),
                ]),
              ]
              const onderdeelOptions = onderdeelOptionKeys.map((k) => merkOnderdeelLabel(k) ?? k)
              const canRemove =
                !disabled &&
                (activeTemplate?.phases ?? []).filter(
                  (p) => p.key !== phase.key && !form.disabledKeys.includes(p.key),
                ).length > 0
              return (
                <li key={phase.key} className={LIST_SECTION.itemClass}>
                  <p className={`${sectionTitleClass} ${disabled ? '!text-ce-beige' : ''}`}>{phase.label}</p>
                  <div className="mt-[6px] flex flex-wrap items-baseline justify-between gap-x-[12px] gap-y-[8px]">
                    <p className={`${bodyClass} m-0 min-w-0`}>
                      {Math.round(phase.share * 100)}% · {phase.rol === 'merk' ? 'Merk' : 'Activatie'}
                      {merkLabel ? ` · ${merkLabel}` : ''}
                    </p>
                    {canEditWizard && (
                      <div className="flex shrink-0 items-baseline whitespace-nowrap font-normal">
                        <button
                          type="button"
                          disabled={disabled}
                          className={`${phaseRowLinkClass} ${disabled ? 'cursor-not-allowed opacity-45' : ''}`}
                          onClick={() => setEditingPhaseKey((k) => (k === phase.key ? null : phase.key))}
                        >
                          Bewerken
                        </button>
                        <ActionSep dimmed={false} />
                        <button
                          type="button"
                          disabled={disabled ? false : !canRemove}
                          className={`${phaseRowLinkClass} ${!disabled && !canRemove ? 'cursor-not-allowed opacity-45' : ''}`}
                          onClick={() => togglePhase(phase.key)}
                        >
                          {disabled ? 'Terugzetten' : 'Verwijderen'}
                        </button>
                      </div>
                    )}
                  </div>
                  {editing && canEditWizard && !disabled ? (
                    <div className="mt-[8px]">
                      <p className="mb-[6px] px-[30px] text-[14px] leading-[18px] font-semibold text-ce-black">
                        Merkonderdeel
                      </p>
                      <Pulldown
                        options={onderdeelOptions}
                        value={merkLabel ?? onderdeelOptions[0] ?? ''}
                        onChange={(label) => {
                          const key =
                            CAMPAGNE_MERK_ONDERDELEN.find((k) => merkOnderdeelLabel(k) === label) ??
                            onderdeelOptionKeys.find((k) => merkOnderdeelLabel(k) === label) ??
                            null
                          setPhaseMerkOnderdeel(phase.key, key)
                        }}
                      />
                    </div>
                  ) : null}
                </li>
              )
            })}
          </ul>
        )}

        {step === 7 && !planReady && (
          <div className={pillActionsRowClass}>
            <PillButton variant="main" disabled={busy || !canContinue(7)} loading={busy} onClick={() => void makePlan()}>
              Maak plan
            </PillButton>
          </div>
        )}

        {step === 7 && (
          <PlanReview
            slots={slots}
            planCheck={planCheck}
            productExtract={productExtract}
            extractError={extractError}
            progressLabel={progressLabel}
            decisions={planDecisions}
            planReady={planReady}
            canEdit={activeCampaignId != null && canEditWizard}
            onEditSection={editPlanSection}
            onPlace={(slotId, publishDate, _publishTime, platform) => {
              setSlots((prev) =>
                prev.map((s) =>
                  s.slotId === slotId
                    ? {
                        ...s,
                        publishDate,
                        publishTime: null,
                        platformKey: platform,
                        platform: platform === 'instagram' ? 'Instagram' : 'LinkedIn',
                      }
                    : s,
                ),
              )
              void scheduleAgendaSlot(slotId, { publishDate, publishTime: null, platform })
                .then(() => (activeCampaignId ? runStagedCheck(activeCampaignId) : undefined))
                .catch((err) => setError(errorMessage(err)))
            }}
            onMerkOnderdeel={(slotId, merkOnderdeel) => {
              setSlots((prev) =>
                prev.map((s) => (s.slotId === slotId ? { ...s, merkOnderdeel } : s)),
              )
              void applyPlanChoice({ slotMerkOnderdeel: { slotId, merkOnderdeel } }, true)
            }}
            onChoice={(body, recheck) => void applyPlanChoice(body, recheck)}
          />
        )}

        {error && <p className="m-0 text-[14px] leading-[18px] text-ce-red">{error}</p>}

        <div className={pillActionsRowWithNavLinkClass}>
          {step === 7 && planReady && previewStatus === 'concept' && (
            <PillButton variant="main" disabled={busy} loading={busy} onClick={() => void confirmPlan()}>
              Voer uit
            </PillButton>
          )}
          {step === 7 && planReady && previewStatus !== 'concept' && (
            <PillButton variant="main" onClick={() => navigate('/agenda')}>
              Voer uit
            </PillButton>
          )}
          {step > 1 && step < 7 && (
            <PillButton variant="main" disabled={busy || !canContinue(step)} onClick={() => void goNextStep()}>
              Verder
            </PillButton>
          )}
          {step > 1 && (
            <PillButton
              variant="second"
              disabled={busy}
              onClick={() => {
                setError(null)
                navigate(campagneStapPath(step - 1, activeCampaignId))
              }}
            >
              Terug
            </PillButton>
          )}
          {step > 1 && (
            <button type="button" className={`${pageNavLinkClass} border-0 bg-transparent px-0`} onClick={backToList}>
              Overzicht
            </button>
          )}
        </div>
      </div>
    )
  }

  return (
    <GridShell
      pageTitle="Campagne"
      pageSubtitle="Plan over een periode, per kanaal"
      activeNav="campagne"
      mainLayout={isOverview ? 'default' : 'grid2'}
      mainContentMaxWidth={isOverview ? undefined : GRID2_REVIEW_MAIN_WIDTH}
      metaAlign={isOverview ? 'grid' : 'flush'}
      headerContentGap={isOverview ? undefined : 20}
      main={main}
      meta={
        <p className="m-0 text-[14px] leading-[18px] text-ce-black">
          {isOverview ? 'Campagne hoort in de kalender, niet als losse AI-posts.' : copy?.hint}
        </p>
      }
    />
  )
}


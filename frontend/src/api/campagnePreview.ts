import { apiFetch } from './client'
import type { LlmQuestionDto } from './llmQuestions'

export type CampagneTemplateKey = 'lancering' | 'serie' | 'touch'
export type CampagneGoal = 'verkopen' | 'merk' | 'beide'
export type CampagnePreviewStatus = 'concept' | 'bevestigd' | 'loopt' | 'afgerond' | 'verwijderd'

export type CampagnePhase = {
  key: string
  label: string
  share: number
  rol: string
  merkOnderdeel: string | null
  opdracht: string
}

export type CampagneTemplate = {
  key: CampagneTemplateKey
  label: string
  phases: CampagnePhase[]
}

/** Zelfde A/B-fases als `campagne-preview-templates.php` — fallback als de API niet laadt. */
export const CAMPAGNE_TEMPLATE_FALLBACKS: CampagneTemplate[] = [
  {
    key: 'lancering',
    label: 'Lancering product of productlijn',
    phases: [
      { key: 'aankondiging', label: 'Aankondiging', share: 0.15, rol: 'activatie', merkOnderdeel: 'wat_je_doet', opdracht: 'Leg uit wat het product is en voor wie het bedoeld is.' },
      { key: 'waarom', label: 'Waarom we dit maken', share: 0.2, rol: 'merk', merkOnderdeel: 'waarom', opdracht: 'Toon het doel of de tegenkracht die dit product adresseert.' },
      { key: 'hoe_werkt', label: 'Hoe het werkt', share: 0.2, rol: 'merk', merkOnderdeel: 'hoe_je_werkt', opdracht: 'Laat zien wat dit product anders maakt dan vergelijkbare producten in de branche.' },
      { key: 'bewust_niet', label: 'Wat we bewust niet doen', share: 0.1, rol: 'merk', merkOnderdeel: 'waar_je_nee_tegen_zegt', opdracht: 'Noem één bewuste keuze rond het product die laat zien waar jullie nee tegen zeggen.' },
      { key: 'bewijs', label: 'Bewijs', share: 0.2, rol: 'merk', merkOnderdeel: 'hoe_je_werkt', opdracht: 'Gebruik concreet bewijs: klant, maker of test.' },
      { key: 'actie', label: 'Actie', share: 0.15, rol: 'activatie', merkOnderdeel: null, opdracht: 'Roep op tot de gewenste actie: bestellen, proberen of aanmelden.' },
    ],
  },
  {
    key: 'serie',
    label: 'Serie of vaste rubriek',
    phases: [
      { key: 'vraag', label: 'Vraag', share: 0.34, rol: 'merk', merkOnderdeel: 'wat_je_doet', opdracht: 'Open de aflevering met de vraag of het hook van deze rubriek.' },
      { key: 'antwoord', label: 'Antwoord', share: 0.33, rol: 'merk', merkOnderdeel: 'hoe_je_werkt', opdracht: 'Beantwoord de vraag vanuit één vast merkonderdeel — geen algemene branchetips.' },
      { key: 'voorbeeld', label: 'Voorbeeld', share: 0.33, rol: 'merk', merkOnderdeel: 'hoe_je_werkt', opdracht: 'Sluit af met een concreet voorbeeld dat hetzelfde merkonderdeel laat zien.' },
    ],
  },
  {
    key: 'touch',
    label: 'Touch',
    phases: [
      { key: 'herkenning', label: 'Herkenning', share: 0.4, rol: 'merk', merkOnderdeel: 'wat_je_doet', opdracht: 'Laat in één beeld of zin zien wie jullie zijn. Kort, herkenbaar, geen opbouw.' },
      { key: 'anker', label: 'Anker/Inzicht', share: 0.35, rol: 'merk', merkOnderdeel: 'hoe_je_werkt', opdracht: 'Eén anker: één vraag of één feit. Geen argumentatie in stappen.' },
      { key: 'connect', label: 'Call-to-connect', share: 0.25, rol: 'merk', merkOnderdeel: 'waarom', opdracht: 'Nodig uit tot contact of herkenning, zonder verkooptaal of prijsaanbieding.' },
    ],
  },
]

export type CampagnePhasePlan = {
  disabledKeys?: string[]
  order?: string[]
  /** Per fase-key: override van sjabloon-merkonderdeel */
  merkOnderdeelByKey?: Record<string, string | null>
}

export type CampagneProductExtract = {
  wat?: string
  anders?: string
  bewijs?: string
  ontbreekt?: string
}

export type CampagnePlanCheckSignaal = {
  soort: string
  bron?: string
  tekst: string
  voorstel?: string
  signaal?: boolean
  status?: 'ok' | 'aandachtspunt' | 'blokkerend' | string
}

export type CampagneBankVraag = {
  id: string
  vraag: string
  bron?: string
}

export type CampagneBankFeit = {
  id: string
  formulering: string
  bronUrl?: string | null
  merkOnderdeel?: string | null
}

export const CAMPAGNE_MERK_ONDERDELEN = [
  'wat_je_doet',
  'waarom',
  'waar_je_tegen_bent',
  'hoe_je_werkt',
  'waar_je_nee_tegen_zegt',
] as const

export const CAMPAGNE_PLAN_CHECK_SOORTEN = [
  { soort: 'balans', label: 'Balans' },
  { soort: 'branchehygiene', label: 'Branchetaal' },
  { soort: 'regel', label: 'Regel' },
  { soort: 'algemeen', label: 'Algemene content' },
  { soort: 'dekking', label: 'Dekking' },
  { soort: 'ankers_te_weinig', label: 'Ankers te weinig' },
  { soort: 'ankers_te_veel', label: 'Ankers te veel' },
  { soort: 'doelpagina', label: 'Doelpagina' },
] as const

export type CampagnePlanCheckOordeel = {
  slotId: string
  label: string
  toelichting: string
}

export type CampagnePlanProgress = {
  key: string
  label: string
}

export type CampagnePlanDecision = {
  choice: 'volgen' | 'niet_volgen' | 'zelf' | 'akkoord' | 'aanpassen' | 'negeren'
  text?: string
  slotIds?: string[]
}

export type CampagnePlanDecisions = {
  advies?: CampagnePlanDecision
  signalen?: Record<string, CampagnePlanDecision>
}

export type CampagnePlanCheck = {
  ok?: boolean
  advies?: string
  progress?: CampagnePlanProgress
  promptVersion?: string
  model?: string
  llmOk?: boolean
  llmError?: string
  postOordelen?: CampagnePlanCheckOordeel[]
  signalen?: CampagnePlanCheckSignaal[]
}

export type CampagnePreviewCampaign = {
  id: string
  title: string
  templateKey: CampagneTemplateKey | null
  phasePlan: CampagnePhasePlan | null
  goal: string | null
  startDate: string | null
  endDate: string | null
  channels: Record<string, number>
  previewStatus: CampagnePreviewStatus | string
  postCount: number
  slotCount: number
  createdAt: string | null
  productPageUrl: string | null
  productExtract: CampagneProductExtract | null
  planCheck: CampagnePlanCheck | null
  planDecisions?: CampagnePlanDecisions
  geoQuestionIds: string[]
  vraagIds?: string[]
  feitIds?: string[]
  merkOnderdelen?: string[]
}

export type CampagnePreviewSlot = {
  slotId: string
  title: string
  platform: string | null
  platformKey: string | null
  slotIndex: number | null
  publishDate: string | null
  publishTime?: string | null
  faseKey: string | null
  faseLabel: string | null
  opdracht: string | null
  instructie?: string | null
  rol: string | null
  anker: { soort: string; id: string; vraag: string; formulering?: string; tekst?: string } | null
  merkOnderdeel?: string | null
  status: string | null
}

export type CampagnePreviewDetail = {
  campaign: CampagnePreviewCampaign
  slots: CampagnePreviewSlot[]
  availableGeoQuestions?: LlmQuestionDto[]
}

export type CampagnePreviewCreatePayload = {
  title: string
  startDate: string
  endDate: string
  channels: Record<string, number>
  goal?: CampagneGoal | string
  templateKey: CampagneTemplateKey
  phasePlan?: CampagnePhasePlan
  schedule?: { mode: 'weekly'; weekday: number }
  productPageUrl?: string
  geoQuestionIds?: string[]
  vraagIds?: string[]
  feitIds?: string[]
  merkOnderdelen?: string[]
}

export type CampagnePreviewCreateResponse = CampagnePreviewDetail & {
  ok: boolean
  campaignId: string
  slotsCreated: number
  productExtract?: { ok?: boolean; error?: string; extract?: CampagneProductExtract }
}

export function fetchCampagneTemplates(): Promise<{
  templates: CampagneTemplate[]
  sellFitHint?: string | null
  planProgress?: CampagnePlanProgress[]
}> {
  return apiFetch('/campagne-preview/templates')
}

export function fetchCampagneGeoQuestions(): Promise<{ questions: LlmQuestionDto[] }> {
  return apiFetch('/campagne-preview/geo-questions')
}

export function fetchCampagneBank(): Promise<{
  vragen: CampagneBankVraag[]
  feiten: CampagneBankFeit[]
  merkOnderdelen?: string[]
}> {
  return apiFetch('/campagne-preview/bank')
}

export function saveCampagneBank(body: {
  vragen?: { id: string; vraag: string; clientRowId?: string }[]
  feiten?: { id: string; formulering: string; clientRowId?: string }[]
  removedVraagIds?: string[]
  removedFeitIds?: string[]
}): Promise<{
  ok: boolean
  vragen: CampagneBankVraag[]
  feiten: CampagneBankFeit[]
  idMap?: Record<string, string>
}> {
  return apiFetch('/campagne-preview/bank', {
    method: 'PATCH',
    body: JSON.stringify(body),
  })
}

export function listCampagnePreviews(): Promise<{ campaigns: CampagnePreviewCampaign[] }> {
  return apiFetch('/campagne-preview')
}

export function fetchCampagnePreview(id: string): Promise<CampagnePreviewDetail> {
  return apiFetch(`/campagne-preview/${id}`)
}

export function createCampagnePreview(
  body: CampagnePreviewCreatePayload,
): Promise<CampagnePreviewCreateResponse> {
  return apiFetch('/campagne-preview', {
    method: 'POST',
    body: JSON.stringify(body),
  })
}

export function deleteCampagnePreview(id: string): Promise<CampagnePreviewDetail> {
  return patchCampagnePreview(id, { previewStatus: 'verwijderd' })
}

export function archiveCampagnePreview(id: string): Promise<CampagnePreviewDetail> {
  return patchCampagnePreview(id, { previewStatus: 'afgerond' })
}

export function patchCampagnePreview(
  id: string,
  body: {
    previewStatus?: CampagnePreviewStatus
    phasePlan?: CampagnePhasePlan | null
    reapplyPhases?: boolean
    vraagIds?: string[]
    feitIds?: string[]
    slotMerkOnderdeel?: { slotId: string; merkOnderdeel: string | null }
    merkOnderdelen?: string[]
    recalculate?: boolean
    slotInstructie?: { slotIds: string[]; text: string }
    planDecision?: {
      target: 'advies' | 'signaal'
      soort?: string
      choice: CampagnePlanDecision['choice']
      text?: string
      slotIds?: string[]
    }
  },
): Promise<CampagnePreviewDetail> {
  return apiFetch(`/campagne-preview/${id}`, {
    method: 'PATCH',
    body: JSON.stringify(body),
  })
}

export function runCampagnePlanCheck(
  id: string,
  stage?: 'check' | 'posts',
): Promise<CampagnePreviewDetail & { ok: boolean; planCheck: CampagnePlanCheck }> {
  return apiFetch(`/campagne-preview/${id}/plan-check`, {
    method: 'POST',
    body: JSON.stringify(stage ? { stage } : {}),
  })
}

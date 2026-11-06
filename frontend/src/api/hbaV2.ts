import { apiFetch } from './client'

export type HbaThemeVerdictLabel = 'versterkt' | 'neutraal' | 'verwatert'

export type HbaThemeVerdict = {
  themeId: string
  label: HbaThemeVerdictLabel
  toelichting: string
}

export type HbaArticleHint = {
  id: string
  aanwijzing: string
  passageVoor?: string | null
  passageNa?: string | null
  headlineVoor?: string | null
  headlineNa?: string | null
  ctaVoor?: string | null
  ctaNa?: string | null
  regelOvertreden?: string | null
  invulvelden?: string[]
  bewijsveldBron?: string | null
  toegepast: boolean
  genegeerd?: boolean
  judgedAt?: string
  openPlaceholders?: boolean
}

export type HbaPbsHistoryPoint = {
  month: string
  score: number
}

export type HbaErosionAlert = {
  message: string
  linkPath?: string
}

export type HbaTrackerRoleCount = {
  role: string
  count: number
}

export type HbaTrackerMetric = {
  label: string
  avg: number | null
}

export type HbaTrackerDetails = {
  contentRoles: HbaTrackerRoleCount[]
  storyTriangle: HbaTrackerMetric[]
  averages: HbaTrackerMetric[]
}

export type HbaProgress = {
  ok: boolean
  scoredPosts: number
  phase: 'A' | 'B' | 'C'
  architectureComplete: boolean
  themesChosen: number
  themesReinforcing: number
  hintsApplied: number
  pbsRevealed: boolean
  showRevealMessage: boolean
  targetPosts: number
  pbsScore?: number | null
  pbsDisplayScore?: number | null
  pbsLabel?: string | null
  pbsHeadline?: string | null
  pbsTrend?: 'up' | 'down' | 'flat' | null
  pbsTrendDelta?: number | null
  pbsHistory?: HbaPbsHistoryPoint[]
  erosionAlert?: HbaErosionAlert | null
  idleMessage?: string | null
  architectureFootnote?: string | null
  trackerDetails?: HbaTrackerDetails | null
  publishedPosts?: number
  voiceActive?: boolean
  guardrailActive?: boolean
  scoreDisplayMode?: 'decimal' | 'band'
  determinismStatus?: 'passed' | 'failed' | null
  scoreAveraging?: boolean
  baselineStatus?: 'pending' | 'running' | 'complete' | 'skipped' | 'failed' | null
  baselineScoredPosts?: number
  baselinePreliminary?: boolean
  baselineMessage?: string | null
  error?: string
}

export type CalibrationPairResult = {
  ok: boolean
  optionA?: string
  optionB?: string
  seedUsed?: string | null
  round?: number
  error?: string
}

export type BrancheOption = {
  id: string
  code: string
  label: string
  marktArchetype?: string | null
  bron: string
  brancheDefinitie?: string
  bronnenKwaliteit?: string
}

export type DeriveArchitectureResult = {
  ok: boolean
  derivation?: {
    archetype_voorstel?: string
    archetype_motivatie?: string
    archetype_alternatieven?: string[]
    foe_afgeleid?: string
    tone_of_behaviour?: string
  }
  error?: string
}

export type HbaWizardDocumentSuggestions = {
  identityIntro: string | null
  doel: string | null
  distinctiviteit: string | null
  watBewustNiet: string | null
  regels: (string | null)[] | null
  kernformuleringen: (string | null)[] | null
}

export type HbaWizardDocumentUploadResult = {
  ok: boolean
  filename?: string
  suggestions?: HbaWizardDocumentSuggestions
  error?: string
}

export function fetchHbaBranches(): Promise<{ branches: BrancheOption[] }> {
  return apiFetch('/hba/branches')
}

export function deriveHbaArchitecture(body: Record<string, unknown>): Promise<DeriveArchitectureResult> {
  return apiFetch('/hba/derive-architecture', { method: 'POST', body: JSON.stringify(body) })
}

export type HbaArchitectureDimension = {
  oordeel: string
  toelichting: string
  verbetering: string
  strawmanGedetecteerd?: boolean
  zitInHygienefactoren?: boolean
}

export type HbaIdentityLevel = 'wrong_level' | 'identity_weak' | 'identity_valid'

export type HbaArchitectureSignaalAdvies = {
  tegenkrachtOntbreekt?: string
  regelOntbreekt?: string
}

export type HbaArchitectureCheckResult = {
  id: string
  dimensies: {
    krachtTriangle: HbaArchitectureDimension
    relevantieTegenkracht: HbaArchitectureDimension
    relevantieRol: HbaArchitectureDimension
    scherpteEnUniciteit: HbaArchitectureDimension
  }
  regelsOordeel: HbaArchitectureDimension
  totaaloordeel: string
  signalen?: {
    tegenkrachtOntbreekt?: boolean
    regelOntbreekt?: boolean
    identityDoelWrongLevel?: boolean
    identityTegenkrachtWrongLevel?: boolean
  }
  signaalAdvies?: HbaArchitectureSignaalAdvies
  meetlatStatus?: 'actief' | 'geblokkeerd'
  premiumLaagAangeraden: boolean
  toonSuggesties: string[]
  voorbeeldSuggesties: Array<{ type: string; tekst: string }>
  identityGate?: {
    goal: { level: HbaIdentityLevel; feedback: string }
    foe: { level: HbaIdentityLevel; feedback: string }
    pass: boolean
  }
  identityGatePass?: boolean
  decision?: 'PASS' | 'REVIEW' | 'FAIL'
  hardFailReason?: string
  returnAction?: string
  criterionScores?: Record<string, { score: number; toelichting: string; verbetering: string }>
  totalScore?: number | null
  guardrailActive?: boolean
  marktcontextOnvoldoende?: boolean
}

export function resolveHbaBranche(body: {
  brancheNaam: string
  brancheCode?: string
}): Promise<{ ok: boolean; branch?: BrancheOption; derived?: boolean; error?: string }> {
  return apiFetch('/hba/derive-branche', { method: 'POST', body: JSON.stringify(body) })
}

export function runHbaArchitectureCheck(
  body: Record<string, unknown>,
): Promise<{ ok: boolean; check?: HbaArchitectureCheckResult; error?: string }> {
  return apiFetch('/hba/architecture-check', { method: 'POST', body: JSON.stringify(body) })
}

export function fetchHbaWizardDocumentStatus(): Promise<{
  ok: boolean
  hasUpload: boolean
  filename?: string
  suggestions?: HbaWizardDocumentSuggestions
}> {
  return apiFetch('/hba/wizard-document')
}

export async function uploadHbaWizardDocument(file: File): Promise<HbaWizardDocumentUploadResult> {
  const API_BASE = import.meta.env.VITE_API_BASE ?? '/api'
  const form = new FormData()
  form.append('document', file)
  const response = await fetch(`${API_BASE}/hba/wizard-document`, {
    method: 'POST',
    credentials: 'include',
    body: form,
  })
  if (!response.ok) {
    let message = `Upload mislukt (${response.status})`
    try {
      const body = (await response.json()) as { error?: string }
      if (body.error) message = body.error
    } catch {
      // ignore
    }
    throw new Error(message)
  }
  return response.json() as Promise<HbaWizardDocumentUploadResult>
}

export function fetchThemeVerdicts(week?: string, themeId?: string, slotId?: string): Promise<{
  ok: boolean
  verdicts?: HbaThemeVerdict[]
  watOntbreekt?: string | null
  error?: string
  code?: string
  architectureFootnote?: string
  guardrailActive?: boolean
  positioningArchetype?: string | null
  positioningBrandGoal?: string | null
  positioningNarrativeVersion?: number
}> {
  const params = new URLSearchParams()
  if (week) params.set('week', week)
  if (themeId) params.set('theme_id', themeId)
  if (slotId) params.set('slot_id', slotId)
  const query = params.toString() ? `?${params.toString()}` : ''
  return apiFetch(`/hba/theme-verdicts${query}`)
}

export function runThemeVerdicts(week?: string, themeId?: string, slotId?: string): Promise<{
  ok: boolean
  verdicts?: HbaThemeVerdict[]
  watOntbreekt?: string | null
  error?: string
  code?: string
  architectureFootnote?: string
  guardrailActive?: boolean
  positioningArchetype?: string | null
  positioningBrandGoal?: string | null
  positioningNarrativeVersion?: number
}> {
  const body: { week?: string; theme_id?: string; slot_id?: string } = {}
  if (week) body.week = week
  if (themeId) body.theme_id = themeId
  if (slotId) body.slot_id = slotId
  return apiFetch('/hba/theme-verdicts', {
    method: 'POST',
    body: JSON.stringify(body),
  })
}

export type HbaResearchVerdict = {
  itemId: string
  label: HbaThemeVerdictLabel
  toelichting: string
}

export function fetchResearchVerdicts(slot: string): Promise<{
  ok: boolean
  verdicts?: HbaResearchVerdict[]
  watOntbreekt?: string | null
  error?: string
  code?: string
  architectureFootnote?: string
  guardrailActive?: boolean
  positioningArchetype?: string | null
  positioningBrandGoal?: string | null
  positioningNarrativeVersion?: number
}> {
  return apiFetch(`/hba/research-verdicts?slot=${encodeURIComponent(slot)}`)
}

export function runResearchVerdicts(slot: string): Promise<{
  ok: boolean
  verdicts?: HbaResearchVerdict[]
  watOntbreekt?: string | null
  error?: string
  code?: string
  architectureFootnote?: string
  guardrailActive?: boolean
  positioningArchetype?: string | null
  positioningBrandGoal?: string | null
  positioningNarrativeVersion?: number
}> {
  return apiFetch('/hba/research-verdicts', {
    method: 'POST',
    body: JSON.stringify({ slot }),
  })
}

export function fetchHbaProgress(): Promise<HbaProgress> {
  return apiFetch('/hba/progress')
}

export function generateArticleHint(
  articleId: string,
  draft?: { headline?: string; content?: string; force?: boolean },
): Promise<{ ok: boolean; hint?: HbaArticleHint; error?: string; code?: string; architectureFootnote?: string }> {
  return apiFetch(`/hba/article-hints/${articleId}`, {
    method: 'POST',
    body: JSON.stringify(draft ?? {}),
  })
}

export function applyArticleHint(
  hintId: string,
  draft?: { headline?: string; content?: string },
): Promise<{
  ok: boolean
  hint?: { id: string; toegepast: boolean; content?: string; headline?: string; openPlaceholders?: boolean }
  error?: string
  code?: string
}> {
  return apiFetch(`/hba/article-hints/${hintId}/apply`, {
    method: 'POST',
    body: JSON.stringify(draft ?? {}),
  })
}

export function ignoreArticleHint(hintId: string): Promise<{ ok: boolean; error?: string }> {
  return apiFetch(`/hba/article-hints/${hintId}/ignore`, {
    method: 'POST',
    body: JSON.stringify({}),
  })
}

export type CalibrationArchitecture = {
  archetype?: string
  doel?: string
  foe?: string
  distinctiviteit?: string
  watBewustNiet?: string
  regels?: string
}

export function fetchCalibrationPair(
  round: number,
  architecture?: CalibrationArchitecture,
): Promise<CalibrationPairResult> {
  return apiFetch('/hba/calibration-pairs', {
    method: 'POST',
    body: JSON.stringify({
      round,
      archetype: architecture?.archetype ?? '',
      doel: architecture?.doel ?? '',
      foe: architecture?.foe ?? '',
      distinctiviteit: architecture?.distinctiviteit ?? '',
      wat_bewust_niet: architecture?.watBewustNiet ?? '',
      regels: architecture?.regels ?? '',
    }),
  })
}

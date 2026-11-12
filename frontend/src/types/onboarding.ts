export type BrandNarrativeDto = {
  id?: string
  clientId?: string
  archetype: string
  doel: string
  foe: string
  spanning: string
  mechanisme: string
  geloofwaardigheidstest: string
  verbodenLogica: string[]
  bewijsvelden: string[]
  toneOfBehaviour: string
  woordenDiePassen: string[]
  woordenDieNietPassen: string[]
  goedeVoorbeelden: [string, string, string]
  zwakkeVoorbeelden: [string, string, string]
  kernformuleringen?: string[]
  contextprofiel: string
  distinctiviteit?: string
  watBewustNiet?: string
  brancheCode?: string
  identityIntro?: string
  aanspreekvorm?: 'je' | 'u' | ''
}

export type BrandNarrativeCompleteness = {
  complete: boolean
  filled: number
  total: number
  missing: string[]
}

export type StrategyDto = {
  clientId: string
  companyName: string
  archetype: string
  adversary: string
  brandstory: string
  /** Hoofddoel-categorie (product-vision.md §9) — bepaalt KPI-highlighting in Report */
  brandGoalCategory: string
  writingGuide: string
  /** Per tone-label (b to b, internal, …) — leidend bij CE-03 rewrite */
  toneStyles?: Record<string, string>
  /** Per redacteur — unieke angle in Article meta */
  editorStyles?: Record<string, string>
  brandNarrative?: BrandNarrativeDto | null
  brandNarrativeCompleteness?: BrandNarrativeCompleteness | null
  archetypeHints?: Record<string, string>
}

export type ResearchItemDto = {
  id: string
  title: string
  snippet: string
  url: string
  itemRationale: string
  /** CE-02 invalshoek-sleutel; null op oudere research. */
  invalshoek?: string | null
  feedback: string
  sourceType: string
}

export type ThemeDto = {
  id: string
  weekLabel: string
  phaseKey?: string
  cycleWeek: string
  title: string
  motivation: string
  rationale: string
  sourceArticleUrl: string
  feedback: string
  status: string
  researchItems: ResearchItemDto[]
  editorSuggested?: string | null
  editorOptions?: string[]
  rhythmRole?: string | null
  rhythmRoleLabel?: string | null
  copyModus?: string | null
  copyModusOverride?: boolean
  copyModusLabel?: string | null
}

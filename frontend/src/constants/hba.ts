export const HBA_ARCHETYPES = [
  'Leider',
  'Magiër',
  'Wijze',
  'Kampioen',
  'Ontdekker',
  'Maker',
  'Rebel',
  'Entertainer',
  'Beminnaar',
  'Verzorger',
  'Buur',
  'Optimist',
] as const

export const HBA_CONTEXT_PROFILES: { value: string; label: string }[] = [
  { value: 'nieuw_merk', label: 'Nieuw merk' },
  { value: 'herpositionering', label: 'Herpositionering' },
  { value: 'groeiend', label: 'Groeiend' },
  { value: 'volwassen', label: 'Volwassen' },
  { value: 'tijdelijke_salesdruk', label: 'Tijdelijke salesdruk' },
  { value: 'b2b_complex_sale', label: 'B2B complex sale' },
]

export const HBA_ARCHETYPE_FOE_HINTS: Record<string, string> = {
  Leider: 'Beslisonzekerheid; complexiteit, onduidelijke keuzes, gebrek aan betrouwbare normen.',
  Magiër: 'Fatalisme en beperkte verbeelding; cynisme over transformatie.',
  Wijze: 'Schijnkennis en verwarring; halve waarheden, expertjargon zonder begrip.',
  Kampioen: 'Machteloosheid en opgave; apathie, middelmaat als norm.',
  Ontdekker: 'Opsluiting en opgelegde routes; angst voor het onbekende.',
  Maker: 'Vormloosheid en lege abstractie; ideeën zonder uitvoering.',
  Rebel: 'Schijnzekerheid; conformiteit, dogma\'s, autoriteit zonder legitimiteit.',
  Entertainer: 'Krampachtige ernst; statusangst, overcontrole.',
  Beminnaar: 'Emotionele afstandelijkheid; puur functionele wereld.',
  Verzorger: 'Verwaarlozing; zorg als kostenpost, doorgeschoven verantwoordelijkheid.',
  Buur: 'Uitsluiting en pretentie; elitair taalgebruik, deelnamedrempels.',
  Optimist: 'Cynisme en besmette complexiteit; wantrouwen als standaard.',
}

export const HBA_PILOT_CLIENT_ID = '016384af-b26e-4415-aa9c-abc46b804798'

/** Leesbare labels voor ontbrekende brand_narratives-velden (API = snake_case). */
export const HBA_NARRATIVE_FIELD_LABELS: Record<string, string> = {
  archetype: 'Persoonlijkheid',
  doel: 'Doel',
  foe: 'Waar je tegen vecht',
  spanning: 'Spanning',
  mechanisme: 'Hoe je het waarmaakt',
  geloofwaardigheidstest: 'Je eigen grens',
  bewijsvelden: 'Bewijsvelden',
  verboden_logica: 'Wat je nooit zegt',
  tone_of_behaviour: 'Gedragstoon',
  woorden_die_passen: 'Woorden die passen',
  woorden_die_niet_passen: 'Woorden die niet passen',
  goede_voorbeelden: 'Sterke voorbeelden',
  zwakke_voorbeelden: 'Zwakke voorbeelden',
  contextprofiel: 'Context',
}

export function hbaNarrativeFieldLabel(field: string): string {
  return HBA_NARRATIVE_FIELD_LABELS[field] ?? field.replaceAll('_', ' ')
}

/** §6 klanttaal — Community/Service blijven (Rogier). */
export const HBA_CONTENT_ROLES: { value: string; label: string }[] = [
  { value: 'positionering', label: 'Positionering' },
  { value: 'bewijs', label: 'Bewijs' },
  { value: 'educatie', label: 'Kennis delen' },
  { value: 'activatie', label: 'Oproep tot actie' },
  { value: 'community', label: 'Community' },
  { value: 'service', label: 'Service' },
  { value: 'employer_culture', label: 'Team en cultuur' },
  { value: 'reactief', label: 'Reactie op nieuws' },
]

export function hbaContentrolLabel(value: string | null | undefined): string {
  if (!value) return ''
  return HBA_CONTENT_ROLES.find((role) => role.value === value)?.label ?? value
}

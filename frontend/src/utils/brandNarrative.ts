import type { BrandNarrativeDto } from '../types/onboarding'

export function emptyBrandNarrative(): BrandNarrativeDto {
  return {
    archetype: '',
    doel: '',
    foe: '',
    spanning: '',
    mechanisme: '',
    geloofwaardigheidstest: '',
    verbodenLogica: [],
    bewijsvelden: [],
    toneOfBehaviour: '',
    woordenDiePassen: [],
    woordenDieNietPassen: [],
    goedeVoorbeelden: ['', '', ''],
    zwakkeVoorbeelden: ['', '', ''],
    kernformuleringen: ['', '', ''],
    contextprofiel: '',
    identityIntro: '',
    aanspreekvorm: '',
  }
}

export function brandNarrativeFromDto(
  dto: BrandNarrativeDto | null | undefined,
  legacy?: { archetype?: string; adversary?: string; brandstory?: string },
): BrandNarrativeDto {
  const base = emptyBrandNarrative()
  if (!dto) {
    return {
      ...base,
      archetype: legacy?.archetype?.trim() ?? '',
      foe: legacy?.adversary?.trim() ?? '',
      doel: legacy?.brandstory?.trim() ?? '',
    }
  }
  return {
    ...base,
    ...dto,
    verbodenLogica: dto.verbodenLogica ?? [],
    bewijsvelden: dto.bewijsvelden ?? [],
    woordenDiePassen: dto.woordenDiePassen ?? [],
    woordenDieNietPassen: dto.woordenDieNietPassen ?? [],
    goedeVoorbeelden: padTriplet(dto.goedeVoorbeelden),
    zwakkeVoorbeelden: padTriplet(dto.zwakkeVoorbeelden),
    kernformuleringen: padKernformuleringen(dto.kernformuleringen),
  }
}

export function wordListToInput(words?: string[]): string {
  return (words ?? []).map((s) => s.trim()).filter(Boolean).join(', ')
}

export function parseWordListInput(text: string): string[] {
  return text
    .split(',')
    .map((s) => s.trim())
    .filter(Boolean)
}

/** Wizard/sjabloonresten — geen echte merkcopy. */
export function stripWizardTemplateNoise(text: string): string {
  return text
    .replace(/\.?\s*Schrijf hieronder in je eigen woorden[^.!?]*[.!?]?/giu, '')
    .replace(/\.?\s*Schrijf hieronder[^.!?]*[.!?]?/giu, '')
    .replace(/\s{2,}/g, ' ')
    .trim()
}

export function narrativeSpanningForSave(doel: string, foe: string, existing: string): string {
  const foeTrim = foe.trim()
  const existingClean = stripWizardTemplateNoise(existing)
  if (existingClean.length >= 20 && existingClean !== foeTrim) {
    return existingClean
  }
  const doelTrim = doel.trim()
  const combo = `De inzet: ${doelTrim} — zolang ${foeTrim} onopgelost blijft, blijft groei kwetsbaar.`
  const fallback = `${doelTrim} — versus ${foeTrim}`
  const candidate = combo.length >= 20 ? combo : fallback
  return candidate.length >= 20 ? candidate.slice(0, 600) : existingClean || candidate
}

export function narrativeMechanismeForSave(distinctiviteit: string, existing: string): string {
  const distinct = stripWizardTemplateNoise(distinctiviteit)
  const mechanisme = stripWizardTemplateNoise(existing)
  if (mechanisme.length >= 20 && mechanisme !== distinct) {
    return mechanisme
  }
  return distinct.length >= 20 ? distinct : mechanisme
}

export function narrativeBewijsveldenForSave(
  distinctiviteit: string,
  regels: string[],
  kernformuleringen: string[],
  existing: string[],
  mechanisme: string,
): string[] {
  const mech = mechanisme.trim()
  const cleaned = existing.map(stripWizardTemplateNoise).filter(Boolean)
  const unique = cleaned.filter((item) => item !== mech)
  if (unique.length >= 1) {
    return unique.slice(0, 5)
  }
  const out = [
    stripWizardTemplateNoise(distinctiviteit),
    ...regels.map(stripWizardTemplateNoise).filter(Boolean),
    ...kernformuleringen.map(stripWizardTemplateNoise).filter(Boolean),
  ].filter(Boolean)
  const deduped = [...new Set(out)].filter((item) => item !== mech)
  return deduped.slice(0, 5)
}

function padKernformuleringen(items?: string[]): string[] {
  const list = (items ?? []).map((s) => s.trim()).filter(Boolean)
  if (list.length === 0) {
    return ['', '', '']
  }
  const count = Math.min(5, Math.max(3, list.length))
  return Array.from({ length: count }, (_, index) => list[index] ?? '')
}

function padTriplet(items?: string[]): [string, string, string] {
  const list = items ?? []
  return [list[0] ?? '', list[1] ?? '', list[2] ?? '']
}

export function brandNarrativeToPayload(narrative: BrandNarrativeDto): BrandNarrativeDto {
  return {
    ...narrative,
    goedeVoorbeelden: padTriplet(narrative.goedeVoorbeelden),
    zwakkeVoorbeelden: padTriplet(narrative.zwakkeVoorbeelden),
    verbodenLogica: narrative.verbodenLogica.map((s) => s.trim()).filter(Boolean),
    bewijsvelden: narrative.bewijsvelden.map((s) => s.trim()).filter(Boolean),
    woordenDiePassen: narrative.woordenDiePassen.map((s) => s.trim()).filter(Boolean),
    woordenDieNietPassen: narrative.woordenDieNietPassen.map((s) => s.trim()).filter(Boolean),
    kernformuleringen: (narrative.kernformuleringen ?? [])
      .map((s) => s.trim())
      .filter(Boolean)
      .slice(0, 5),
  }
}

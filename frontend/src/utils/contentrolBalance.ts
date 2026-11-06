import type { HomeContentrolMix } from '../api/home'

/**
 * Streefaandeel positionering in de contentrol-verdeling (notitie 15 §2.2).
 * Geen per-klant instelling — dit is de norm die Overzicht en Rapport delen,
 * zodat "Scheef" op het Overzicht hetzelfde betekent als "onder je doel" in het Rapport.
 */
export const POSITIONERING_TARGET_PCT = 30

/** §6 klanttaal — Community/Service blijven (Rogier). */
export const CONTENTROL_LABELS: Record<string, string> = {
  positionering: 'Positionering',
  bewijs: 'Bewijs',
  educatie: 'Kennis delen',
  activatie: 'Oproep tot actie',
  community: 'Community',
  service: 'Service',
  employer_culture: 'Team en cultuur',
  reactief: 'Reactie op nieuws',
}

export function contentrolLabel(role: string): string {
  return CONTENTROL_LABELS[role.toLowerCase()] ?? role
}

export type ContentrolShare = { role: string; label: string; pct: number }

/** Rollen op aflopend aandeel, zonder nulwaarden. */
export function contentrolShares(mix: HomeContentrolMix | null | undefined): ContentrolShare[] {
  if (!mix) return []
  return Object.entries(mix)
    .filter(([, pct]) => pct > 0)
    .map(([role, pct]) => ({ role, label: contentrolLabel(role), pct }))
    .sort((a, b) => b.pct - a.pct)
}

export function positioneringPct(mix: HomeContentrolMix | null | undefined): number | null {
  if (!mix || Object.keys(mix).length === 0) return null
  return mix.positionering ?? 0
}

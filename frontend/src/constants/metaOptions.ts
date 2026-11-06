/** Tone — §5.6.1 (vijf opties; archetype/adversary horen bij Angle, niet Tone) */
export const TONE_OPTIONS = [
  'b to b',
  'b to c',
  'internal',
  'partners',
  'personal',
] as const

export const IMAGE_RATIO_OPTIONS = ['square', 'landscape'] as const

const IMAGE_RATIO_LABELS: Record<(typeof IMAGE_RATIO_OPTIONS)[number], string> = {
  square: 'Vierkant',
  landscape: 'Liggend',
}

export function imageRatioDisplayLabel(ratio: string): string {
  return IMAGE_RATIO_LABELS[ratio as (typeof IMAGE_RATIO_OPTIONS)[number]] ?? ratio
}

export const PLATFORM_OPTIONS = ['Linkedin', 'Instagram', 'X', 'Facebook', 'Blog'] as const

export const DEFAULT_META_TONE = ''

export function defaultToneFrom(options: readonly string[]): string {
  return options[0] ?? ''
}
/** Angle is niet meer handmatig te kiezen (HBA bepaalt de contentrol); nieuwe artikelen starten op general. */
export const DEFAULT_META_ANGLE = 'general'

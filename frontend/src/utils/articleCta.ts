/** Klikmodel p-artikeldetail — drie CTA-voorstellen (letterlijk). */
export const ARTICLE_CTA_VOORSTELLEN = [
  'Neem contact op voor een vrijblijvend kennismakingsgesprek.',
  'Lees hier hoe wij dit aanpakken.',
  'Plan een intake in bij ons team.',
] as const

export type ArticleCtaState = {
  hasCta: boolean | null
  ctaText: string
}

export function articleCtaFromDto(hasCta?: boolean | null, ctaText?: string | null): ArticleCtaState {
  return {
    hasCta: typeof hasCta === 'boolean' ? hasCta : null,
    ctaText: (ctaText ?? '').trim(),
  }
}
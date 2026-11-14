import { CAMPAGNE_MERK_ONDERDELEN } from '../api/campagnePreview'

export const ARTICLE_START_MERK_KEYS = CAMPAGNE_MERK_ONDERDELEN

export const ARTICLE_START_MERK_LABELS: Record<(typeof CAMPAGNE_MERK_ONDERDELEN)[number], string> = {
  wat_je_doet: 'Rol',
  waarom: 'Doel',
  waar_je_tegen_bent: 'Waar je tegen bent',
  hoe_je_werkt: 'Hoe je werkt',
  waar_je_nee_tegen_zegt: 'Regel of subregel',
}

export type ArticleStartChannelKey = 'linkedin' | 'instagram' | 'website' | 'x' | 'facebook'

export const ARTICLE_START_CHANNELS: { key: ArticleStartChannelKey; label: string }[] = [
  { key: 'linkedin', label: 'LinkedIn' },
  { key: 'instagram', label: 'Instagram' },
  { key: 'website', label: 'Eigen website' },
  { key: 'x', label: 'X' },
  { key: 'facebook', label: 'Facebook' },
]

export function defaultArticleStartChannels(): Record<ArticleStartChannelKey, boolean> {
  return {
    linkedin: true,
    instagram: true,
    website: false,
    x: false,
    facebook: false,
  }
}
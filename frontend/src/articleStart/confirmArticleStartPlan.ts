import { createFastlaneArticle } from '../api/fastlane'
import { isApiConfigured } from '../api/client'
import type { ArticleStartDraft } from './useArticleStartDraft'
import type { ArticleStartPlanItem } from './articleStartPlan'
import { primaryPlatform, selectedChannelLabels } from './articleStartPlan'

export type ConfirmArticleStartResult = {
  slotIds: string[]
  themeIds: string[]
}

function buildExtraThoughts(draft: ArticleStartDraft, item: ArticleStartPlanItem): string {
  const parts: string[] = []
  if (draft.theme.trim()) parts.push(`Onderwerp: ${draft.theme.trim()}`)
  if (item.angle.trim()) parts.push(`Invalshoek: ${item.angle.trim()}`)
  if (draft.extraThoughts.trim()) parts.push(draft.extraThoughts.trim())
  const channels = selectedChannelLabels(draft)
  if (channels.length > 1) {
    parts.push(`Kanalen (zelfde basistekst): ${channels.join(', ')}`)
  }
  if (draft.channels.linkedin && draft.linkedinType === 'artikel') {
    parts.push('LinkedIn-vorm: artikel (niet alleen post).')
  }
  return parts.join('\n')
}

/** Bevestig plan: één fastlane-slot per artikel (primair kanaal). */
export async function confirmArticleStartPlan(
  draft: ArticleStartDraft,
  planItems: ArticleStartPlanItem[],
): Promise<ConfirmArticleStartResult> {
  if (!isApiConfigured()) {
    return {
      slotIds: planItems.map((_, i) => `demo-slot-${i + 1}`),
      themeIds: planItems.map((_, i) => `demo-theme-${i + 1}`),
    }
  }

  const platform = primaryPlatform(draft)
  const slotIds: string[] = []
  const themeIds: string[] = []

  for (const item of planItems) {
    const title = (item.angle.trim() || draft.theme.trim() || 'Artikel').slice(0, 255)
    const result = await createFastlaneArticle({
      title,
      researchUrls: [],
      extraThoughts: buildExtraThoughts(draft, item) || undefined,
      platform,
      tags: draft.hashtags.length > 0 ? draft.hashtags : undefined,
      scheduledDate: item.publishDate || undefined,
      rationale: draft.theme.trim() || undefined,
      origin: 'article-start-wizard',
    })
    slotIds.push(result.slotId)
    themeIds.push(result.themeId)
  }

  return { slotIds, themeIds }
}

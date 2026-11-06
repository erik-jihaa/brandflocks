import type { ArchiveItemDto, ArchiveReportDto } from '../api/archive'
import { formatArchiveSearchDate } from './archiveDates'

export type ArchiveSearchType = 'article' | 'report'

export const ARCHIVE_SEARCH_TYPE_OPTIONS = ['Artikel', 'Rapport'] as const

export function archiveSearchTypeFromLabel(label: string): ArchiveSearchType | null {
  if (label === 'Artikel' || label === 'Article') return 'article'
  if (label === 'Rapport' || label === 'Report') return 'report'
  return null
}

export function archiveSearchTypeLabel(type: ArchiveSearchType | null): string {
  if (type === 'article') return 'Artikel'
  if (type === 'report') return 'Rapport'
  return ''
}

export function filterArchiveItems(items: ArchiveItemDto[], query: string): ArchiveItemDto[] {
  const q = query.trim().toLowerCase()
  if (q === '') return items

  return items.filter((item) => {
    const haystack = [
      item.title,
      item.weekLabel,
      item.archiveNote,
      item.socialPostUrl,
      ...(item.publishLinks?.flatMap((link) => [link.label, link.url]) ?? []),
      item.socialPostId,
      item.publishedAt,
      formatArchiveSearchDate(item.publishedAt),
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase()

    return haystack.includes(q)
  })
}

export function filterArchiveReports(reports: ArchiveReportDto[], query: string): ArchiveReportDto[] {
  const q = query.trim().toLowerCase()
  if (q === '') return reports

  return reports.filter((report) => {
    const haystack = [
      report.title,
      report.narrativePreview,
      report.generatedAt,
      formatArchiveSearchDate(report.generatedAt),
    ]
      .filter(Boolean)
      .join(' ')
      .toLowerCase()

    return haystack.includes(q)
  })
}

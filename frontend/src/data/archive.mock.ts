import type { ArchiveItemDto, ArchiveReportDto } from '../api/archive'

export const archiveMock: ArchiveItemDto[] = [
  {
    slotId: 'mock-archive-1',
    articleId: 'mock-a-1',
    weekLabel: 'Week 12',
    title: 'Post name 1',
    publishedAt: '2026-06-24T10:00:00',
    socialPostUrl: 'https://linkedin.com/posts/example-1',
    socialPostId: 'https://linkedin.com/posts/example-1',
    publishLinks: [{ kind: 'linkedin', label: 'LinkedIn', url: 'https://linkedin.com/posts/example-1' }],
    promoBudget: 250,
    archiveNote: 'We released a new proposal that week.',
    interactionViews: 1200,
    interactionLikes: 10,
    interactionComments: 2,
    isArchived: true,
  },
  {
    slotId: 'mock-archive-2',
    articleId: 'mock-a-2',
    weekLabel: 'Week 12',
    title: 'Post name 2',
    publishedAt: '2026-06-20T14:30:00',
    socialPostUrl: null,
    socialPostId: null,
    promoBudget: null,
    archiveNote: null,
    interactionViews: null,
    interactionLikes: null,
    interactionComments: null,
    isArchived: true,
  },
  {
    slotId: 'mock-archive-3',
    articleId: 'mock-a-3',
    weekLabel: 'Week 11',
    title: 'Post name 1',
    publishedAt: '2026-06-13T09:00:00',
    socialPostUrl: 'https://linkedin.com/posts/example-3',
    socialPostId: 'https://linkedin.com/posts/example-3',
    publishLinks: [{ kind: 'linkedin', label: 'LinkedIn', url: 'https://linkedin.com/posts/example-3' }],
    promoBudget: 100,
    archiveNote: null,
    interactionViews: 800,
    interactionLikes: 6,
    interactionComments: 1,
    isArchived: true,
  },
  {
    slotId: 'mock-archive-4',
    articleId: 'mock-a-4',
    weekLabel: 'Week 11',
    title: 'Post name 2',
    publishedAt: '2026-06-11T11:00:00',
    socialPostUrl: null,
    socialPostId: null,
    promoBudget: null,
    archiveNote: null,
    interactionViews: null,
    interactionLikes: null,
    interactionComments: null,
    isArchived: true,
  },
]

export const archiveReportsMock: ArchiveReportDto[] = [
  {
    reportId: 'mock-report-1',
    title: 'Report 2026-05-01 – 2026-05-31',
    narrativePreview: 'May performance summary with engagement highlights across LinkedIn posts.',
    generatedAt: '2026-06-01T09:00:00',
    pdfUrl: '/api/reports/mock-report-1/pdf',
  },
  {
    reportId: 'mock-report-2',
    title: 'Report 2026-04-01 – 2026-04-30',
    narrativePreview: 'April overview: steady reach with two standout articles in week 14.',
    generatedAt: '2026-05-01T09:00:00',
    pdfUrl: '/api/reports/mock-report-2/pdf',
  },
]

export function groupArchiveByWeek(items: ArchiveItemDto[]): { week: string; rows: ArchiveItemDto[] }[] {
  const order: string[] = []
  const map = new Map<string, ArchiveItemDto[]>()

  for (const item of items) {
    const week = item.weekLabel || 'Archive'
    if (!map.has(week)) {
      order.push(week)
      map.set(week, [])
    }
    map.get(week)!.push(item)
  }

  return order.map((week) => ({ week, rows: map.get(week)! }))
}

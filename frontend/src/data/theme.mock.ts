import type { ThemeDto } from '../types/onboarding'

export const themeMock: ThemeDto[] = [
  {
    id: 'mock-theme-1',
    weekLabel: 'Week 17',
    phaseKey: '2026-W17',
    cycleWeek: '2026-04-21',
    title: 'De organisatie staat — maar loopt ze ook?',
    motivation:
      'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.',
    rationale:
      'How the Strategy input connects with the article — tation ullamco laboris nisi ut aliquip ex ea commodo consequat.',
    sourceArticleUrl: 'https://example.com/article',
    feedback: 'none',
    status: 'proposed',
    researchItems: [],
  },
  {
    id: 'mock-theme-2',
    weekLabel: 'Week 17',
    phaseKey: '2026-W17',
    cycleWeek: '2026-04-21',
    title: 'AI in contentmarketing: hype of hefboom?',
    motivation:
      'Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.',
    rationale:
      'How the Strategy input connects with the article — sunt in culpa qui officia deserunt mollit anim id est laborum.',
    sourceArticleUrl: '',
    feedback: 'none',
    status: 'proposed',
    researchItems: [],
  },
  {
    id: 'mock-theme-3',
    weekLabel: 'Week 17',
    phaseKey: '2026-W17',
    cycleWeek: '2026-04-21',
    title: 'Thought leadership zonder ego',
    motivation:
      'Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium doloremque laudantium.',
    rationale:
      'How the Strategy input connects with the article — eaque ipsa quae ab illo inventore veritatis.',
    sourceArticleUrl: 'https://example.com/other',
    feedback: 'none',
    status: 'proposed',
    researchItems: [],
  },
]

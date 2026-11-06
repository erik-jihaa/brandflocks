/** PDF pag. 6 — Agenda */
export const agendaMock = [
  {
    week: 'Week 13',
    rows: [
      {
        title: 'Post name 1',
        actions: [
          { label: 'Published', status: 'published' as const },
          { label: 'View' },
          { label: 'Archive' },
        ],
      },
      {
        title: 'Post name 2',
        actions: [
          { label: 'Not Published', status: 'not-published' as const },
          { label: 'View' },
          { label: 'Archive' },
        ],
      },
    ],
  },
  {
    week: 'Week 14',
    rows: [
      {
        title: 'Post name 1',
        actions: [
          { label: 'Edit' },
          { label: 'Delete' },
          { label: 'Approved', status: 'approved' as const },
          { label: 'Archive' },
        ],
      },
      {
        title: 'Post name 2',
        actions: [
          { label: 'Edit' },
          { label: 'Delete' },
          { label: 'Pending', status: 'pending' as const },
          { label: 'Archive' },
        ],
      },
    ],
  },
  {
    week: 'Week 15',
    rows: [
      { title: 'Content reseach title 1', researchDone: true, actions: [{ label: 'Edit' }] },
      { title: 'Content reseach title 2', researchDone: true, actions: [{ label: 'Edit' }] },
    ],
  },
] as const

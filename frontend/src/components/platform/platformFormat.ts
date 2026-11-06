export function formatDaysAgo(days: number | null | undefined): string {
  if (days == null) return '—'
  if (days === 0) return 'vandaag'
  if (days === 1) return '1 dag'
  return `${days} dagen`
}

export function formatEur(value: number | null | undefined): string {
  if (value == null || Number.isNaN(value)) return '—'
  return `€${value.toFixed(2)}`
}

export function formatCount(value: number | null | undefined): string {
  if (value == null || Number.isNaN(value)) return '—'
  return value.toLocaleString('nl-NL')
}

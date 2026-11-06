/** Hoofddoel-categorieën (product-vision.md §9, §1) — bepalen KPI-highlighting in Report. */
export const BRAND_GOAL_CATEGORIES: Array<{ id: string; label: string }> = [
  { id: 'awareness', label: 'Naamsbekendheid' },
  { id: 'leads', label: 'Leads' },
  { id: 'sales', label: 'Verkoop' },
  { id: 'recruitment', label: 'Recruitment' },
  { id: 'community', label: 'Community bouwen' },
  { id: 'service', label: 'Klantenservice' },
  { id: 'thought_leadership', label: 'Thought leadership' },
  { id: 'brand_preference', label: 'Merkvoorkeur' },
]

export function brandGoalLabel(id: string): string {
  return BRAND_GOAL_CATEGORIES.find((c) => c.id === id)?.label ?? ''
}

export function brandGoalIdFromLabel(label: string): string {
  return BRAND_GOAL_CATEGORIES.find((c) => c.label === label)?.id ?? ''
}

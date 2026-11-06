import type { ObjectiveDto } from '../types/objectives'

/** Korte titel voor doel — gebruikt in Bronnen (map-koppeling) en lijsten. */
export function objectiveDisplayLabel(objective: Pick<ObjectiveDto, 'label' | 'description'>): string {
  const label = objective.label.trim()
  if (label) return label
  const line = objective.description.trim().split('\n')[0]?.trim() ?? ''
  return line.slice(0, 80) || 'Doel'
}

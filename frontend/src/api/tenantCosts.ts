import { apiFetch } from './client'
import type { TenantCostReport } from '../utils/tenantCosts'

export function fetchTenantCosts(params?: {
  month?: string
  clientId?: string
  postsPerWeek?: number
  projected?: boolean
}): Promise<TenantCostReport> {
  const search = new URLSearchParams()
  if (params?.month) search.set('month', params.month)
  if (params?.clientId) search.set('client_id', params.clientId)
  if (params?.postsPerWeek != null) search.set('posts_per_week', String(params.postsPerWeek))
  if (params?.projected) search.set('projected', 'true')
  const suffix = search.toString() ? `?${search.toString()}` : ''
  return apiFetch(`/tenant-costs${suffix}`)
}

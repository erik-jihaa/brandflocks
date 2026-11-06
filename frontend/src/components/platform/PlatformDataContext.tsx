import { createContext, useContext, type ReactNode } from 'react'
import type { PlatformEvent, PlatformTenantRow, PlatformUser, PlatformCostTenantRow } from '../../api/platform'

export type PlatformData = {
  loading: boolean
  error: string | null
  platformUser: PlatformUser | null
  tenants: PlatformTenantRow[]
  events: PlatformEvent[]
  status: {
    environment: string
    errors24h: number
    tenantCount: number
    lastMigration: { filename: string; applied_at: string } | null
  } | null
  costsTotal: number | null
  totalN8nExecutions: number | null
  costsByTenant: PlatformCostTenantRow[]
  reload: () => void
}

const PlatformDataContext = createContext<PlatformData | null>(null)

export function PlatformDataProvider({
  value,
  children,
}: {
  value: PlatformData
  children: ReactNode
}) {
  return <PlatformDataContext.Provider value={value}>{children}</PlatformDataContext.Provider>
}

export function usePlatformData(): PlatformData {
  const ctx = useContext(PlatformDataContext)
  if (!ctx) {
    throw new Error('usePlatformData requires PlatformDataProvider')
  }
  return ctx
}

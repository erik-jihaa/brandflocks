import { useCallback, useEffect, useMemo, useState } from 'react'
import { Outlet, useLocation } from 'react-router-dom'
import { GridShell } from '../layout/GridShell'
import { PLATFORM_MAIN_WIDTH } from '../layout/grid.constants'
import { PlatformDataProvider, type PlatformData } from '../components/platform/PlatformDataContext'
import { PlatformSidebarNav } from '../components/platform/PlatformSidebarNav'
import {
  fetchPlatformCosts,
  fetchPlatformEvents,
  fetchPlatformSession,
  fetchPlatformStatus,
  fetchPlatformTenants,
} from '../api/platform'
import { isApiConfigured } from '../api/client'

function pageTitleForPath(pathname: string, tenantName: string | null): string {
  if (pathname === '/platform/tenant/new') return 'Nieuwe tenant'
  if (pathname.startsWith('/platform/tenant/') && tenantName) return tenantName
  if (pathname.startsWith('/platform/finance')) return 'Finance'
  if (pathname.startsWith('/platform/hygiene')) return 'Hygiënefactoren'
  if (pathname.startsWith('/platform/settings')) return 'Settings'
  return 'Overview'
}

export function PlatformLayout() {
  const location = useLocation()
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [platformUser, setPlatformUser] = useState<PlatformData['platformUser']>(null)
  const [tenants, setTenants] = useState<PlatformData['tenants']>([])
  const [events, setEvents] = useState<PlatformData['events']>([])
  const [status, setStatus] = useState<PlatformData['status']>(null)
  const [costsTotal, setCostsTotal] = useState<number | null>(null)
  const [totalN8nExecutions, setTotalN8nExecutions] = useState<number | null>(null)
  const [costsByTenant, setCostsByTenant] = useState<PlatformData['costsByTenant']>([])

  const load = useCallback(() => {
    if (!isApiConfigured()) {
      setLoading(false)
      return
    }
    setLoading(true)
    setError(null)
    void Promise.all([
      fetchPlatformTenants(),
      fetchPlatformEvents({ severity: 'error', days: 7, limit: 50 }),
      fetchPlatformStatus(),
      fetchPlatformCosts(),
      fetchPlatformSession(),
    ])
      .then(([tenantsRes, eventsRes, statusRes, costsRes, sessionRes]) => {
        setTenants(tenantsRes.tenants ?? [])
        setEvents(eventsRes.events ?? [])
        setStatus(statusRes)
        setCostsTotal(costsRes.grandTotalEur ?? null)
        setTotalN8nExecutions(costsRes.totalN8nExecutions ?? null)
        setCostsByTenant(costsRes.tenants ?? [])
        setPlatformUser(sessionRes.user ?? null)
      })
      .catch((err) => {
        setError(err instanceof Error ? err.message : 'Laden mislukt')
      })
      .finally(() => setLoading(false))
  }, [])

  useEffect(() => {
    load()
  }, [load])

  const tenantMatch = /^\/platform\/tenant\/([0-9a-f-]{36})/i.exec(location.pathname)
  const activeTenant = useMemo(
    () => tenants.find((t) => t.clientId === tenantMatch?.[1]) ?? null,
    [tenants, tenantMatch],
  )

  const pageTitle = pageTitleForPath(location.pathname, activeTenant?.company ?? null)

  const platformData: PlatformData = {
    loading,
    error,
    platformUser,
    tenants,
    events,
    status,
    costsTotal,
    totalN8nExecutions,
    costsByTenant,
    reload: load,
  }

  return (
    <PlatformDataProvider value={platformData}>
      <GridShell
        pageTitle={pageTitle}
        activeNav="home"
        showNav
        sidebarNav={<PlatformSidebarNav />}
        forceLightTheme
        logoVariant="platform"
        mainColumnWidth={PLATFORM_MAIN_WIDTH}
        main={<Outlet />}
      />
    </PlatformDataProvider>
  )
}

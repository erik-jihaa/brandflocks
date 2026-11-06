import { useEffect, useMemo, useState } from 'react'
import { useLocation } from 'react-router-dom'
import { apiFetch, isApiConfigured } from '../api/client'

type HealthResponse = {
  environment?: string
}

export type SiteBrand = {
  title: string
  favicon: string
}

/** Browser-tab label + favicon per subsite (productie / platform / staging). */
export function resolveSiteBrand(environment: string | null, isPlatformRoute: boolean): SiteBrand {
  if (isPlatformRoute) {
    return { title: 'CF_Platform', favicon: '/favicon-white.svg' }
  }
  if (environment === 'staging' || environment === 'local') {
    return { title: 'CF_Staging', favicon: '/favicon-green.svg' }
  }
  return { title: 'ContentFlow', favicon: '/favicon-blue.svg' }
}

export function SiteBranding() {
  const { pathname } = useLocation()
  const [environment, setEnvironment] = useState<string | null>(null)

  useEffect(() => {
    if (!isApiConfigured()) {
      setEnvironment('local')
      return
    }

    let cancelled = false
    void apiFetch<HealthResponse>('/health')
      .then((data) => {
        if (!cancelled) setEnvironment(data.environment ?? 'production')
      })
      .catch(() => {
        if (!cancelled) setEnvironment(null)
      })

    return () => {
      cancelled = true
    }
  }, [])

  const brand = useMemo(
    () => resolveSiteBrand(environment, pathname.startsWith('/platform')),
    [environment, pathname],
  )

  useEffect(() => {
    document.title = brand.title

    let link = document.querySelector<HTMLLinkElement>('link[rel="icon"]')
    if (!link) {
      link = document.createElement('link')
      link.rel = 'icon'
      document.head.appendChild(link)
    }
    link.type = 'image/svg+xml'
    link.href = brand.favicon
  }, [brand])

  return null
}

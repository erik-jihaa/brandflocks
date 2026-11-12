import { useEffect, useMemo, useState } from 'react'
import { useLocation } from 'react-router-dom'
import { apiFetch, isApiConfigured } from '../api/client'
import {
  DEFAULT_PRODUCT_BRANDING,
  parseProductBranding,
  type ProductBranding,
} from '../config/productBranding'

type HealthResponse = {
  environment?: string
  branding?: ProductBranding
}

export type SiteBrand = {
  title: string
  favicon: string
}

/** Browser-tab label + favicon per subsite (productie / platform / niet-productie). */
export function resolveSiteBrand(
  environment: string | null,
  isPlatformRoute: boolean,
  branding: ProductBranding = DEFAULT_PRODUCT_BRANDING,
): SiteBrand {
  if (isPlatformRoute) {
    return { title: branding.platformLabel, favicon: '/favicon-white.svg' }
  }
  if (environment !== null && environment !== 'production') {
    return { title: branding.stagingLabel, favicon: '/favicon-green.svg' }
  }
  return { title: branding.siteTitle, favicon: '/favicon-blue.svg' }
}

export function SiteBranding() {
  const { pathname } = useLocation()
  const [environment, setEnvironment] = useState<string | null>(null)
  const [branding, setBranding] = useState<ProductBranding>(DEFAULT_PRODUCT_BRANDING)

  useEffect(() => {
    if (!isApiConfigured()) {
      setEnvironment('local')
      return
    }

    let cancelled = false
    void apiFetch<HealthResponse>('/health')
      .then((data) => {
        if (cancelled) return
        setEnvironment(data.environment ?? 'production')
        setBranding(parseProductBranding(data.branding))
      })
      .catch(() => {
        if (!cancelled) setEnvironment(null)
      })

    return () => {
      cancelled = true
    }
  }, [])

  const brand = useMemo(
    () => resolveSiteBrand(environment, pathname.startsWith('/platform'), branding),
    [environment, pathname, branding],
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

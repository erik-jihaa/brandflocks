import { useEffect, useState } from 'react'
import { useLocation } from 'react-router-dom'
import { apiFetch, isApiConfigured } from '../api/client'
import {
  DEFAULT_PRODUCT_BRANDING,
  parseProductBranding,
  type ProductBranding,
} from '../config/productBranding'

type HealthResponse = {
  ok?: boolean
  environment?: string
  db?: string
  branding?: ProductBranding
}

type BannerScope = 'platform' | 'tenant'

function bannerLabel(
  environment: string,
  scope: BannerScope,
  branding: ProductBranding,
): string {
  const base = scope === 'platform' ? branding.platformLabel : branding.stagingLabel
  if (environment === 'local') {
    return `${base} (local) — ontwikkelomgeving`
  }
  if (environment === 'staging') {
    return `${base} — testomgeving, geen productie-tenants`
  }
  return `${base} (${environment}) — niet-productieomgeving`
}

function BrandBall({ variant }: { variant: 'white' | 'green' }) {
  if (variant === 'white') {
    return (
      <span
        aria-hidden
        className="inline-block h-[10px] w-[10px] shrink-0 rounded-full border border-white/90 bg-white"
      />
    )
  }

  return (
    <span
      aria-hidden
      className="inline-block h-[10px] w-[10px] shrink-0 rounded-full bg-ce-green"
    />
  )
}

/**
 * Zichtbare band bij staging/lokaal/named installs. Voorkomt de klassieke fout: denken
 * dat je in de testomgeving zit terwijl je op productie werkt (of andersom).
 */
export function EnvironmentBanner() {
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
        setEnvironment(data.environment ?? null)
        setBranding(parseProductBranding(data.branding))
      })
      .catch(() => {
        if (!cancelled) setEnvironment(null)
      })

    return () => {
      cancelled = true
    }
  }, [])

  if (environment === null || environment === 'production') return null

  const scope: BannerScope = pathname.startsWith('/platform') ? 'platform' : 'tenant'
  const label = bannerLabel(environment, scope, branding)

  return (
    <div
      role="status"
      className="environment-banner fixed inset-x-0 top-0 z-50 flex items-center justify-center gap-[8px] px-[20px] py-[6px] text-center text-[13px] font-semibold leading-[16px]"
    >
      <BrandBall variant={scope === 'platform' ? 'white' : 'green'} />
      <span>{label}</span>
    </div>
  )
}

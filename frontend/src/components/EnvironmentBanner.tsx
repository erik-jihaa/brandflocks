import { useEffect, useState } from 'react'
import { useLocation } from 'react-router-dom'
import { apiFetch, isApiConfigured } from '../api/client'

type HealthResponse = {
  ok?: boolean
  environment?: string
  db?: string
}

type BannerScope = 'platform' | 'tenant'

const STAGING_LABELS: Record<BannerScope, string> = {
  platform: 'CF_Platform — testomgeving, geen productie-tenants',
  tenant: 'CF_Staging — testomgeving, geen productie-tenants',
}

const LOCAL_LABELS: Record<BannerScope, string> = {
  platform: 'CF_Platform (local) — ontwikkelomgeving',
  tenant: 'CF_Staging (local) — ontwikkelomgeving',
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
 * Zichtbare band bij staging/lokaal. Voorkomt de klassieke fout: denken dat je in
 * de testomgeving zit terwijl je op productie werkt (of andersom).
 */
export function EnvironmentBanner() {
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
        if (!cancelled) setEnvironment(data.environment ?? null)
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
  const label =
    environment === 'local'
      ? LOCAL_LABELS[scope]
      : STAGING_LABELS[scope] ?? `${environment.toUpperCase()} — niet-productieomgeving`

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

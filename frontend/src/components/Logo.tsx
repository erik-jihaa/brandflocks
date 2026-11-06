import { useEffect, useState } from 'react'
import { fetchAccount, fetchAuthSession } from '../api/account'
import { isApiConfigured } from '../api/client'
import {
  TENANT_LOGO_IMG_CLASS,
  tenantLogoImgStyle,
} from './TenantLogoField'

const DEFAULT_LOGO = '/svg/iGiSNext_Blue-main.svg' // canonical: SVG/iGiSNext_Blue-main.svg (sync-platform-logo.sh)
const DARK_LOGO = '/svg/iGiSNext_Blue-dark.svg'
const DEFAULT_ALT = 'iGiS ContentFlow'

/** Dispatch na upload/delete zodat de sidebar-logo meteen ververst. */
export const TENANT_LOGO_CHANGED_EVENT = 'ce-tenant-logo-changed'

export { TENANT_LOGO_IMG_CLASS, tenantLogoImgStyle } from './TenantLogoField'

type LogoProps = {
  className?: string
  /** platform = iGiSNext (login); tenant = klantlogo uit account */
  variant?: 'platform' | 'tenant'
  /** Account-dark: gebruik dark SVG-variant */
  tone?: 'light' | 'dark'
}

export function Logo({ className = '', variant = 'tenant', tone = 'light' }: LogoProps) {
  const platformLogo = tone === 'dark' ? DARK_LOGO : DEFAULT_LOGO
  const [src, setSrc] = useState<string | null>(variant === 'platform' ? platformLogo : null)
  const [alt, setAlt] = useState(DEFAULT_ALT)
  const [reloadToken, setReloadToken] = useState(0)

  useEffect(() => {
    if (variant !== 'tenant') return
    function onLogoChanged() {
      setReloadToken((n) => n + 1)
    }
    window.addEventListener(TENANT_LOGO_CHANGED_EVENT, onLogoChanged)
    return () => window.removeEventListener(TENANT_LOGO_CHANGED_EVENT, onLogoChanged)
  }, [variant])

  useEffect(() => {
    if (variant === 'platform' || !isApiConfigured()) {
      setSrc(platformLogo)
      setAlt(DEFAULT_ALT)
      return
    }

    let cancelled = false
    void fetchAuthSession()
      .then((session) => {
        if (cancelled) return null
        const clientId = session.clientId?.trim()
        if (!clientId) return null
        return fetchAccount(clientId)
      })
      .then((result) => {
        if (cancelled) return
        const account = result?.account
        if (!account) {
          setSrc(platformLogo)
          setAlt(DEFAULT_ALT)
          return
        }
        const logo = account.logoUrl?.trim()
        const company = account.company?.trim()
        setSrc(logo || platformLogo)
        setAlt(company || DEFAULT_ALT)
      })
      .catch(() => {
        if (!cancelled) {
          setSrc(platformLogo)
          setAlt(DEFAULT_ALT)
        }
      })

    return () => {
      cancelled = true
    }
  }, [variant, platformLogo, reloadToken])

  if (src === null) {
    return (
      <span
        className={`inline-block shrink-0 ${className}`}
        style={variant === 'tenant' ? tenantLogoImgStyle() : undefined}
        aria-hidden
      />
    )
  }

  if (variant === 'tenant') {
    return (
      <img
        src={src}
        alt={alt}
        className={`${TENANT_LOGO_IMG_CLASS} ${className}`}
        style={tenantLogoImgStyle()}
      />
    )
  }

  return (
    <img
      src={src}
      alt={alt}
      className={`block max-h-[32px] w-auto max-w-[200px] object-contain bg-transparent ${className}`}
    />
  )
}

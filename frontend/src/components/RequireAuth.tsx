import { Navigate, Outlet, useLocation } from 'react-router-dom'
import { useEffect, useState } from 'react'
import { fetchAuthSession } from '../api/account'
import { isApiConfigured } from '../api/client'
import { useNavKeyboardShortcuts } from '../hooks/useNavKeyboardShortcuts'
import { useTenantIdleLogout } from '../hooks/useTenantIdleLogout'

type AuthState = 'loading' | 'ok' | 'login'

function AuthenticatedOutlet() {
  useNavKeyboardShortcuts()
  useTenantIdleLogout()
  return <Outlet />
}

/** Beschermt routes wanneer login verplicht is (§5.9 guard). */
export function RequireAuth() {
  const location = useLocation()
  const [state, setState] = useState<AuthState>('loading')

  useEffect(() => {
    if (!isApiConfigured()) {
      setState('ok')
      return
    }

    let cancelled = false
    void fetchAuthSession()
      .then((session) => {
        if (cancelled) return
        if (session.authRequired) {
          if (!session.authenticated || !session.clientId?.trim()) {
            setState('login')
            return
          }
        }
        setState('ok')
      })
      .catch(() => {
        if (!cancelled) setState('login')
      })

    return () => {
      cancelled = true
    }
  }, [])

  if (state === 'loading') {
    return (
      <div className="flex min-h-screen items-center justify-center bg-ce-offwhite text-[14px] text-ce-beige">
        Laden…
      </div>
    )
  }

  if (state === 'login') {
    return <Navigate to="/login" replace state={{ from: location.pathname }} />
  }

  return <AuthenticatedOutlet />
}

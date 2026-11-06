import { useEffect, useState } from 'react'
import { Navigate, Outlet, useLocation } from 'react-router-dom'
import { fetchPlatformSession } from '../api/platform'
import { isApiConfigured } from '../api/client'

type AuthState = 'loading' | 'ok' | 'login'

/** Beschermt /platform routes — aparte platform_users-sessie. */
export function RequirePlatformAuth() {
  const location = useLocation()
  const [state, setState] = useState<AuthState>('loading')

  useEffect(() => {
    if (!isApiConfigured()) {
      setState('ok')
      return
    }

    let cancelled = false

    function verifySession() {
      void fetchPlatformSession()
        .then((session) => {
          if (cancelled) return
          setState(session.authenticated ? 'ok' : 'login')
        })
        .catch(() => {
          if (!cancelled) setState('login')
        })
    }

    verifySession()
    window.addEventListener('focus', verifySession)

    return () => {
      cancelled = true
      window.removeEventListener('focus', verifySession)
    }
  }, [location.pathname])

  if (state === 'loading') {
    return (
      <div className="flex min-h-screen items-center justify-center bg-ce-offwhite text-[14px] text-ce-beige">
        Laden…
      </div>
    )
  }

  if (state === 'login') {
    return <Navigate to="/platform/login" replace state={{ from: location.pathname }} />
  }

  return <Outlet />
}

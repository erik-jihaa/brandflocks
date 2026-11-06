import { useEffect } from 'react'
import { logoutAuth } from '../api/account'

/** Inactiviteit tenant-sessie — gelijk aan server-side idle (60 min). */
export const TENANT_IDLE_MS = 60 * 60 * 1000

const ACTIVITY_EVENTS = ['mousedown', 'keydown', 'scroll', 'touchstart'] as const

/** Log tenant uit na 60 minuten zonder interactie. */
export function useTenantIdleLogout(enabled = true): void {
  useEffect(() => {
    if (!enabled) return

    let timer = 0

    const logout = () => {
      void logoutAuth().finally(() => {
        window.location.href = '/login'
      })
    }

    const reset = () => {
      window.clearTimeout(timer)
      timer = window.setTimeout(logout, TENANT_IDLE_MS)
    }

    for (const event of ACTIVITY_EVENTS) {
      window.addEventListener(event, reset, { passive: true })
    }
    reset()

    return () => {
      window.clearTimeout(timer)
      for (const event of ACTIVITY_EVENTS) {
        window.removeEventListener(event, reset)
      }
    }
  }, [enabled])
}

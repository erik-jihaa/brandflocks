import { useCallback, useEffect, useRef, useState } from 'react'
import type { SaveNoticeKind } from './useSaveNotice'

/** Zichtbaar vóór fade — actie-feedback (Check, upload, map bewerken, …). */
export const ACTION_NOTICE_VISIBLE_MS = 5000
const ACTION_NOTICE_FADE_MS = 500

export function useActionNotice() {
  const [message, setMessage] = useState<string | null>(null)
  const [kind, setKind] = useState<SaveNoticeKind>('success')
  const [fading, setFading] = useState(false)
  const timersRef = useRef<number[]>([])

  const clearTimers = useCallback(() => {
    timersRef.current.forEach((id) => window.clearTimeout(id))
    timersRef.current = []
  }, [])

  const scheduleDismiss = useCallback(() => {
    clearTimers()
    setFading(false)
    const fadeTimer = window.setTimeout(() => setFading(true), ACTION_NOTICE_VISIBLE_MS)
    const clearTimer = window.setTimeout(() => {
      setMessage(null)
      setFading(false)
    }, ACTION_NOTICE_VISIBLE_MS + ACTION_NOTICE_FADE_MS)
    timersRef.current = [fadeTimer, clearTimer]
  }, [clearTimers])

  useEffect(() => () => clearTimers(), [clearTimers])

  const clear = useCallback(() => {
    clearTimers()
    setMessage(null)
    setFading(false)
  }, [clearTimers])

  const showSuccess = useCallback(
    (text: string) => {
      setKind('success')
      setMessage(text)
      scheduleDismiss()
    },
    [scheduleDismiss],
  )

  const showError = useCallback(
    (text: string) => {
      setKind('error')
      setMessage(text)
      scheduleDismiss()
    },
    [scheduleDismiss],
  )

  const showInfo = useCallback(
    (text: string) => {
      clearTimers()
      setFading(false)
      setKind('info')
      setMessage(text)
    },
    [clearTimers],
  )

  return { message, kind, fading, showSuccess, showError, showInfo, clear }
}

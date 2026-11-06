import { useCallback, useEffect, useRef, useState } from 'react'

export type SaveNoticeKind = 'success' | 'error' | 'saving' | 'info'

const FADE_AFTER_MS = 8000
const FADE_DURATION_MS = 500

export function useSaveNotice(defaultSuccess = 'Opgeslagen') {
  const [message, setMessage] = useState<string | null>(null)
  const [kind, setKind] = useState<SaveNoticeKind>('success')
  const [fading, setFading] = useState(false)
  const timersRef = useRef<number[]>([])

  const clearTimers = useCallback(() => {
    timersRef.current.forEach((id) => window.clearTimeout(id))
    timersRef.current = []
  }, [])

  const scheduleFade = useCallback(() => {
    clearTimers()
    setFading(false)
    const fadeTimer = window.setTimeout(() => setFading(true), FADE_AFTER_MS)
    const clearTimer = window.setTimeout(() => {
      setMessage(null)
      setFading(false)
    }, FADE_AFTER_MS + FADE_DURATION_MS)
    timersRef.current = [fadeTimer, clearTimer]
  }, [clearTimers])

  useEffect(() => () => clearTimers(), [clearTimers])

  const clear = useCallback(() => {
    clearTimers()
    setMessage(null)
    setFading(false)
  }, [clearTimers])

  const showSaving = useCallback(
    (text = 'Opslaan…') => {
      clearTimers()
      setFading(false)
      setKind('saving')
      setMessage(text)
    },
    [clearTimers],
  )

  const showSuccess = useCallback(
    (text?: string) => {
      setKind('success')
      setMessage(text ?? defaultSuccess)
      scheduleFade()
    },
    [defaultSuccess, scheduleFade],
  )

  const showError = useCallback(
    (text: string) => {
      setKind('error')
      setMessage(text)
      scheduleFade()
    },
    [scheduleFade],
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

  return { message, kind, fading, showSaving, showSuccess, showError, showInfo, clear }
}

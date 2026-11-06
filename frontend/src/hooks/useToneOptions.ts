import { useCallback, useEffect, useState } from 'react'
import { fetchStrategy } from '../api/onboarding'
import { isApiConfigured } from '../api/client'
import { toneOptionsFromStyles } from '../utils/toneStyles'

/** Tenant-specifieke tone-labels uit Strategy (schrijfwijzer). */
export function useToneOptions() {
  const [toneOptions, setToneOptions] = useState<string[]>([])
  const [loading, setLoading] = useState(isApiConfigured())

  const load = useCallback(async () => {
    if (!isApiConfigured()) {
      setToneOptions([])
      setLoading(false)
      return
    }
    setLoading(true)
    try {
      const data = await fetchStrategy()
      setToneOptions(toneOptionsFromStyles(data.strategy?.toneStyles))
    } catch {
      setToneOptions([])
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  return { toneOptions, loading, reload: load }
}

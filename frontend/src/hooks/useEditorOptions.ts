import { useCallback, useEffect, useState } from 'react'
import { fetchStrategy } from '../api/onboarding'
import { isApiConfigured } from '../api/client'
import { editorOptionsFromStyles } from '../utils/editorStyles'

export function useEditorOptions(fallbackEditor?: string | null) {
  const [editorOptions, setEditorOptions] = useState<string[]>([])
  const [loading, setLoading] = useState(true)

  const load = useCallback(async () => {
    if (!isApiConfigured()) {
      setEditorOptions(editorOptionsFromStyles(undefined, fallbackEditor))
      setLoading(false)
      return
    }

    try {
      const data = await fetchStrategy()
      setEditorOptions(editorOptionsFromStyles(data.strategy?.editorStyles, fallbackEditor))
    } catch {
      setEditorOptions(editorOptionsFromStyles(undefined, fallbackEditor))
    } finally {
      setLoading(false)
    }
  }, [fallbackEditor])

  useEffect(() => {
    void load()
  }, [load])

  return { editorOptions, loading, reload: load }
}

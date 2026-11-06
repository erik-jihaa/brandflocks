import { useEffect, useRef } from 'react'
import { useSearchParams } from 'react-router-dom'

const HIGHLIGHT_CLASS = 'ring-[1.5px] ring-ce-blue ring-offset-2 ring-offset-ce-offwhite'

export function useFocusSection(sectionIds: string[]) {
  const [searchParams, setSearchParams] = useSearchParams()
  const focus = searchParams.get('focus')
  const clearedRef = useRef<string | null>(null)

  useEffect(() => {
    if (!focus || !sectionIds.includes(focus) || clearedRef.current === focus) return

    const el = document.getElementById(focus)
    if (!el) return

    clearedRef.current = focus
    el.scrollIntoView({ block: 'center', behavior: 'smooth' })
    el.classList.add(HIGHLIGHT_CLASS)

    const next = new URLSearchParams(searchParams)
    next.delete('focus')
    setSearchParams(next, { replace: true })

    const timer = window.setTimeout(() => {
      el.classList.remove(HIGHLIGHT_CLASS)
    }, 2000)

    return () => window.clearTimeout(timer)
  }, [focus, sectionIds, searchParams, setSearchParams])
}

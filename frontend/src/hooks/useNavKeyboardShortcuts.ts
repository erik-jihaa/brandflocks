import { useEffect } from 'react'
import { useNavigate } from 'react-router-dom'

function isEditableTarget(target: EventTarget | null): boolean {
  if (!(target instanceof HTMLElement)) return false
  if (target.isContentEditable) return true
  const tag = target.tagName
  return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT'
}

/** Globale navigatie: Option+A Agenda, Shift+Option+A Article, Option+T Themes, Option+R Research. */
export function useNavKeyboardShortcuts() {
  const navigate = useNavigate()

  useEffect(() => {
    function onKeyDown(event: KeyboardEvent) {
      if (!event.altKey || event.metaKey || event.ctrlKey) return
      if (isEditableTarget(event.target)) return

      if (event.code === 'KeyA') {
        event.preventDefault()
        navigate(event.shiftKey ? '/article' : '/agenda')
        return
      }

      if (event.shiftKey) return

      if (event.code === 'KeyT') {
        event.preventDefault()
        navigate('/theme')
        return
      }

      if (event.code === 'KeyR') {
        event.preventDefault()
        navigate('/research')
      }
    }

    window.addEventListener('keydown', onKeyDown)
    return () => window.removeEventListener('keydown', onKeyDown)
  }, [navigate])
}

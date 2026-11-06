import { useEffect, useState } from 'react'
import { statusRowActionClass } from './ActionLink'

/** Tweede stap na Delete — rood, underline alleen bij hover */
export const statusRowConfirmDeleteClass =
  'cursor-pointer border-0 bg-transparent p-0 font-semibold text-[14px] leading-[18px] text-ce-red no-underline hover:underline hover:decoration-ce-red'

type DeleteConfirmActionProps = {
  onConfirm: () => void
  disabled?: boolean
  busy?: boolean
  busyLabel?: string
  confirmLabel?: string
  deleteLabel?: string
  className?: string
  /** Bevestigingsstap (rood); default semibold statusrij-stijl. */
  confirmClassName?: string
  /** Wijziging reset de bevestigingsstap (bijv. andere actie op dezelfde rij). */
  resetSignal?: unknown
  onConfirmingChange?: (confirming: boolean) => void
  onMouseDown?: (event: React.MouseEvent<HTMLButtonElement>) => void
}

/** Inline twee-staps delete: Delete → rode Confirm delete. Geen browser-popup. */
export function DeleteConfirmAction({
  onConfirm,
  disabled = false,
  busy = false,
  busyLabel = 'Deleting…',
  confirmLabel = 'Bevestig verwijderen',
  deleteLabel = 'Verwijderen',
  className,
  confirmClassName,
  resetSignal,
  onConfirmingChange,
  onMouseDown,
}: DeleteConfirmActionProps) {
  const [confirming, setConfirming] = useState(false)

  useEffect(() => {
    if (!busy) {
      setConfirming(false)
    }
  }, [busy])

  useEffect(() => {
    setConfirming(false)
  }, [resetSignal])

  useEffect(() => {
    onConfirmingChange?.(confirming)
  }, [confirming, onConfirmingChange])

  function handleClick() {
    if (disabled || busy) {
      return
    }
    if (!confirming) {
      setConfirming(true)
      return
    }
    onConfirm()
  }

  const deleteClass = className ?? statusRowActionClass
  const confirmClass = confirmClassName ?? statusRowConfirmDeleteClass

  if (confirming) {
    return (
      <button
        type="button"
        className={`${confirmClass} ${disabled || busy ? 'cursor-not-allowed opacity-45' : ''}`}
        onClick={handleClick}
        onMouseDown={onMouseDown}
        disabled={disabled || busy}
      >
        {busy ? busyLabel : confirmLabel}
      </button>
    )
  }

  return (
    <button
      type="button"
      className={`${deleteClass} ${disabled || busy ? 'cursor-not-allowed opacity-45' : ''}`}
      onClick={handleClick}
      onMouseDown={onMouseDown}
      disabled={disabled || busy}
    >
      {deleteLabel}
    </button>
  )
}

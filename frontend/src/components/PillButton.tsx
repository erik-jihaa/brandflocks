import { LoadingDotsInline } from './LoadingDots'

export {
  pillActionsRowClass,
  pillActionsRowWithNavLinkClass,
  pillActionsRowInnerClass,
  pillLineGapBeforeClass,
  pillLineGapAfterClass,
  designStackClass,
  designItemStackClass,
} from '../design/identity.constants'

type PillButtonProps = {
  variant?: 'main' | 'second'
  children: React.ReactNode
  type?: 'button' | 'submit'
  onClick?: () => void
  className?: string
  disabled?: boolean
  /** Beige pill + animerende puntjes (zelfde als fastlane/research-wachtstatus). */
  loading?: boolean
}

export function pillButtonClass(
  variant: 'main' | 'second' = 'main',
  { disabled = false, className = '' }: { disabled?: boolean; className?: string } = {},
) {
  const base =
    'mb-[18px] inline-flex h-[40px] self-start items-center justify-center rounded-[20px] border px-[30px] text-[14px] font-semibold leading-none'

  if (disabled) {
    return `${base} cursor-not-allowed border-ce-beige bg-transparent text-ce-beige ${className}`.trim()
  }

  const styles =
    variant === 'main'
      ? 'cursor-pointer border-ce-green bg-ce-green text-ce-blue'
      : 'cursor-pointer border-ce-green bg-transparent text-ce-blue'

  return `${base} ${styles} ${className}`.trim()
}

/** Label in pill-knoppen die geen PillButton-component gebruiken (Link, custom button). */
export function PillButtonContent({
  loading = false,
  children,
}: {
  loading?: boolean
  children: React.ReactNode
}) {
  if (loading) {
    return (
      <>
        <LoadingDotsInline />
        <span className="sr-only">Bezig</span>
      </>
    )
  }

  return <>{children}</>
}

/**
 * Artboard 20 — pill 40px; outline 1px.
 * main = groen volvlak + blauwe tekst (gewenste actie in context).
 * second = groen outline + blauwe tekst (alternatieve actie).
 * disabled = beige outline + tekst.
 */
export function PillButton({
  variant = 'main',
  children,
  type = 'button',
  onClick,
  className = '',
  disabled = false,
  loading = false,
}: PillButtonProps) {
  const isDisabled = disabled || loading

  return (
    <button
      type={type}
      disabled={isDisabled}
      className={pillButtonClass(variant, { disabled: isDisabled, className })}
      onClick={onClick}
    >
      {loading ? (
        <>
          <LoadingDotsInline />
          <span className="sr-only">Bezig</span>
        </>
      ) : (
        children
      )}
    </button>
  )
}

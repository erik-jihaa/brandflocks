import { hyperlinkClassBody, hyperlinkClassLg } from '../../design/identity.constants'

type ActionLinkProps = {
  children: React.ReactNode
  onClick?: () => void
  onMouseDown?: (event: React.MouseEvent<HTMLButtonElement>) => void
  className?: string
  disabled?: boolean
}

const actionLinkButtonBase = 'cursor-pointer border-0 bg-transparent p-0'

export const actionLinkClass = `${actionLinkButtonBase} ${hyperlinkClassLg}`

/** Actieve agenda-/statusrij-links — blauw, underline bij hover */
export const statusRowActionClass = `${actionLinkButtonBase} ${hyperlinkClassBody}`

/** Niet-actieve agenda-acties — beige, geen hover */
export const statusRowActionStaticClass =
  'cursor-default border-0 bg-transparent p-0 font-semibold text-[14px] leading-[18px] text-ce-beige no-underline pointer-events-none'

/** Verlopen week — Delete en Archive blijven actief (blauw) */
export const statusRowArchiveClass = statusRowActionClass

/** Verlopen week — overige acties niet klikbaar */
export const statusRowDimmedStaticClass =
  'cursor-default border-0 bg-transparent p-0 font-semibold text-[14px] leading-[18px] text-ce-beige no-underline pointer-events-none'

/** Actielink — blauw, underline bij hover */
export function ActionLink({
  children,
  onClick,
  onMouseDown,
  className = '',
  disabled = false,
  variant = 'default',
}: ActionLinkProps & { variant?: 'default' | 'statusRow' | 'archive' }) {
  const baseClass =
    variant === 'archive'
      ? statusRowArchiveClass
      : variant === 'statusRow'
        ? statusRowActionClass
        : actionLinkClass

  return (
    <button
      type="button"
      onClick={onClick}
      onMouseDown={onMouseDown}
      disabled={disabled}
      className={`${baseClass} ${disabled ? 'cursor-not-allowed opacity-45' : ''} ${className}`}
    >
      {children}
    </button>
  )
}

export function ActionSep({ dimmed = true }: { dimmed?: boolean }) {
  return (
    <span className={`mx-[6px] ${dimmed ? 'text-ce-beige' : 'text-ce-black'}`}>|</span>
  )
}

export type PostStatus = 'draft' | 'pending' | 'approved' | 'feedback' | 'published' | 'not-published' | 'themed' | 'research_done'

const STATUS_COLOR: Record<PostStatus, string> = {
  draft: 'text-ce-black',
  pending: 'text-ce-black',
  themed: 'text-ce-black',
  research_done: 'text-ce-black',
  approved: 'text-ce-blue',
  feedback: 'text-ce-red',
  published: 'text-ce-green-text',
  'not-published': 'text-ce-black',
}

const COLORED_STATUSES = new Set<PostStatus>(['approved', 'feedback', 'published'])

export function StatusLabel({
  status,
  label,
  dimmed = false,
}: {
  status: PostStatus
  label: string
  dimmed?: boolean
}) {
  const colorClass = dimmed
    ? COLORED_STATUSES.has(status)
      ? STATUS_COLOR[status]
      : 'text-ce-beige'
    : STATUS_COLOR[status]

  return (
    <span className={`text-[14px] leading-[18px] font-semibold ${colorClass}`}>
      {label}
    </span>
  )
}

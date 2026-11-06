import type { IntegrationStatus } from '../api/integrations'

export function integrationStatusDotClass(status: IntegrationStatus): string {
  switch (status) {
    case 'connected':
      return 'bg-ce-green-fill'
    case 'action_required':
      return 'bg-ce-red'
    case 'unavailable':
      return 'bg-ce-grey'
    default:
      return 'bg-ce-beige'
  }
}

export function integrationStatusTextClass(status: IntegrationStatus): string {
  switch (status) {
    case 'connected':
      return 'text-ce-green-text'
    case 'action_required':
      return 'text-ce-red'
    case 'unavailable':
      return 'text-ce-beige'
    default:
      return 'text-ce-beige'
  }
}

export function IntegrationStatusPill({
  status,
  label,
  className = '',
}: {
  status: IntegrationStatus
  label: string
  className?: string
}) {
  return (
    <span
      className={`inline-flex items-center gap-[8px] text-[14px] font-semibold leading-[18px] ${integrationStatusTextClass(status)} ${className}`.trim()}
    >
      <span
        className={`inline-block h-[8px] w-[8px] shrink-0 rounded-full ${integrationStatusDotClass(status)}`}
        aria-hidden
      />
      {label}
    </span>
  )
}

import type { PlatformTenantRow } from '../../api/platform'

/** Tenant-status — iGiSNext tokens (Sources check-dot patroon). */
export function platformStatusDotClass(status: PlatformTenantRow['status']): string {
  switch (status) {
    case 'green':
      return 'bg-ce-green-fill'
    case 'orange':
      return 'bg-ce-blue'
    case 'red':
      return 'bg-ce-red'
    default:
      return 'bg-ce-beige'
  }
}

export function PlatformStatusDot({
  status,
  title,
  className = '',
}: {
  status: PlatformTenantRow['status']
  title?: string
  className?: string
}) {
  return (
    <span
      className={`inline-block h-[8px] w-[8px] shrink-0 rounded-full ${platformStatusDotClass(status)} ${className}`.trim()}
      aria-hidden={title ? undefined : true}
      title={title}
    />
  )
}

export function platformStatusLabel(status: PlatformTenantRow['status']): string {
  switch (status) {
    case 'green':
      return 'Gezond — geen fouten, Rolling Agenda OK'
    case 'orange':
      return 'Aandacht — configuratie of inactiviteit'
    case 'red':
      return 'Probleem — fouten of Rolling Agenda-signalen'
    default:
      return status
  }
}

export const PLATFORM_STATUS_LEGEND: {
  status: PlatformTenantRow['status']
  label: string
  detail: string
}[] = [
  {
    status: 'green',
    label: 'Gezond',
    detail: 'Geen fouten (7d), geen Rolling Agenda-signalen, recent ingelogd',
  },
  {
    status: 'orange',
    label: 'Aandacht',
    detail: 'CMS incompleet, of >14 dagen niet ingelogd',
  },
  {
    status: 'red',
    label: 'Probleem',
    detail: 'Fouten in event_log (7d) of vastgelopen Rolling Agenda',
  },
]

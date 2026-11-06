import { LIST_ROW } from '../../layout/grid.constants'

type PlatformDiagnosticRowProps = {
  label: string
  value: string
  tone?: 'default' | 'ok' | 'warn' | 'error'
}

function toneClass(tone: PlatformDiagnosticRowProps['tone']): string {
  switch (tone) {
    case 'ok':
      return 'text-ce-green-text'
    case 'warn':
      return 'text-ce-beige'
    case 'error':
      return 'text-ce-red'
    default:
      return 'text-ce-black'
  }
}

/** LIST_ROW diagnostic row — label links, status rechts (Agenda/identity pattern). */
export function PlatformDiagnosticRow({ label, value, tone = 'default' }: PlatformDiagnosticRowProps) {
  return (
    <div className={`${LIST_ROW.rowClass} border-b border-ce-black/10 pb-[10px] text-ce-black`}>
      <span className="text-ce-black">{label}</span>
      <span className={`${LIST_ROW.actionsClass} font-semibold ${toneClass(tone)}`}>{value}</span>
    </div>
  )
}

import { Link } from 'react-router-dom'
import { LIST_ROW, LIST_SECTION } from '../layout/grid.constants'
import { PillButton, pillActionsRowWithNavLinkClass, pillButtonClass } from './PillButton'
import { PageNavLink } from './ui/PageNavLink'
import { IntegrationStatusPill } from './IntegrationStatusPill'
import type { IntegrationStatusEntry } from '../api/integrations'
import type { IntegrationDef } from '../config/integrations'

type IntegrationCardProps = {
  def: IntegrationDef
  status: IntegrationStatusEntry
  expanded: boolean
  onToggle: () => void
  onConnect?: () => void
  onDisconnect?: () => void
  onTest?: () => void
  connectLabel?: string
  disconnectLabel?: string
  testLabel?: string
  busy?: boolean
  testing?: boolean
  testNote?: string | null
  testOk?: boolean | null
  showConnect?: boolean
  showDisconnect?: boolean
  showTest?: boolean
  connectVariant?: 'main' | 'second'
  settingsLabel?: string
  settingsHref?: string
  settingsAsPill?: boolean
  settingsPillVariant?: 'main' | 'second'
  settingsFirst?: boolean
  /** Extra pills vóór connect/test (bijv. Opslaan op Website-kaart). */
  leadingActions?: React.ReactNode
  /** Feedback onder de actierij (bijv. opgeslagen-melding). */
  actionNotice?: React.ReactNode
  children?: React.ReactNode
}

export function IntegrationCard({
  def,
  status,
  expanded,
  onToggle,
  onConnect,
  onDisconnect,
  onTest,
  connectLabel = 'Koppel',
  disconnectLabel = 'Ontkoppel',
  testLabel = 'Test',
  busy = false,
  testing = false,
  testNote = null,
  testOk = null,
  showConnect = false,
  showDisconnect = false,
  showTest = true,
  connectVariant = 'main',
  settingsLabel = 'Instellingen',
  settingsHref,
  settingsAsPill = false,
  settingsPillVariant = 'main',
  settingsFirst = false,
  leadingActions,
  actionNotice,
  children,
}: IntegrationCardProps) {
  const resolvedSettingsHref = settingsHref ?? def.settingsHref
  const canConnect = showConnect && status.status !== 'unavailable'
  const canDisconnect = showDisconnect && status.status !== 'not_set' && status.status !== 'unavailable'

  const settingsLink =
    resolvedSettingsHref &&
    (settingsAsPill ? (
      <Link
        to={resolvedSettingsHref}
        className={pillButtonClass(settingsPillVariant)}
        onClick={(e) => e.stopPropagation()}
      >
        {settingsLabel}
      </Link>
    ) : (
      <PageNavLink to={resolvedSettingsHref} onClick={(e) => e.stopPropagation()}>
        {settingsLabel}
      </PageNavLink>
    ))

  const connectButton =
    canConnect ? (
      <PillButton variant={connectVariant} disabled={busy} loading={busy} onClick={() => onConnect?.()}>
        {connectLabel}
      </PillButton>
    ) : null

  const disconnectButton =
    canDisconnect ? (
      <PillButton variant="second" disabled={busy} loading={busy} onClick={() => onDisconnect?.()}>
        {disconnectLabel}
      </PillButton>
    ) : null

  const testButton = showTest ? (
    <PillButton variant="second" disabled={testing || busy} loading={testing} onClick={() => onTest?.()}>
      {testLabel}
    </PillButton>
  ) : null

  return (
    <div className={LIST_SECTION.sourcesItemClass}>
      <button
        type="button"
        className="w-full cursor-pointer border-0 bg-transparent p-0 text-left"
        onClick={onToggle}
        aria-expanded={expanded}
      >
        <div className={`${LIST_ROW.rowClass} font-semibold text-ce-black`}>
          <span className="min-w-0">{def.name}</span>
          <IntegrationStatusPill
            status={status.status}
            label={status.label}
            className={`${LIST_ROW.actionsClass} font-semibold`}
          />
        </div>
        {status.context ? (
          <p className="m-0 mt-[4px] text-[14px] leading-[18px] text-ce-beige">{status.context}</p>
        ) : null}
        <p className="m-0 mt-[4px] text-[14px] leading-[18px] text-ce-black">{def.purpose}</p>
        {status.note && status.status === 'action_required' && !expanded ? (
          <p className="m-0 mt-[8px] text-[14px] leading-[18px] text-ce-red">{status.note}</p>
        ) : null}
      </button>

      {expanded ? (
        <div className="mt-[16px]">
          {status.note && status.status === 'action_required' ? (
            <p className="m-0 mb-[12px] text-[14px] leading-[18px] text-ce-red">{status.note}</p>
          ) : null}
          {status.note && status.status === 'unavailable' ? (
            <p className="m-0 mb-[12px] text-[14px] leading-[18px] text-ce-beige">{status.note}</p>
          ) : null}
          {children}
          <div className={pillActionsRowWithNavLinkClass}>
            {leadingActions}
            {settingsFirst ? settingsLink : null}
            {connectButton}
            {disconnectButton}
            {testButton}
            {!settingsFirst ? settingsLink : null}
          </div>
          {actionNotice || testNote ? (
            <div className="mt-[10px]">
              {actionNotice}
              {testNote ? (
                <p
                  className={`m-0 text-[14px] leading-[18px] ${actionNotice ? 'mt-[8px]' : ''} ${testOk ? 'text-ce-blue' : 'text-ce-red'}`}
                >
                  {testNote}
                </p>
              ) : null}
            </div>
          ) : null}
        </div>
      ) : null}
    </div>
  )
}

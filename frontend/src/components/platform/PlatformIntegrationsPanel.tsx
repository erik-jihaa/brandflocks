import { LIST_ROW, LIST_SECTION } from '../../layout/grid.constants'
import { INTEGRATION_GROUPS, integrationsForGroup, type IntegrationDef } from '../../config/integrations'
import { IntegrationStatusPill } from '../IntegrationStatusPill'
import type { IntegrationStatusEntry } from '../../api/integrations'

type PlatformIntegrationsPanelProps = {
  integrations: Record<string, IntegrationStatusEntry>
  summary?: { active: number; actionRequired: number; notSet: number }
  compact?: boolean
}

function PlatformIntegrationRow({
  def,
  entry,
}: {
  def: IntegrationDef
  entry: IntegrationStatusEntry
}) {
  return (
    <div className={LIST_SECTION.sourcesItemClass}>
      <div className={`${LIST_ROW.rowClass} font-semibold text-ce-black`}>
        <span className="min-w-0">{def.name}</span>
        <IntegrationStatusPill
          status={entry.status}
          label={entry.label}
          className={`${LIST_ROW.actionsClass} font-semibold`}
        />
      </div>
      {entry.context ? (
        <p className="m-0 mt-[4px] text-[14px] leading-[18px] text-ce-beige">{entry.context}</p>
      ) : null}
      <p className="m-0 mt-[4px] text-[14px] leading-[18px] text-ce-black">{def.purpose}</p>
      {entry.note && entry.status === 'action_required' ? (
        <p className="m-0 mt-[8px] text-[14px] leading-[18px] text-ce-red">{entry.note}</p>
      ) : null}
      {entry.note && entry.status === 'unavailable' ? (
        <p className="m-0 mt-[8px] text-[14px] leading-[18px] text-ce-beige">{entry.note}</p>
      ) : null}
    </div>
  )
}

/** Read-only integratiestatus voor platform — zelfde registry/groepen als tenant Integrations. */
export function PlatformIntegrationsPanel({
  integrations,
  summary,
  compact = false,
}: PlatformIntegrationsPanelProps) {
  const fallback: IntegrationStatusEntry = {
    status: 'not_set',
    label: 'Niet ingesteld',
    context: '',
    note: null,
    since: null,
    meta: {},
  }

  return (
    <div className={LIST_SECTION.bodyStackClass}>
      {summary && !compact ? (
        <p className={`m-0 ${LIST_SECTION.itemClass} text-[14px] leading-[18px] text-ce-black`}>
          Koppelingen{' '}
          <span className="text-ce-green-text">{summary.active} actief</span>
          {' · '}
          <span className={summary.actionRequired > 0 ? 'text-ce-red' : 'text-ce-beige'}>
            {summary.actionRequired} actie nodig
          </span>
          {' · '}
          <span className="text-ce-beige">{summary.notSet} niet ingesteld</span>
        </p>
      ) : null}

      {INTEGRATION_GROUPS.map((group) => {
        const defs = integrationsForGroup(group.key)
        if (defs.length === 0) return null

        return (
          <section key={group.key} className={LIST_SECTION.sectionClass}>
            <h4 className={`m-0 ${LIST_SECTION.headerClass} text-ce-black`}>{group.label}</h4>
            <div className="mt-[20px]">
              {defs.map((def) => (
                <PlatformIntegrationRow
                  key={def.key}
                  def={def}
                  entry={integrations[def.key] ?? fallback}
                />
              ))}
            </div>
          </section>
        )
      })}
    </div>
  )
}

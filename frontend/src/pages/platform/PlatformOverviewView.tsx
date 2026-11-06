import { LIST_SECTION } from '../../layout/grid.constants'
import { usePlatformData } from '../../components/platform/PlatformDataContext'
import { PlatformActionItemsSection } from '../../components/platform/PlatformActionItemsSection'
import { PlatformEventLogSection } from '../../components/platform/PlatformEventLogSection'
import { PlatformFinancePanel } from '../../components/platform/PlatformFinancePanel'
import { PLATFORM_STATUS_LEGEND, PlatformStatusDot } from '../../components/platform/platformStatus'
import { formatEur, formatCount } from '../../components/platform/platformFormat'
import { collectPlatformActionItems } from '../../utils/platformActionItems'

export function PlatformOverviewView() {
  const { loading, error, status, costsTotal, totalN8nExecutions, events, tenants } = usePlatformData()

  if (loading) {
    return <p className="m-0 text-[14px] leading-[18px] text-ce-beige">Laden…</p>
  }
  if (error) {
    return <p className="m-0 text-[14px] leading-[18px] text-ce-red">{error}</p>
  }

  const problemCount = tenants.filter((t) => t.status === 'red').length
  const watchCount = tenants.filter((t) => t.status === 'orange').length
  const healthyCount = tenants.filter((t) => t.status === 'green').length
  const actionItems = collectPlatformActionItems(tenants, events)

  return (
    <div className={`${LIST_SECTION.bodyStackClass}`}>
      {status && (
        <section className={LIST_SECTION.bodyClass}>
          <h2 className={`${LIST_SECTION.headerClass} text-ce-blue`}>Platform</h2>
          <dl className="m-0 mt-[20px] grid gap-[12px] text-[14px] leading-[18px] md:grid-cols-2">
            <div>
              <dt className="font-semibold">Omgeving</dt>
              <dd className="m-0">{status.environment}</dd>
            </div>
            <div>
              <dt className="font-semibold">Tenants</dt>
              <dd className="m-0">{status.tenantCount}</dd>
            </div>
            <div>
              <dt className="font-semibold">Fouten 24u</dt>
              <dd className={`m-0 ${status.errors24h > 0 ? 'text-ce-red' : ''}`}>{status.errors24h}</dd>
            </div>
            <div>
              <dt className="font-semibold">Kosten deze maand</dt>
              <dd className="m-0">{formatEur(costsTotal)}</dd>
            </div>
            <div>
              <dt className="font-semibold">n8n executions deze maand</dt>
              <dd className="m-0">{formatCount(totalN8nExecutions)}</dd>
            </div>
          </dl>
        </section>
      )}

      <section className={LIST_SECTION.bodyClass}>
        <h2 className={`${LIST_SECTION.headerClass} text-ce-blue`}>Tenant-status</h2>
        <p className="m-0 mt-[12px] text-[14px] leading-[18px] text-ce-beige">
          De gekleurde bal in de navigatie toont de gezondheid per tenant.
        </p>
        <ul className="m-0 mt-[20px] list-none space-y-[12px] p-0">
          {PLATFORM_STATUS_LEGEND.map((item) => (
            <li key={item.status} className="flex items-start gap-[10px] text-[14px] leading-[18px]">
              <PlatformStatusDot status={item.status} className="mt-[6px]" />
              <div>
                <div className="font-semibold">{item.label}</div>
                <div className="text-ce-beige">{item.detail}</div>
              </div>
            </li>
          ))}
        </ul>
        <p className="m-0 mt-[20px] text-[14px] leading-[18px] text-ce-black">
          Nu: {healthyCount} gezond · {watchCount} aandacht · {problemCount} probleem
        </p>
      </section>

      <PlatformActionItemsSection items={actionItems} />

      <PlatformFinancePanel compact />

      <PlatformEventLogSection
        events={events}
        title="Recente fouten (7 dagen)"
        emptyLabel="Geen fouten in de afgelopen 7 dagen"
      />
    </div>
  )
}

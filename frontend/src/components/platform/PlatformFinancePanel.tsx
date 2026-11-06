import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { LIST_SECTION } from '../../layout/grid.constants'
import { hyperlinkClassBody } from '../../design/identity.constants'
import { fetchPlatformCosts, type PlatformCostBreakdownLine, type PlatformCostTenantRow, type PlatformCostsTrend } from '../../api/platform'
import { formatEur, formatCount } from './platformFormat'
import { PlatformCostsTrend as PlatformCostsTrendChart } from './PlatformCostsTrend'
import { PlatformStatusDot } from './platformStatus'
import { usePlatformData } from './PlatformDataContext'

function currentMonth(): string {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
}

function monthLabel(month: string): string {
  const [y, m] = month.split('-')
  const names = ['jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec']
  const idx = parseInt(m ?? '1', 10) - 1
  return `${names[idx] ?? m} ${y}`
}

function shiftMonth(month: string, delta: number): string {
  const [y, m] = month.split('-').map(Number)
  const d = new Date(y, (m ?? 1) - 1 + delta, 1)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
}

function clampMonth(month: string): string {
  const max = currentMonth()
  return month > max ? max : month
}

type PlatformFinancePanelProps = {
  compact?: boolean
}

/** Totaal financieel overzicht + gebruik per tenant. */
export function PlatformFinancePanel({ compact = false }: PlatformFinancePanelProps) {
  const { tenants } = usePlatformData()
  const maxMonth = currentMonth()
  const [month, setMonth] = useState(() => clampMonth(currentMonth()))
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [grandTotal, setGrandTotal] = useState<number | null>(null)
  const [previousTotal, setPreviousTotal] = useState<number | null>(null)
  const [previousMonth, setPreviousMonth] = useState<string | null>(null)
  const [breakdown, setBreakdown] = useState<PlatformCostBreakdownLine[]>([])
  const [rows, setRows] = useState<PlatformCostTenantRow[]>([])
  const [totalN8nExecutions, setTotalN8nExecutions] = useState<number | null>(null)
  const [trend, setTrend] = useState<PlatformCostsTrend | null>(null)
  const [expandedTenant, setExpandedTenant] = useState<string | null>(null)

  const canGoForward = month < maxMonth

  useEffect(() => {
    setMonth((m) => clampMonth(m))
  }, [maxMonth])

  useEffect(() => {
    setLoading(true)
    setError(null)
    void fetchPlatformCosts(month)
      .then((res) => {
        const resolvedMonth = clampMonth(res.month ?? month)
        if (resolvedMonth !== month) {
          setMonth(resolvedMonth)
        }
        setGrandTotal(res.grandTotalEur)
        setPreviousTotal(res.previousGrandTotalEur ?? null)
        setPreviousMonth(res.previousMonth ?? null)
        setBreakdown(res.breakdown ?? [])
        setRows(res.tenants ?? [])
        setTotalN8nExecutions(res.totalN8nExecutions ?? null)
        setTrend(res.trend ?? null)
      })
      .catch((err) => {
        setError(err instanceof Error ? err.message : 'Laden mislukt')
      })
      .finally(() => setLoading(false))
  }, [month])

  const delta = useMemo(() => {
    if (grandTotal == null || previousTotal == null) return null
    return grandTotal - previousTotal
  }, [grandTotal, previousTotal])

  if (loading) {
    return <p className="m-0 text-[14px] leading-[18px] text-ce-beige">Finance laden…</p>
  }
  if (error) {
    return <p className="m-0 text-[14px] leading-[18px] text-ce-red">{error}</p>
  }

  return (
    <div className={LIST_SECTION.bodyStackClass}>
      <section className={LIST_SECTION.bodyClass}>
        <div className="flex flex-wrap items-baseline justify-between gap-[12px]">
          <h2 className={`m-0 ${LIST_SECTION.headerClass} text-ce-blue`}>
            {compact ? 'Kosten deze maand' : 'Finance'}
          </h2>
          <div className="flex items-center gap-[8px] text-[14px] leading-[18px]">
            <button
              type="button"
              className="cursor-pointer border-0 bg-transparent p-0 font-semibold text-ce-black"
              onClick={() => setMonth((m) => shiftMonth(m, -1))}
              aria-label="Vorige maand"
            >
              ←
            </button>
            <span className="font-semibold text-ce-black">{monthLabel(month)}</span>
            <button
              type="button"
              className={`border-0 bg-transparent p-0 font-semibold ${
                canGoForward ? 'cursor-pointer text-ce-black' : 'cursor-default text-ce-beige'
              }`}
              onClick={() => {
                if (canGoForward) setMonth((m) => clampMonth(shiftMonth(m, 1)))
              }}
              disabled={!canGoForward}
              aria-label="Volgende maand"
            >
              →
            </button>
          </div>
        </div>

        {trend && !compact ? (
          <div className="mt-[20px]">
            <PlatformCostsTrendChart data={trend} />
          </div>
        ) : null}

        <p className={`m-0 ${trend && !compact ? 'mt-[20px]' : 'mt-[20px]'} text-[14px] leading-[18px]`}>
          Totaal: <strong>{formatEur(grandTotal)}</strong>
          {totalN8nExecutions != null && (
            <span className="text-ce-beige">
              {' '}
              · n8n: {formatCount(totalN8nExecutions)} executions (geschat)
            </span>
          )}
          {previousMonth && previousTotal != null && (
            <span className="text-ce-beige">
              {' '}
              · vorige maand ({monthLabel(previousMonth)}): {formatEur(previousTotal)}
              {delta != null && (
                <span className={delta > 0 ? ' text-ce-red' : delta < 0 ? ' text-ce-green-text' : ''}>
                  {' '}
                  ({delta > 0 ? '+' : ''}
                  {formatEur(delta)})
                </span>
              )}
            </span>
          )}
        </p>

        {breakdown.length > 0 && (
          <ul className="m-0 mt-[16px] list-none space-y-[6px] p-0 text-[14px] leading-[18px]">
            {breakdown.map((line) => (
              <li key={line.id} className="flex justify-between gap-[12px]">
                <span className="text-ce-black">{line.label}</span>
                <span className="shrink-0 font-semibold">{formatEur(line.eur)}</span>
              </li>
            ))}
          </ul>
        )}
      </section>

      <section>
        <h3 className={`m-0 ${LIST_SECTION.headerClass} text-ce-black`}>Per tenant</h3>
        <ul className="m-0 mt-[20px] list-none p-0">
          {rows.map((row) => {
            const tenant = tenants.find((t) => t.clientId === row.clientId)
            const expanded = expandedTenant === row.clientId
            const usage = row.usage

            return (
              <li
                key={row.clientId}
                className={`${LIST_SECTION.itemClass} text-[14px] leading-[18px]`}
              >
                <div className="flex items-baseline justify-between gap-[12px]">
                  <span className="flex min-w-0 items-center gap-[8px] font-semibold">
                    {tenant && <PlatformStatusDot status={tenant.status} />}
                    {!compact ? (
                      <Link to={`/platform/tenant/${row.clientId}`} className={`truncate ${hyperlinkClassBody}`}>
                        {row.company}
                      </Link>
                    ) : (
                      <span className="truncate">{row.company}</span>
                    )}
                  </span>
                  <span className="flex shrink-0 items-center gap-[8px]">
                    <span className="font-semibold">{formatEur(row.totalEur)}</span>
                    {(row.lines?.length ?? 0) > 0 && (
                      <button
                        type="button"
                        className="cursor-pointer border-0 bg-transparent p-0 text-[12px] font-semibold text-ce-blue"
                        onClick={() => setExpandedTenant(expanded ? null : row.clientId)}
                      >
                        {expanded ? '−' : '+'}
                      </button>
                    )}
                  </span>
                </div>
                {usage && (
                  <p className="m-0 mt-[4px] text-ce-beige">
                    {usage.articlesWritten} geschreven · {usage.articlesPublished} gepubliceerd
                    {usage.n8nExecutions > 0 ? ` · ${formatCount(usage.n8nExecutions)} n8n` : ''}
                    {usage.scraperRuns > 0 ? ` · ${usage.scraperRuns} scraper-runs` : ''}
                    {usage.apifyCostEur > 0 ? ` · Apify ${formatEur(usage.apifyCostEur)}` : ''}
                    {usage.apifyUnits != null && usage.apifyUnits > 0 ? ` · ${usage.apifyUnits.toFixed(2)} CU` : ''}
                  </p>
                )}
                {expanded && row.lines && row.lines.length > 0 && (
                  <ul className="m-0 mt-[8px] list-none space-y-[4px] border-l border-ce-beige/50 pl-[12px]">
                    {row.lines.map((line) => (
                      <li key={line.id} className="flex justify-between gap-[8px] text-ce-beige">
                        <span>{line.label}</span>
                        <span>{formatEur(line.eur)}</span>
                      </li>
                    ))}
                  </ul>
                )}
              </li>
            )
          })}
        </ul>
        {compact && (
          <div className="mt-[12px]">
            <Link to="/platform/finance" className={hyperlinkClassBody}>
              Volledig finance-overzicht →
            </Link>
          </div>
        )}
      </section>
    </div>
  )
}

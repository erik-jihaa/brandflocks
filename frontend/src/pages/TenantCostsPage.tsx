import { useEffect, useMemo, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { GridShell } from '../layout/GridShell'
import { GRID2_REVIEW_MAIN_WIDTH } from '../layout/grid.constants'
import { FieldSingle } from '../components/ui/Field'
import { CheckboxOption } from '../components/ui/RadioOption'
import { PillButton, pillActionsRowClass, designStackClass } from '../components/PillButton'
import { SaveNotice } from '../components/ui/SaveNotice'
import { useSaveNotice } from '../hooks/useSaveNotice'
import { fetchTenantCosts } from '../api/tenantCosts'
import { isApiConfigured } from '../api/client'
import {
  calculateTenantCosts,
  currentMonthValue,
  formatEur,
  projectedUsage,
  type TenantCostRates,
  type TenantCostReport,
  type TenantCostService,
  type TenantCostUsage,
} from '../utils/tenantCosts'

const DEFAULT_RATES: TenantCostRates = {
  claudeInputPerMtokEur: 2.75,
  claudeOutputPerMtokEur: 13.75,
  n8nPerExecutionEur: 0.002,
  dataTrafficPerGbEur: 0.09,
  gbPerArticle: 0.04,
  eurUsd: 0.92,
}

const DEFAULT_SERVICES: TenantCostService[] = [
  { id: 'hostingSharedEur', label: 'Hosting (gedeeld)', defaultEur: 5, description: 'Server/FTP per tenant' },
  { id: 'yoastPremiumEur', label: 'Yoast SEO Premium', defaultEur: 8.25, description: 'WordPress ~€99/jaar' },
  { id: 'mailTransactionalEur', label: 'Transactionele mail', defaultEur: 0.5, description: 'Human review-mails' },
  { id: 'linkedinApiEur', label: 'LinkedIn API', defaultEur: 0, description: 'Meestal €0' },
  { id: 'wordpressMaintenanceEur', label: 'WordPress onderhoud', defaultEur: 0, description: 'Updates/monitoring' },
]

const RATE_FIELDS: { key: keyof TenantCostRates; labelLines: string[] }[] = [
  { key: 'claudeInputPerMtokEur', labelLines: ['Claude input', '€/M tokens'] },
  { key: 'claudeOutputPerMtokEur', labelLines: ['Claude output', '€/M tokens'] },
  { key: 'n8nPerExecutionEur', labelLines: ['n8n', '€/execution'] },
  { key: 'dataTrafficPerGbEur', labelLines: ['Data', '€/GB'] },
]

const RATES_STORAGE_PREFIX = 'ce-tenant-cost-rates'

function mergeRates(base: TenantCostRates, partial?: Partial<TenantCostRates> | null): TenantCostRates {
  if (!partial) return { ...base }
  return { ...base, ...partial }
}

function loadStoredRates(clientId: string): TenantCostRates | null {
  try {
    const raw = localStorage.getItem(`${RATES_STORAGE_PREFIX}:${clientId}`)
    if (!raw) return null
    return mergeRates(DEFAULT_RATES, JSON.parse(raw) as Partial<TenantCostRates>)
  } catch {
    return null
  }
}

function storeRates(clientId: string, rates: TenantCostRates): void {
  const payload = Object.fromEntries(RATE_FIELDS.map(({ key }) => [key, rates[key]]))
  localStorage.setItem(`${RATES_STORAGE_PREFIX}:${clientId}`, JSON.stringify(payload))
}

function ratesEqual(a: TenantCostRates, b: TenantCostRates): boolean {
  return RATE_FIELDS.every(({ key }) => a[key] === b[key])
}

type RateMetricFieldProps = {
  labelLines: string[]
  value: string
  onChange: (value: string) => void
  id?: string
}

/** Label links (2 regels) + smalle pink pill — zelfde patroon als Targets planning */
function RateMetricField({ labelLines, value, onChange, id }: RateMetricFieldProps) {
  const ariaLabel = labelLines.join(' ')

  return (
    <div className="flex min-w-0 items-center gap-[10px]">
      <span
        className="w-[76px] shrink-0 text-[14px] leading-[18px] font-semibold text-ce-black"
        aria-hidden
      >
        {labelLines.map((line) => (
          <span key={line} className="block">
            {line}
          </span>
        ))}
      </span>
      <input
        id={id}
        type="text"
        inputMode="decimal"
        value={value}
        onChange={(e) => onChange(e.target.value.replace(',', '.'))}
        aria-label={ariaLabel}
        className="h-[40px] w-[72px] shrink-0 rounded-[20px] border-0 bg-ce-pink px-[10px] text-center text-[14px] leading-[14px] text-ce-black outline-none focus:ring-[1.5px] focus:ring-ce-blue"
      />
    </div>
  )
}

function mockReport(postsPerWeek: number): TenantCostReport {
  const usage = projectedUsage(postsPerWeek)
  const enabled = { hostingSharedEur: 5, mailTransactionalEur: 0.5 }
  const labels = Object.fromEntries(DEFAULT_SERVICES.map((s) => [s.id, s.label]))
  const calculation = calculateTenantCosts(usage, DEFAULT_RATES, enabled, false, labels)

  return {
    clientId: 'mock',
    company: 'Demo tenant',
    month: currentMonthValue(),
    mode: 'projected',
    postsPerWeek,
    usage: { actual: usage, effective: usage, projected: usage },
    rates: DEFAULT_RATES,
    services: DEFAULT_SERVICES,
    calculation,
  }
}

function CostLineRow({
  label,
  eur,
  detail,
  source,
}: {
  label: string
  eur: number
  detail?: string
  source?: string
}) {
  return (
    <div className="flex items-baseline justify-between border-b border-ce-beige py-[10px] text-[14px] leading-[18px]">
      <div className="min-w-0 pr-[16px]">
        <p className="m-0 font-semibold text-ce-black">{label}</p>
        {detail && <p className="m-0 pt-[2px] text-ce-beige">{detail}</p>}
      </div>
      <div className="shrink-0 text-right">
        <p className="m-0 font-semibold text-ce-black">{formatEur(eur)}</p>
        {source && (
          <p className="m-0 pt-[2px] text-[12px] text-ce-beige">
            {source === 'actual' ? 'werkelijk' : 'schatting'}
          </p>
        )}
      </div>
    </div>
  )
}

/** Kosten per tenant — verbruik & rekentool */
export function TenantCostsPage() {
  const [report, setReport] = useState<TenantCostReport | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [usingMock, setUsingMock] = useState(false)

  const [month, setMonth] = useState(currentMonthValue())
  const [postsPerWeek, setPostsPerWeek] = useState(2)
  const [forceProjected, setForceProjected] = useState(false)
  const [includeAiReview, setIncludeAiReview] = useState(false)
  const [savedRates, setSavedRates] = useState<TenantCostRates>(DEFAULT_RATES)
  const [rates, setRates] = useState<TenantCostRates>(DEFAULT_RATES)
  const {
    message: ratesSaveMessage,
    kind: ratesSaveKind,
    fading: ratesSaveFading,
    showSuccess: showRatesSaveSuccess,
    clear: clearRatesSaveNotice,
  } = useSaveNotice('Tarieven opgeslagen')
  const ratesClientRef = useRef<string | null>(null)
  const [enabledServices, setEnabledServices] = useState<Record<string, boolean>>({
    hostingSharedEur: true,
    yoastPremiumEur: false,
    mailTransactionalEur: true,
    linkedinApiEur: false,
    wordpressMaintenanceEur: false,
  })

  useEffect(() => {
    if (!isApiConfigured()) {
      setReport(mockReport(postsPerWeek))
      setUsingMock(true)
      setLoading(false)
      if (ratesClientRef.current !== 'mock') {
        ratesClientRef.current = 'mock'
        const initial = loadStoredRates('mock') ?? DEFAULT_RATES
        setSavedRates(initial)
        setRates(initial)
      }
      return
    }

    setLoading(true)
    void fetchTenantCosts({ month, postsPerWeek, projected: forceProjected })
      .then((data) => {
        setReport(data)
        setPostsPerWeek(data.postsPerWeek)
        setUsingMock(false)
        setError(null)

        if (ratesClientRef.current !== data.clientId) {
          ratesClientRef.current = data.clientId
          const initial = loadStoredRates(data.clientId) ?? mergeRates(DEFAULT_RATES, data.rates)
          setSavedRates(initial)
          setRates(initial)
        }
      })
      .catch((err) => {
        setError(err instanceof Error ? err.message : 'Laden mislukt')
        setReport(mockReport(postsPerWeek))
        setUsingMock(true)
        if (ratesClientRef.current !== 'mock') {
          ratesClientRef.current = 'mock'
          const initial = loadStoredRates('mock') ?? DEFAULT_RATES
          setSavedRates(initial)
          setRates(initial)
        }
      })
      .finally(() => setLoading(false))
  }, [month, postsPerWeek, forceProjected])

  const serviceCatalog = report?.services ?? DEFAULT_SERVICES
  const serviceLabels = useMemo(
    () => Object.fromEntries(serviceCatalog.map((s) => [s.id, s.label])),
    [serviceCatalog],
  )

  const effectiveUsage: TenantCostUsage = useMemo(() => {
    if (!report) return projectedUsage(postsPerWeek)
    if (forceProjected || report.mode === 'projected') {
      return { ...projectedUsage(postsPerWeek), apifyCostEur: report.usage.actual.apifyCostEur, apifyUnits: report.usage.actual.apifyUnits, scraperRuns: report.usage.actual.scraperRuns }
    }
    return report.usage.effective
  }, [report, postsPerWeek, forceProjected])

  const enabledAmounts = useMemo(() => {
    const amounts: Record<string, number> = {}
    for (const service of serviceCatalog) {
      if (enabledServices[service.id]) {
        amounts[service.id] = service.defaultEur
      }
    }
    return amounts
  }, [serviceCatalog, enabledServices])

  const calculation = useMemo(
    () => calculateTenantCosts(effectiveUsage, rates, enabledAmounts, includeAiReview, serviceLabels),
    [effectiveUsage, rates, enabledAmounts, includeAiReview, serviceLabels],
  )

  const ratesDirty = useMemo(() => !ratesEqual(rates, savedRates), [rates, savedRates])

  const articlesPerMonth = effectiveUsage.articlesWritten

  function saveRates() {
    const clientId = report?.clientId ?? 'mock'
    storeRates(clientId, rates)
    setSavedRates({ ...rates })
    showRatesSaveSuccess()
  }

  function undoRates() {
    setRates({ ...savedRates })
    clearRatesSaveNotice()
  }

  function updateRate(key: keyof TenantCostRates, raw: string) {
    clearRatesSaveNotice()
    setRates((prev) => ({
      ...prev,
      [key]: Number.parseFloat(raw.replace(',', '.')) || 0,
    }))
  }

  return (
    <GridShell
      pageTitle="Kosten per tenant"
      pageSubtitle={report ? `${report.company} · ${report.month}` : undefined}
      activeNav="support"
      main={
        <div className={designStackClass} style={{ maxWidth: GRID2_REVIEW_MAIN_WIDTH }}>
          <p className="m-0 text-[14px] leading-[21px] text-ce-beige">
            <Link to="/support" className="text-ce-blue underline">
              ← Support
            </Link>
          </p>

          {error && <p className="text-[14px] text-ce-red">{error}</p>}
          {usingMock && (
            <p className="text-[14px] text-ce-beige">Mock-modus — geen live API/DB.</p>
          )}

          <section>
            <p className="m-0 text-[14px] font-semibold text-ce-black">Periode & ritme</p>
            <div className="mt-[12px] grid grid-cols-2 gap-[12px]">
              <FieldSingle
                id="cost-month"
                type="month"
                value={month}
                onChange={setMonth}
                placeholder="Maand"
                placeholderTone="black"
                aria-label="Maand"
              />
              <FieldSingle
                id="cost-posts-week"
                value={String(postsPerWeek)}
                onChange={(v) => setPostsPerWeek(Math.max(1, Math.min(20, Number.parseInt(v, 10) || 1)))}
                placeholder="Artikelen per week"
                placeholderTone="black"
                aria-label="Artikelen per week"
              />
            </div>
            <fieldset className="m-0 mt-[12px] flex flex-col gap-[6px] border-0 p-0">
              <CheckboxOption
                checked={forceProjected}
                onChange={setForceProjected}
                label="Projectie op basis van ritme (negeer werkelijke aantallen)"
              />
              <CheckboxOption
                checked={includeAiReview}
                onChange={setIncludeAiReview}
                label="Inclusief AI-review (CE-04) op elk artikel"
              />
            </fieldset>
          </section>

          {loading ? (
            <p className="text-[14px] text-ce-beige">Laden…</p>
          ) : (
            <>
              <section>
                <p className="m-0 text-[14px] font-semibold text-ce-black">Totaal deze maand</p>
                <p className="m-0 pt-[8px] text-[32px] font-semibold leading-[36px] text-ce-black">
                  {formatEur(calculation.totals.grandEur)}
                </p>
                <p className="m-0 pt-[6px] text-[14px] text-ce-black">
                  {articlesPerMonth} artikel{articlesPerMonth === 1 ? '' : 'en'}
                  {calculation.totals.perArticleEur != null &&
                    ` · ${formatEur(calculation.totals.perArticleEur)} per artikel`}
                </p>
                {report && report.mode === 'actual' && !forceProjected && (
                  <p className="m-0 pt-[6px] text-[14px] text-ce-beige">
                    Gebaseerd op werkelijk verbruik in {report.month}
                  </p>
                )}
              </section>

              <section>
                <p className="m-0 text-[14px] font-semibold text-ce-black">Kosten per dienst</p>
                {calculation.lines.map((line) => (
                  <CostLineRow
                    key={line.id}
                    label={line.label}
                    eur={line.eur}
                    detail={line.detail}
                    source={line.source}
                  />
                ))}
              </section>

              <section>
                <p className="m-0 text-[14px] font-semibold text-ce-black">Overige diensten</p>
                <fieldset className="m-0 mt-[10px] flex flex-col gap-[6px] border-0 p-0">
                  {serviceCatalog.map((service) => (
                    <CheckboxOption
                      key={service.id}
                      align="start"
                      checked={Boolean(enabledServices[service.id])}
                      onChange={(checked) =>
                        setEnabledServices((prev) => ({ ...prev, [service.id]: checked }))
                      }
                    >
                      <span className="font-semibold text-ce-black">{service.label}</span>
                      <span className="text-ce-beige"> — {formatEur(service.defaultEur)}/maand</span>
                      <span className="block text-ce-beige">{service.description}</span>
                    </CheckboxOption>
                  ))}
                </fieldset>
              </section>

              <section>
                <p className="m-0 text-[14px] font-semibold text-ce-black">Tarieven (aanpasbaar)</p>
                <div className="mt-[12px] grid grid-cols-2 gap-x-[12px] gap-y-[10px]">
                  {RATE_FIELDS.map(({ key, labelLines }) => (
                    <RateMetricField
                      key={key}
                      id={`rate-${key}`}
                      labelLines={labelLines}
                      value={String(rates[key])}
                      onChange={(v) => updateRate(key, v)}
                    />
                  ))}
                </div>
              </section>

              {report && (
                <section className="text-[14px] leading-[21px] text-ce-beige">
                  <p className="m-0 font-semibold text-ce-black">Werkelijk verbruik ({report.month})</p>
                  <p className="m-0 pt-[6px]">
                    Geschreven: {report.usage.actual.articlesWritten} · Gepubliceerd:{' '}
                    {report.usage.actual.articlesPublished} · Scraper-runs: {report.usage.actual.scraperRuns} ·
                    Apify: {formatEur(report.usage.actual.apifyCostEur)}
                  </p>
                </section>
              )}

              <div>
                <div className={pillActionsRowClass}>
                  <PillButton variant="main" onClick={saveRates} disabled={!ratesDirty}>
                    Save
                  </PillButton>
                  <PillButton variant="second" onClick={undoRates} disabled={!ratesDirty}>
                    Undo
                  </PillButton>
                </div>
                <SaveNotice
                  message={ratesSaveMessage}
                  kind={ratesSaveKind}
                  fading={ratesSaveFading}
                />
              </div>
            </>
          )}
        </div>
      }
    />
  )
}

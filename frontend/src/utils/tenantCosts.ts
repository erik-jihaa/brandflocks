/** Token- en workflow-schattingen — spiegelt web/api/tenant-cost-repository.php */

export type TenantCostRates = {
  claudeInputPerMtokEur: number
  claudeOutputPerMtokEur: number
  n8nPerExecutionEur: number
  dataTrafficPerGbEur: number
  gbPerArticle: number
  eurUsd: number
}

export type TenantCostUsage = {
  articlesWritten: number
  articlesPublished: number
  themesGenerated: number
  themesSubmitted: number
  researchRuns: number
  reportsGenerated: number
  scraperRuns: number
  apifyUnits: number
  apifyCostEur: number
  postsPerWeek: number
}

export type TenantCostService = {
  id: string
  label: string
  defaultEur: number
  description: string
}

export type TenantCostLine = {
  id: string
  label: string
  eur: number
  detail?: string
  source?: 'actual' | 'estimate'
}

export type TenantCostReport = {
  clientId: string
  company: string
  month: string
  mode: 'actual' | 'projected'
  postsPerWeek: number
  usage: {
    actual: TenantCostUsage
    effective: TenantCostUsage
    projected: TenantCostUsage
  }
  rates: TenantCostRates
  services: TenantCostService[]
  calculation: {
    workflowRuns: Record<string, number>
    tokens: { input: number; output: number }
    n8nExecutions: number
    dataTrafficGb: number
    lines: TenantCostLine[]
    totals: {
      claudeEur: number
      n8nEur: number
      dataTrafficEur: number
      apifyEur: number
      otherEur: number
      grandEur: number
      perArticleEur: number | null
    }
  }
}

const WORKFLOW_TOKENS: Record<string, { in: number; out: number; n8n: number }> = {
  ce01: { in: 1800, out: 800, n8n: 8 },
  ce02: { in: 2500, out: 1200, n8n: 12 },
  ce01b: { in: 900, out: 300, n8n: 4 },
  ce03: { in: 20000, out: 3500, n8n: 18 },
  ce04: { in: 3500, out: 600, n8n: 6 },
  ce07: { in: 1800, out: 800, n8n: 8 },
}

export function formatEur(value: number): string {
  return new Intl.NumberFormat('nl-NL', {
    style: 'currency',
    currency: 'EUR',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value)
}

export function currentMonthValue(): string {
  const now = new Date()
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}`
}

export function projectedUsage(postsPerWeek: number): TenantCostUsage {
  const articles = Math.max(0, Math.round(postsPerWeek * 4.33))
  return {
    articlesWritten: articles,
    articlesPublished: articles,
    themesGenerated: Math.max(articles, Math.round(postsPerWeek * 4)),
    themesSubmitted: articles,
    researchRuns: articles,
    reportsGenerated: 1,
    scraperRuns: articles,
    apifyUnits: 0,
    apifyCostEur: 0,
    postsPerWeek,
  }
}

export function calculateTenantCosts(
  usage: TenantCostUsage,
  rates: TenantCostRates,
  enabledServices: Record<string, number>,
  includeAiReview: boolean,
  serviceLabels: Record<string, string>,
): TenantCostReport['calculation'] {
  const articles = Math.max(0, usage.articlesWritten)
  const themesSubmitted = Math.max(articles, usage.themesSubmitted || articles)
  const researchRuns = Math.max(articles, usage.researchRuns || articles)
  const themesGenerated = Math.max(usage.themesGenerated, Math.round(usage.postsPerWeek * 4))
  const reports = Math.max(0, usage.reportsGenerated)

  const ce01Runs = Math.max(1, Math.round(themesGenerated / Math.max(1, usage.postsPerWeek)))
  const ce02Runs = researchRuns > 0 ? researchRuns : articles
  const ce01bRuns = themesSubmitted > 0 ? themesSubmitted : articles
  const ce03Runs = articles
  const ce04Runs = includeAiReview ? articles : 0
  const ce07Runs = reports > 0 ? reports : 0

  let claudeIn = 0
  let claudeOut = 0
  let n8nExecutions = 0

  for (const [runs, key] of [
    [ce01Runs, 'ce01'],
    [ce02Runs, 'ce02'],
    [ce01bRuns, 'ce01b'],
    [ce03Runs, 'ce03'],
    [ce04Runs, 'ce04'],
    [ce07Runs, 'ce07'],
  ] as const) {
    const spec = WORKFLOW_TOKENS[key]
    claudeIn += runs * spec.in
    claudeOut += runs * spec.out
    n8nExecutions += runs * spec.n8n
  }

  const claudeTotal =
    (claudeIn / 1_000_000) * rates.claudeInputPerMtokEur +
    (claudeOut / 1_000_000) * rates.claudeOutputPerMtokEur

  const n8nTotal = n8nExecutions * rates.n8nPerExecutionEur
  const gb = articles * rates.gbPerArticle
  const dataTrafficTotal = gb * rates.dataTrafficPerGbEur
  const apifyTotal = usage.apifyCostEur

  const otherLines: TenantCostLine[] = []
  let otherTotal = 0
  for (const [id, amount] of Object.entries(enabledServices)) {
    if (amount <= 0) continue
    otherLines.push({
      id,
      label: serviceLabels[id] ?? id,
      eur: amount,
      source: 'estimate',
    })
    otherTotal += amount
  }

  const lines: TenantCostLine[] = [
    {
      id: 'claude',
      label: 'Claude (Anthropic)',
      eur: claudeTotal,
      detail: `${claudeIn.toLocaleString('nl-NL')} in / ${claudeOut.toLocaleString('nl-NL')} out tokens`,
      source: 'estimate',
    },
    {
      id: 'n8n',
      label: 'n8n (executions)',
      eur: n8nTotal,
      detail: `${n8nExecutions.toLocaleString('nl-NL')} executions`,
      source: 'estimate',
    },
    {
      id: 'data',
      label: 'Dataverkeer (hosting/CDN/API)',
      eur: dataTrafficTotal,
      detail: `${gb.toFixed(2)} GB geschat`,
      source: 'estimate',
    },
    {
      id: 'apify',
      label: 'Apify (scraper)',
      eur: apifyTotal,
      detail: `${usage.scraperRuns} runs${usage.apifyUnits > 0 ? ` · ${usage.apifyUnits.toFixed(2)} CU` : ''}`,
      source: usage.scraperRuns > 0 ? 'actual' : 'estimate',
    },
    ...otherLines,
  ]

  const grandEur = claudeTotal + n8nTotal + dataTrafficTotal + apifyTotal + otherTotal

  return {
    workflowRuns: {
      ce01: ce01Runs,
      ce02: ce02Runs,
      ce01b: ce01bRuns,
      ce03: ce03Runs,
      ce04: ce04Runs,
      ce07: ce07Runs,
    },
    tokens: { input: claudeIn, output: claudeOut },
    n8nExecutions,
    dataTrafficGb: gb,
    lines,
    totals: {
      claudeEur: claudeTotal,
      n8nEur: n8nTotal,
      dataTrafficEur: dataTrafficTotal,
      apifyEur: apifyTotal,
      otherEur: otherTotal,
      grandEur,
      perArticleEur: articles > 0 ? grandEur / articles : null,
    },
  }
}

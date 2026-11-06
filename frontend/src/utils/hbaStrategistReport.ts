/** Plain-text merkarchitectuur-rapport voor HBA-strateeg mailto (Hero Brands meelezen). */

export type HbaStrategistReportInput = {
  companyName: string
  clientId?: string | null
  identityIntro?: string
  brancheLabel: string
  contextprofielLabel: string
  doel: string
  distinctiviteit: string
  watBewustNiet: string
  regels: string[]
  archetype: string
  foe: string
  toneOfBehaviour: string
  goedeVoorbeelden: readonly string[]
  zwakkeVoorbeelden: readonly string[]
  woordenDiePassen?: readonly string[]
  woordenDieNietPassen?: readonly string[]
}

const MAILTO_BODY_MAX = 7500

function section(title: string, lines: string[]): string[] {
  const body = lines.map((line) => line.trim()).filter(Boolean)
  if (body.length === 0) return []
  return [title, ...body.map((line) => (line.startsWith('-') || line.startsWith('•') ? line : `- ${line}`)), '']
}

function numberedLines(items: readonly string[], prefix: string): string[] {
  return items
    .map((item) => item.trim())
    .filter(Boolean)
    .map((item, index) => `${prefix} ${index + 1}: ${item}`)
}

/** Bouwt het volledige meeleesrapport in platte tekst. */
export function buildHbaStrategistReport(input: HbaStrategistReportInput): string {
  const company = input.companyName.trim() || 'Onbekende klant'
  const header = [
    'HBA MERKARCHITECTUUR — MEELLEESRAPPORT',
    '======================================',
    '',
    `Klant: ${company}`,
  ]
  if (input.clientId?.trim()) {
    header.push(`Tenant-ID: ${input.clientId.trim()}`)
  }
  header.push('')

  const blocks: string[] = [
    ...header,
    ...section('Markt & context', [
      input.identityIntro?.trim() ? `Wie jullie zijn: ${input.identityIntro.trim()}` : '',
      input.brancheLabel.trim() ? `Branche: ${input.brancheLabel.trim()}` : '',
      input.contextprofielLabel.trim() ? `Contextprofiel: ${input.contextprofielLabel.trim()}` : '',
    ]),
    ...section('Wat jullie willen bereiken', [input.doel]),
    ...section('Wat maakt jullie anders', [input.distinctiviteit]),
    ...section('Wat jullie bewust niet doen', [input.watBewustNiet]),
    ...section('Jullie regels (geloofwaardigheidstest)', input.regels),
    ...section('Positionering', [
      input.archetype.trim() ? `Archetype: ${input.archetype.trim()}` : '',
      input.foe.trim() ? `Tegenstander: ${input.foe.trim()}` : '',
      input.toneOfBehaviour.trim() ? `Tone of behaviour: ${input.toneOfBehaviour.trim()}` : '',
    ]),
    ...section('Kalibratie — sterke voorbeelden', numberedLines(input.goedeVoorbeelden, 'Sterk')),
    ...section('Kalibratie — zwakke voorbeelden', numberedLines(input.zwakkeVoorbeelden, 'Zwak')),
  ]

  const passend = (input.woordenDiePassen ?? []).map((s) => s.trim()).filter(Boolean)
  const nietPassend = (input.woordenDieNietPassen ?? []).map((s) => s.trim()).filter(Boolean)
  if (passend.length > 0) {
    blocks.push(...section('Woorden die passen', passend))
  }
  if (nietPassend.length > 0) {
    blocks.push(...section('Woorden die niet passen', nietPassend))
  }

  blocks.push(
    '---',
    'Verzoek: wil je hier even naar laten kijken voordat we live gaan?',
    'Dit rapport is automatisch gegenereerd vanuit de Merkarchitectuur.',
  )

  let report = blocks.join('\r\n').replace(/\r\n(\r\n)+/g, '\r\n\r\n').trim()

  if (report.length > MAILTO_BODY_MAX) {
    report =
      report.slice(0, MAILTO_BODY_MAX - 80).trimEnd() +
      '\r\n\r\n[… rapport ingekort vanwege e-maillimiet — volledige architectuur staat in Merkarchitectuur.]'
  }

  return report
}

export function buildHbaStrategistMailto(input: HbaStrategistReportInput): string {
  const company = input.companyName.trim() || 'tenant'
  const subject = encodeURIComponent(`Merkstrateeg gevraagd — ${company}`)
  const body = encodeURIComponent(buildHbaStrategistReport(input))
  return `mailto:support@igisnext.com?subject=${subject}&body=${body}`
}

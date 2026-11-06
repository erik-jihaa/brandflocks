import { STORY_TRIANGLE_MAX } from '../../utils/reportFindings'

type Props = {
  title: string
  labels: string[]
  values: Record<string, number>
  fill?: string
  size?: number
  /** Absolute schaal (bijv. 10 voor Story Triangle-cijfers) in plaats van aandeel van het totaal. */
  max?: number
  /** Zonder eigen kaart, voor gebruik binnen een bevindingsblok. */
  bare?: boolean
}

function normalize(values: Record<string, number>, labels: string[], max?: number): number[] {
  const raw = labels.map((label) => Math.max(0, values[label] ?? 0))
  if (max != null && max > 0) {
    return raw.map((v) => Math.min(100, (v / max) * 100))
  }
  const total = raw.reduce((a, b) => a + b, 0)
  if (total <= 0) return labels.map(() => 0)
  return raw.map((v) => (v / total) * 100)
}

/** Radardiagram voor Angle/Tone — §5.8bis.1 */
export function ReportRadarChart({
  title,
  labels,
  values,
  fill = '#52ffa8',
  size = 200,
  max,
  bare = false,
}: Props) {
  const pct = normalize(values, labels, max)
  const rawValues = labels.map((label) => Math.max(0, values[label] ?? 0))
  const cx = size / 2
  const cy = size / 2
  const radius = size * 0.34
  const rings = [25, 50, 75, 100]

  const angleAt = (index: number) => -Math.PI / 2 + (index * 2 * Math.PI) / labels.length

  const pointAt = (index: number, scale: number) => {
    const angle = angleAt(index)
    const r = (scale / 100) * radius
    return { x: cx + Math.cos(angle) * r, y: cy + Math.sin(angle) * r }
  }

  const polygon = pct
    .map((value, index) => {
      const p = pointAt(index, value)
      return `${p.x},${p.y}`
    })
    .join(' ')

  const chart = (
    <>
      <svg width={size} height={size} role="img" aria-label={`${title} radardiagram`}>
        {rings.map((ring) => (
          <polygon
            key={ring}
            points={labels
              .map((_, index) => {
                const p = pointAt(index, ring)
                return `${p.x},${p.y}`
              })
              .join(' ')}
            fill="none"
            stroke="#ada695"
            strokeWidth={0.8}
            opacity={0.5}
          />
        ))}
        {labels.map((label, index) => {
          const outer = pointAt(index, 100)
          const inner = pointAt(index, pct[index] ?? 0)
          return (
            <g key={label}>
              <line x1={cx} y1={cy} x2={outer.x} y2={outer.y} stroke="#ada695" strokeWidth={0.8} />
              <text
                x={outer.x + (outer.x - cx) * 0.18}
                y={outer.y + (outer.y - cy) * 0.18}
                textAnchor="middle"
                className="fill-ce-black text-[9px]"
              >
                {label}
              </text>
              <text
                x={inner.x}
                y={inner.y}
                textAnchor="middle"
                className="fill-ce-blue text-[8px] font-semibold"
              >
                {max != null
                  ? (rawValues[index] ?? 0).toLocaleString('nl-NL', { maximumFractionDigits: 1 })
                  : `${Math.round(pct[index] ?? 0)}%`}
              </text>
            </g>
          )
        })}
        <polygon points={polygon} fill={fill} fillOpacity={0.35} stroke="#033dff" strokeWidth={1.5} />
      </svg>
    </>
  )

  if (bare) {
    return <div className="min-w-0">{chart}</div>
  }

  return (
    <div className="min-w-0">
      <p className="m-0 pb-[8px] text-[14px] font-semibold leading-[18px] text-ce-black">{title}</p>
      {chart}
    </div>
  )
}

/** Story Triangle op eigen schaal (0–5), niet als aandeel — notitie 15 §2.2. */
export function ReportStoryTriangleRadar({
  metrics,
  size = 180,
}: {
  metrics: Array<{ label: string; avg: number }>
  size?: number
}) {
  const labels = metrics.map((metric) => metric.label)
  const values: Record<string, number> = {}
  for (const metric of metrics) {
    values[metric.label] = metric.avg
  }

  return (
    <ReportRadarChart
      title="Hoe je merkverhaal terugkomt"
      labels={labels}
      values={values}
      max={STORY_TRIANGLE_MAX}
      size={size}
      fill="#52ffa8"
      bare
    />
  )
}

const DEFAULT_TONE_LABELS = ['Consument', 'Zakelijk', 'Persoonlijk', 'Intern', 'Partners']

export function ReportContentrolRadar({ values }: { values?: Record<string, number> | null }) {
  const labels = [
    'Positionering',
    'Bewijs',
    'Kennis delen',
    'Oproep tot actie',
    'Community',
    'Service',
    'Team en cultuur',
    'Reactie op nieuws',
  ]
  const keyMap: Record<string, string> = {
    positionering: 'Positionering',
    bewijs: 'Bewijs',
    educatie: 'Kennis delen',
    activatie: 'Oproep tot actie',
    community: 'Community',
    service: 'Service',
    employer_culture: 'Team en cultuur',
    reactief: 'Reactie op nieuws',
  }
  const mapped: Record<string, number> = {}
  if (values) {
    for (const [key, val] of Object.entries(values)) {
      const normalized = key.toLowerCase()
      const label = keyMap[normalized] ?? key
      mapped[label] = val
    }
  }

  return <ReportRadarChart title="Contentrol" labels={labels} values={mapped} fill="#89adaa" />
}

export function ReportToneRadar({ values }: { values?: Record<string, number> | null }) {
  const mapped: Record<string, number> = {}
  if (values) {
    for (const [key, val] of Object.entries(values)) {
      const normalized = key.toLowerCase()
      if (normalized.includes('btoc') || normalized === 'b2c') mapped.Consument = val
      else if (normalized.includes('btob') || normalized === 'b2b') mapped.Zakelijk = val
      else if (normalized.includes('personal')) mapped.Persoonlijk = val
      else if (normalized.includes('internal')) mapped.Intern = val
      else if (normalized.includes('partner')) mapped.Partners = val
      else mapped[key] = val
    }
  }

  return <ReportRadarChart title="Toon" labels={DEFAULT_TONE_LABELS} values={mapped} />
}

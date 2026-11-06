import { useEffect, useState } from 'react'
import {
  fetchLlmVisibility,
  type LlmVisibilityReport,
  type SearchQuestionKind,
} from '../../api/llmQuestions'
import { isApiConfigured } from '../../api/client'

const GEO_MODEL_LABELS: Record<string, string> = {
  chatgpt: 'ChatGPT',
  perplexity: 'Perplexity',
  claude: 'Claude',
  gemini: 'Gemini',
}

function modelLabel(kind: SearchQuestionKind, model: string): string {
  if (kind === 'seo') {
    return SEO_MODEL_LABELS[model] ?? model
  }
  return GEO_MODEL_LABELS[model] ?? model
}

const SECTION_TITLE: Record<SearchQuestionKind, string> = {
  geo: 'GEO-vindbaarheid (AI-antwoorden)',
  seo: 'SEO-vindbaarheid (web-zoek)',
}

const SEO_MODEL_LABELS: Record<string, string> = {
  google: 'Google',
  web: 'Web-zoek',
}

type ReportFindabilitySectionProps = {
  kind: SearchQuestionKind
  snapshot?: LlmVisibilityReport | null
}

/** Report — Findability meting + verbeteradvies */
export function ReportFindabilitySection({ kind, snapshot }: ReportFindabilitySectionProps) {
  const [data, setData] = useState<LlmVisibilityReport | null>(snapshot ?? null)
  const [loading, setLoading] = useState(snapshot == null)

  useEffect(() => {
    if (snapshot != null) {
      setData(snapshot)
      setLoading(false)
      return
    }
    if (!isApiConfigured()) {
      setLoading(false)
      return
    }
    setLoading(true)
    void fetchLlmVisibility(kind)
      .then(setData)
      .catch(() => setData(null))
      .finally(() => setLoading(false))
  }, [kind, snapshot])

  if (loading) {
    return <p className="m-0 text-[14px] leading-[18px] text-ce-black">Vindbaarheid laden…</p>
  }

  if (!data || !data.hasQuestions) {
    return (
      <div className="space-y-[8px]">
        <p className="m-0 text-[14px] font-semibold leading-[18px] text-ce-black">
          {SECTION_TITLE[kind]}
        </p>
        <p className="m-0 text-[14px] leading-[18px] text-ce-black">
          {kind === 'seo'
            ? 'Voeg SEO-vragen toe bij Vindbaarheid om je zichtbaarheid in Google te meten.'
            : 'Voeg GEO-vragen toe bij Vindbaarheid om je zichtbaarheid in AI-antwoorden te meten.'}
        </p>
        {(data?.advice ?? []).length > 0 && (
          <FindabilityAdvice advice={data?.advice ?? []} />
        )}
      </div>
    )
  }

  if (!data.latest) {
    return (
      <div className="space-y-[8px]">
        <p className="m-0 text-[14px] font-semibold leading-[18px] text-ce-black">
          {SECTION_TITLE[kind]}
        </p>
        <p className="m-0 text-[14px] leading-[18px] text-ce-black">
          Nog geen meting — start de check via admin of wacht op de maandelijkse run.
        </p>
        <FindabilityAdvice advice={data.advice ?? []} />
      </div>
    )
  }

  const { items, mentionedCount, totalCount } = data.latest
  const seoUsesWeb = kind === 'seo' && items.some((item) => item.models.some((m) => m.model === 'web'))
  const comparison = data.previous
    ? ` (vorige meting: ${data.previous.mentionedCount} van ${data.previous.totalCount})`
    : ''

  return (
    <div className="space-y-[16px]">
      <p className="m-0 text-[14px] font-semibold leading-[18px] text-ce-black">
        {SECTION_TITLE[kind]}
      </p>
      {seoUsesWeb && (
        <p className="m-0 text-[14px] leading-[18px] text-ce-black">
          Via Perplexity web-zoek (indicatie). Google Programmable Search staat geen nieuwe &ldquo;hele
          web&rdquo;-engines meer toe sinds jan 2026.
        </p>
      )}

      <div className="flex flex-col gap-[14px]">
        {items.map((item) => (
          <div key={item.questionId}>
            <p className="m-0 text-[14px] leading-[18px] text-ce-black">&ldquo;{item.question}&rdquo;</p>
            <div className="mt-[4px] flex flex-wrap gap-[16px]">
              {item.models.map((m) => (
                <span key={m.model} className="text-[14px] leading-[18px] text-ce-black">
                  {modelLabel(kind, m.model)}{' '}
                  <span className={m.mentioned ? 'text-ce-green-text' : 'text-ce-black/50'}>
                    {m.mentioned ? '✅' : '—'}
                  </span>
                </span>
              ))}
            </div>
          </div>
        ))}
      </div>

      <p className="m-0 text-[14px] leading-[18px] text-ce-black">
        Zichtbaar in {mentionedCount} van {totalCount} gecontroleerde{' '}
        {kind === 'seo' ? 'zoekresultaten' : 'antwoorden'} deze meting
        {comparison}
      </p>

      <FindabilityAdvice advice={data.advice ?? []} />
    </div>
  )
}

/** @deprecated gebruik ReportFindabilitySection */
export const ReportLlmVisibilitySection = ReportFindabilitySection

function FindabilityAdvice({ advice }: { advice: string[] }) {
  if (advice.length === 0) return null

  return (
    <div className="space-y-[8px] border-t border-ce-black/10 pt-[16px]">
      <p className="m-0 text-[14px] font-semibold leading-[18px] text-ce-black">
        Hoe verbeter je dit?
      </p>
      <ul className="m-0 list-disc space-y-[6px] pl-[20px] text-[14px] leading-[21px] text-ce-black">
        {advice.map((tip) => (
          <li key={tip}>{tip}</li>
        ))}
      </ul>
    </div>
  )
}

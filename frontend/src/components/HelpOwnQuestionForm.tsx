import { useState } from 'react'
import { PillButton } from './PillButton'
import { isApiConfigured } from '../api/client'
import { escalateSupportChat } from '../api/supportChat'
import { DEFAULT_SUPPORT_EMAIL } from '../config/installContact'

const CONFIRM =
  'Verstuurd — we reageren zo snel mogelijk per mail.'

type HelpOwnQuestionFormProps = {
  supportEmail?: string
}

/** Los van de onboarding-assistent (klikmodel p-help). */
export function HelpOwnQuestionForm({
  supportEmail = DEFAULT_SUPPORT_EMAIL,
}: HelpOwnQuestionFormProps) {
  const [question, setQuestion] = useState('')
  const [sent, setSent] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function handleSubmit() {
    const trimmed = question.trim()
    if (!trimmed || sent) return

    if (!isApiConfigured()) {
      const subject = encodeURIComponent('Vraag via Help — Brandflocks')
      const body = encodeURIComponent(trimmed)
      window.location.href = `mailto:${supportEmail}?subject=${subject}&body=${body}`
      setQuestion('')
      setSent(true)
      return
    }

    setSubmitting(true)
    setError(null)
    try {
      await escalateSupportChat({
        messages: [{ role: 'user', content: trimmed }],
        draft: trimmed,
      })
      setQuestion('')
      setSent(true)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Versturen mislukt')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div>
      <p className="m-0 text-[14px] leading-[21px] text-ce-beige">
        Geen antwoord gevonden in de FAQ hierboven? Stel je vraag hieronder, dan nemen we &apos;m
        persoonlijk op.
      </p>
      <div className="mt-[12px] flex flex-wrap items-stretch gap-[10px] sm:flex-nowrap">
        <input
          type="text"
          value={question}
          disabled={submitting || sent}
          onChange={(e) => setQuestion(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === 'Enter') {
              e.preventDefault()
              void handleSubmit()
            }
          }}
          placeholder="Typ je vraag…"
          className="min-w-0 flex-1 border border-ce-beige bg-ce-offwhite px-[12px] py-[8px] text-[14px] leading-[18px] text-ce-black outline-none"
        />
        <PillButton
          variant="second"
          onClick={() => void handleSubmit()}
          disabled={submitting || sent || !question.trim()}
          loading={submitting}
        >
          Versturen
        </PillButton>
      </div>
      {error ? (
        <p className="m-0 mt-[10px] text-[14px] leading-[18px] text-ce-red">{error}</p>
      ) : null}
      {sent ? (
        <p className="m-0 mt-[10px] text-[14px] leading-[21px] text-ce-blue">{CONFIRM}</p>
      ) : null}
    </div>
  )
}
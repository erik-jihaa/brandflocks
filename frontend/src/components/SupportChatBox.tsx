import { useEffect, useRef, useState } from 'react'
import { PillButton } from './PillButton'
import { isApiConfigured } from '../api/client'
import { buildSupportAskDraft, getFieldHelp } from '../config/fieldHelp'
import {
  escalateSupportChat,
  fetchSupportChatBudget,
  sendSupportChatMessages,
  type SupportChatBudget,
  type SupportChatContext,
  type SupportChatMessage,
} from '../api/supportChat'
import { DEFAULT_SUPPORT_EMAIL } from '../config/installContact'

const MAX_INPUT_CHARS = 1500

type SupportChatBoxProps = {
  askKey?: string
  fromPath?: string
  supportEmail?: string
}

function formatEur(value: number): string {
  return `€${value.toFixed(2).replace('.', ',')}`
}

/** Support — onboarding-chatbot (API-keys, sources); spend-cap per tenant */
export function SupportChatBox({
  askKey,
  fromPath,
  supportEmail = DEFAULT_SUPPORT_EMAIL,
}: SupportChatBoxProps) {
  const [messages, setMessages] = useState<SupportChatMessage[]>([])
  const [input, setInput] = useState('')
  const [budget, setBudget] = useState<SupportChatBudget | null>(null)
  const [sending, setSending] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const listRef = useRef<HTMLDivElement>(null)
  const inputRef = useRef<HTMLInputElement>(null)
  const contextRef = useRef<SupportChatContext | null>(
    askKey && getFieldHelp(askKey) ? { fieldKey: askKey, screen: fromPath ?? '' } : null,
  )
  const escalationContextRef = useRef<SupportChatContext | null>(
    askKey && getFieldHelp(askKey) ? { fieldKey: askKey, screen: fromPath ?? '' } : null,
  )
  const prefilledRef = useRef(false)
  const [escalating, setEscalating] = useState(false)
  const [escalatedMessage, setEscalatedMessage] = useState<string | null>(null)

  const apiReady = isApiConfigured()
  const blocked = budget?.blocked === true

  useEffect(() => {
    if (!apiReady) return
    void fetchSupportChatBudget()
      .then((data) => setBudget(data.budget))
      .catch(() => {
        // Budget-status is informatief — chat blijft bruikbaar tot de server blokkeert
      })
  }, [apiReady])

  useEffect(() => {
    if (prefilledRef.current || !askKey) return
    const draft = buildSupportAskDraft(askKey, fromPath)
    if (!draft) return
    prefilledRef.current = true
    setInput(draft)
    window.requestAnimationFrame(() => {
      inputRef.current?.scrollIntoView({ behavior: 'smooth', block: 'center' })
      inputRef.current?.focus()
      inputRef.current?.select()
    })
  }, [askKey, fromPath])

  useEffect(() => {
    listRef.current?.scrollTo({ top: listRef.current.scrollHeight })
  }, [messages, sending])

  async function send() {
    const question = input.trim()
    if (question === '' || sending || blocked) return

    const nextMessages: SupportChatMessage[] = [...messages, { role: 'user', content: question }]
    const context = contextRef.current
    if (contextRef.current) {
      contextRef.current = null
    }

    setMessages(nextMessages)
    setInput('')
    setSending(true)
    setError(null)

    try {
      const result = await sendSupportChatMessages(nextMessages, context ?? undefined)
      setBudget(result.budget)
      if (result.reply) {
        setMessages([...nextMessages, { role: 'assistant', content: result.reply }])
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Versturen mislukt')
      void fetchSupportChatBudget()
        .then((data) => setBudget(data.budget))
        .catch(() => undefined)
    } finally {
      setSending(false)
    }
  }

  async function escalate() {
    if (escalating || escalatedMessage) return

    setEscalating(true)
    setError(null)

    try {
      const result = await escalateSupportChat({
        messages,
        context: escalationContextRef.current ?? undefined,
        draft: input,
      })
      setEscalatedMessage(result.message)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Doorsturen mislukt')
    } finally {
      setEscalating(false)
    }
  }

  if (!apiReady) {
    return (
      <p className="m-0 text-[14px] leading-[18px] text-ce-beige">
        Chatbot niet beschikbaar in mock-modus.
      </p>
    )
  }

  return (
    <div>
      <p className="m-0 text-[14px] leading-[21px] text-ce-beige">
        Vraag hulp bij onboarding: API-keys vinden (WordPress, Apify), koppelingen (Analytics,
        LinkedIn) en bronnen instellen. De assistent beantwoordt alleen vragen over deze app.
      </p>

      {messages.length > 0 && (
        <div
          ref={listRef}
          className="mt-[16px] flex max-h-[360px] flex-col gap-[12px] overflow-y-auto"
        >
          {messages.map((message, index) => (
            <div key={index}>
              <p
                className={`m-0 text-[12px] leading-[16px] font-semibold ${
                  message.role === 'user' ? 'text-ce-blue' : 'text-ce-beige'
                }`}
              >
                {message.role === 'user' ? 'Jij' : 'Assistent'}
              </p>
              <p className="m-0 pt-[2px] text-[14px] leading-[21px] whitespace-pre-wrap text-ce-black">
                {message.content}
              </p>
            </div>
          ))}
          {sending && (
            <p className="m-0 text-[14px] leading-[21px] text-ce-beige">Assistent typt…</p>
          )}
        </div>
      )}

      {blocked ? (
        <p className="m-0 mt-[16px] text-[14px] leading-[21px] text-ce-black">
          Het chatbudget voor deze tenant is bereikt ({formatEur(budget?.limitEur ?? 5)}). Mail{' '}
          <a href={`mailto:${supportEmail}`} className="text-ce-blue underline">
            {supportEmail}
          </a>{' '}
          voor verdere hulp.
        </p>
      ) : (
        <div className="mt-[16px] flex items-center gap-[12px]">
          <input
            ref={inputRef}
            type="text"
            value={input}
            maxLength={MAX_INPUT_CHARS}
            disabled={sending}
            onChange={(e) => setInput(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter') {
                e.preventDefault()
                void send()
              }
            }}
            placeholder="Bijv. Hoe koppel ik WordPress?"
            aria-label="Vraag aan de onboarding-assistent"
            className="h-[40px] min-w-0 flex-1 rounded-[20px] border-0 bg-ce-pink px-[20px] text-[14px] leading-[18px] text-ce-black outline-none placeholder:text-ce-beige focus:ring-[1.5px] focus:ring-ce-blue"
          />
          <PillButton variant="main" onClick={() => void send()} disabled={sending || input.trim() === ''} loading={sending}>
            Vraag
          </PillButton>
        </div>
      )}

      {error && <p className="m-0 mt-[8px] text-[14px] leading-[18px] text-ce-red">{error}</p>}

      {budget && !blocked && (
        <p className="m-0 mt-[8px] text-[12px] leading-[16px] text-ce-beige">
          Chatbudget: {formatEur(budget.usedEur)} van {formatEur(budget.limitEur)} gebruikt
        </p>
      )}

      {escalatedMessage ? (
        <p className="m-0 mt-[16px] text-[14px] leading-[21px] text-ce-black">{escalatedMessage}</p>
      ) : (
        <div className="mt-[16px]">
          <button
            type="button"
            disabled={escalating}
            onClick={() => void escalate()}
            className="cursor-pointer border-0 bg-transparent p-0 text-[14px] font-semibold leading-[18px] text-ce-green-text underline disabled:cursor-not-allowed disabled:opacity-60"
          >
            {escalating ? 'Doorsturen…' : 'Stuur mijn vraag door naar Erik'}
          </button>
        </div>
      )}
    </div>
  )
}

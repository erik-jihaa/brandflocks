import { useState } from 'react'
import { LIST_ROW, LIST_SECTION } from '../../layout/grid.constants'
import { PillButton } from '../PillButton'
import { ActionLink } from '../ui/ActionLink'
import { FieldMulti, FieldSingle } from '../ui/Field'
import { Pulldown } from '../ui/Pulldown'
import { SaveNotice } from '../ui/SaveNotice'
import { DeleteConfirmAction } from '../ui/DeleteConfirmAction'
import { useSaveNotice } from '../../hooks/useSaveNotice'
import {
  createPlatformServiceTicket,
  platformTicketStatusLabel,
  platformTicketSuggestion,
  redactPlatformServiceMessage,
  replyPlatformServiceTicket,
  ticketMessageText,
  updatePlatformServiceTicket,
  type PlatformServiceSummary,
  type PlatformServiceTicket,
} from '../../api/platform'

const TICKET_STATUS_OPTIONS = ['Open', 'In behandeling', 'Afgerond'] as const

function statusFromLabel(label: string): PlatformServiceTicket['status'] {
  switch (label) {
    case 'In behandeling':
      return 'in_progress'
    case 'Afgerond':
      return 'closed'
    default:
      return 'open'
  }
}

function labelFromStatus(status: PlatformServiceTicket['status']): string {
  return platformTicketStatusLabel(status)
}

function formatServiceDate(value: string): string {
  if (!value) return '—'
  return value.replace('T', ' ').slice(0, 16)
}

type PlatformServiceSectionProps = {
  clientId: string
  service: PlatformServiceSummary
  isAdmin: boolean
  onReload: () => void
}

export function PlatformServiceSection({
  clientId,
  service,
  isAdmin,
  onReload,
}: PlatformServiceSectionProps) {
  const { message, kind, fading, showSaving, showSuccess, showError } = useSaveNotice()
  const [subject, setSubject] = useState('')
  const [note, setNote] = useState('')
  const [busy, setBusy] = useState(false)
  const [replyTicketId, setReplyTicketId] = useState<string | null>(null)
  const [replyDrafts, setReplyDrafts] = useState<Record<string, string>>({})
  const [markClosedOnReply, setMarkClosedOnReply] = useState(false)

  async function handleCreateTicket() {
    if (!isAdmin || subject.trim() === '') return
    setBusy(true)
    showSaving('Ticket opslaan…')
    try {
      await createPlatformServiceTicket(clientId, {
        subject: subject.trim(),
        note: note.trim() || undefined,
      })
      setSubject('')
      setNote('')
      showSuccess('Ticket toegevoegd')
      onReload()
    } catch (err) {
      showError(err instanceof Error ? err.message : 'Ticket opslaan mislukt')
    } finally {
      setBusy(false)
    }
  }

  async function handleStatusChange(ticket: PlatformServiceTicket, label: string) {
    if (!isAdmin) return
    const status = statusFromLabel(label)
    if (status === ticket.status) return
    setBusy(true)
    showSaving('Status bijwerken…')
    try {
      await updatePlatformServiceTicket(clientId, ticket.id, { status })
      showSuccess('Status bijgewerkt')
      onReload()
    } catch (err) {
      showError(err instanceof Error ? err.message : 'Status bijwerken mislukt')
    } finally {
      setBusy(false)
    }
  }

  async function handleRedactMessage(messageId: number) {
    if (!isAdmin) return
    setBusy(true)
    showSaving('Vraag verwijderen…')
    try {
      await redactPlatformServiceMessage(clientId, messageId)
      showSuccess('Vraag verwijderd')
      onReload()
    } catch (err) {
      showError(err instanceof Error ? err.message : 'Verwijderen mislukt')
    } finally {
      setBusy(false)
    }
  }

  function replyRecipient(ticket: PlatformServiceTicket): string {
    return ticket.senderEmail?.trim() ?? ''
  }

  async function handleReply(ticket: PlatformServiceTicket) {
    if (!isAdmin) return
    const messageText = (replyDrafts[ticket.id] ?? '').trim()
    if (messageText === '') return

    setBusy(true)
    showSaving('Antwoord versturen…')
    try {
      const result = await replyPlatformServiceTicket(clientId, ticket.id, {
        message: messageText,
        markClosed: markClosedOnReply,
      })
      setReplyDrafts((prev) => {
        const next = { ...prev }
        delete next[ticket.id]
        return next
      })
      setReplyTicketId(null)
      setMarkClosedOnReply(false)
      showSuccess(`Antwoord verstuurd naar ${result.sentTo}`)
      onReload()
    } catch (err) {
      showError(err instanceof Error ? err.message : 'Antwoord versturen mislukt')
    } finally {
      setBusy(false)
    }
  }

  return (
    <section className={LIST_SECTION.bodyClass}>
      <h3 className={`m-0 ${LIST_SECTION.headerClass} text-ce-black`}>Service</h3>
      <p className="m-0 mt-[12px] text-[14px] leading-[18px] text-ce-beige">
        Support-chatvragen van de tenant (12 maanden) en interne tickets (mail/telefoon).
      </p>

      <div className="mt-[20px]">
        <h4 className="m-0 text-[14px] font-semibold leading-[18px] text-ce-black">Chatvragen</h4>
        {service.chatMessages.length === 0 ? (
          <p className="m-0 mt-[12px] text-[14px] leading-[18px] text-ce-beige">Nog geen vragen</p>
        ) : (
          <ul className={`m-0 mt-[12px] list-none p-0 ${LIST_SECTION.bodyStackClass}`}>
            {service.chatMessages.map((item) => (
              <li key={item.id} className={`${LIST_SECTION.itemClass} text-[14px] leading-[18px]`}>
                <div className={`${LIST_ROW.rowClass} text-ce-black`}>
                  <span className="font-semibold">{formatServiceDate(item.createdAt)}</span>
                  {isAdmin && (
                    <DeleteConfirmAction
                      onConfirm={() => void handleRedactMessage(item.id)}
                      disabled={busy}
                      busy={busy}
                      deleteLabel="Verwijder"
                      confirmLabel="Confirm delete"
                      busyLabel="…"
                    />
                  )}
                </div>
                <p className="m-0 mt-[8px] text-ce-black">{item.question}</p>
              </li>
            ))}
          </ul>
        )}
      </div>

      <div className="mt-[18px] border-t border-ce-black/10 pt-[18px]">
        <h4 className="m-0 text-[14px] font-semibold leading-[18px] text-ce-black">Interne tickets</h4>
        {service.tickets.length === 0 ? (
          <p className="m-0 mt-[12px] text-[14px] leading-[18px] text-ce-beige">Nog geen tickets</p>
        ) : (
          <ul className={`m-0 mt-[12px] list-none p-0 ${LIST_SECTION.bodyStackClass}`}>
            {service.tickets.map((ticket) => {
              const messageText = ticketMessageText(ticket.note, ticket.subject)
              const suggestion = platformTicketSuggestion(ticket)
              const email = ticket.senderEmail?.trim() || '—'

              return (
              <li key={ticket.id} className={`${LIST_SECTION.itemClass} text-[14px] leading-[18px]`}>
                <div className={`${LIST_ROW.rowClass} text-ce-beige`}>
                  <span>
                    {email} · {formatServiceDate(ticket.createdAt)}
                  </span>
                  {isAdmin ? (
                    <div className={`${LIST_ROW.actionsClass} min-w-[160px]`}>
                      <Pulldown
                        placeholder="Status"
                        options={[...TICKET_STATUS_OPTIONS]}
                        value={labelFromStatus(ticket.status)}
                        onChange={(label) => void handleStatusChange(ticket, label)}
                      />
                    </div>
                  ) : (
                    <span className={`${LIST_ROW.actionsClass} font-semibold text-ce-beige`}>
                      {labelFromStatus(ticket.status)}
                    </span>
                  )}
                </div>
                {messageText ? (
                  <p className="m-0 mt-[8px] whitespace-pre-wrap text-ce-black">{messageText}</p>
                ) : null}
                {suggestion ? (
                  <p className="m-0 mt-[8px] text-ce-blue">{suggestion}</p>
                ) : null}
                {isAdmin && (
                  <div className="mt-[10px]">
                    {replyTicketId === ticket.id ? (
                      <div className="flex flex-col items-start gap-[10px]">
                        <p className="m-0 text-[14px] leading-[18px] text-ce-beige">
                          Antwoord naar{' '}
                          <span className="font-semibold text-ce-black">
                            {replyRecipient(ticket) || 'contact e-mail tenant'}
                          </span>
                        </p>
                        <FieldMulti
                          placeholder="Typ je antwoord…"
                          aria-label="Antwoord"
                          value={replyDrafts[ticket.id] ?? ''}
                          onChange={(value) =>
                            setReplyDrafts((prev) => ({ ...prev, [ticket.id]: value }))
                          }
                          className="w-full min-w-[280px]"
                        />
                        <label className="flex cursor-pointer items-center gap-[8px] text-[14px] leading-[18px] text-ce-black">
                          <input
                            type="checkbox"
                            checked={markClosedOnReply}
                            onChange={(event) => setMarkClosedOnReply(event.target.checked)}
                            disabled={busy}
                          />
                          Markeer als afgerond na versturen
                        </label>
                        <div className="flex flex-wrap items-center gap-[12px]">
                          <PillButton
                            variant="main"
                            disabled={busy || (replyDrafts[ticket.id] ?? '').trim() === ''}
                            onClick={() => void handleReply(ticket)}
                          >
                            Verstuur antwoord
                          </PillButton>
                          <ActionLink
                            variant="statusRow"
                            disabled={busy}
                            onClick={() => {
                              setReplyTicketId(null)
                              setMarkClosedOnReply(false)
                            }}
                          >
                            Annuleren
                          </ActionLink>
                        </div>
                      </div>
                    ) : (
                      <ActionLink
                        variant="statusRow"
                        disabled={busy}
                        onClick={() => {
                          setReplyTicketId(ticket.id)
                          setMarkClosedOnReply(ticket.status === 'closed')
                        }}
                      >
                        Antwoorden per e-mail
                      </ActionLink>
                    )}
                  </div>
                )}
              </li>
              )
            })}
          </ul>
        )}

        {isAdmin && (
          <div className="mt-[20px] flex flex-col items-start gap-[10px]">
            <FieldSingle
              placeholder="Onderwerp"
              aria-label="Onderwerp"
              value={subject}
              onChange={setSubject}
              className="w-full min-w-[280px]"
            />
            <FieldMulti
              placeholder="Notitie (optioneel)"
              aria-label="Notitie"
              value={note}
              onChange={setNote}
              className="w-full min-w-[280px]"
            />
            <PillButton
              variant="main"
              disabled={busy || subject.trim() === ''}
              onClick={() => void handleCreateTicket()}
            >
              Ticket toevoegen
            </PillButton>
          </div>
        )}
      </div>

      <SaveNotice message={message} kind={kind} fading={fading} />
    </section>
  )
}

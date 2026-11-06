import { useCallback, useEffect, useState } from 'react'
import { borderedSectionStackClass } from '../design/identity.constants'
import {
  deleteMailboxNewsletter,
  fetchMailboxNewsletters,
  updateMailboxNewsletter,
} from '../api/sources'
import type { MailboxNewsletterItem } from '../types/sources'
import { FieldSingle, StrategySection } from './ui/Field'
import { PillButton } from './PillButton'
import { DeleteConfirmAction } from './ui/DeleteConfirmAction'

type MailboxNewsletterListProps = {
  sourceId: string
  refreshKey?: number
}

function formatReceivedAt(value: string | null): string {
  if (!value?.trim()) return ''
  const parsed = new Date(value)
  if (Number.isNaN(parsed.getTime())) return value
  return parsed.toLocaleDateString('nl-NL', { day: 'numeric', month: 'short', year: 'numeric' })
}

export function MailboxNewsletterList({ sourceId, refreshKey = 0 }: MailboxNewsletterListProps) {
  const [items, setItems] = useState<MailboxNewsletterItem[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [busyId, setBusyId] = useState<string | null>(null)
  const [drafts, setDrafts] = useState<Record<string, { subject: string; bodyText: string }>>({})

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const data = await fetchMailboxNewsletters(sourceId)
      const nextItems = data.items ?? []
      setItems(nextItems)
      setDrafts(
        Object.fromEntries(
          nextItems.map((item) => [
            item.id,
            { subject: item.subject, bodyText: item.bodyText ?? '' },
          ]),
        ),
      )
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Nieuwsbrieven laden mislukt')
      setItems([])
    } finally {
      setLoading(false)
    }
  }, [sourceId])

  useEffect(() => {
    void load()
  }, [load, refreshKey])

  async function saveItem(itemId: string) {
    const draft = drafts[itemId]
    if (!draft) return
    setBusyId(itemId)
    setError(null)
    try {
      const { item } = await updateMailboxNewsletter(sourceId, itemId, {
        subject: draft.subject,
        bodyText: draft.bodyText,
      })
      setItems((current) => current.map((row) => (row.id === itemId ? item : row)))
      setDrafts((current) => ({
        ...current,
        [itemId]: { subject: item.subject, bodyText: item.bodyText ?? '' },
      }))
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Opslaan mislukt')
    } finally {
      setBusyId(null)
    }
  }

  async function removeItem(itemId: string) {
    setBusyId(itemId)
    setError(null)
    try {
      await deleteMailboxNewsletter(sourceId, itemId)
      setItems((current) => current.filter((row) => row.id !== itemId))
      setDrafts((current) => {
        const next = { ...current }
        delete next[itemId]
        return next
      })
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Verwijderen mislukt')
    } finally {
      setBusyId(null)
    }
  }

  function itemDirty(item: MailboxNewsletterItem): boolean {
    const draft = drafts[item.id]
    if (!draft) return false
    return draft.subject !== item.subject || draft.bodyText !== (item.bodyText ?? '')
  }

  return (
    <StrategySection
      title="Gesynchroniseerde nieuwsbrieven"
      hint="Pas onderwerp of tekst aan, of verwijder een item uit de research-pool. Een nieuwe Check op de mailbox haalt opnieuw op uit IMAP en overschrijft de lijst."
      hintTone="black"
    >
      {loading ? (
        <p className="m-0 text-[14px] leading-[18px] text-ce-beige">Laden…</p>
      ) : error ? (
        <p className="m-0 text-[14px] leading-[18px] text-ce-red" role="alert">
          {error}
        </p>
      ) : items.length === 0 ? (
        <p className="m-0 text-[14px] leading-[18px] text-ce-beige">
          Nog geen nieuwsbrieven — voer Check uit na een geslaagde IMAP-koppeling.
        </p>
      ) : (
        <div className={borderedSectionStackClass}>
          {items.map((item) => {
            const draft = drafts[item.id] ?? { subject: item.subject, bodyText: item.bodyText ?? '' }
            const received = formatReceivedAt(item.receivedAt)
            return (
              <div key={item.id} className="grid gap-[12px] border-b border-ce-black/10 pb-[20px] last:border-0 last:pb-0">
                {received ? (
                  <p className="m-0 text-[12px] leading-[16px] text-ce-beige">{received}</p>
                ) : null}
                <FieldSingle
                  placeholder="Onderwerp"
                  value={draft.subject}
                  onChange={(subject) =>
                    setDrafts((current) => ({ ...current, [item.id]: { ...draft, subject } }))
                  }
                  aria-label="Onderwerp nieuwsbrief"
                />
                <textarea
                  value={draft.bodyText}
                  onChange={(e) =>
                    setDrafts((current) => ({
                      ...current,
                      [item.id]: { ...draft, bodyText: e.target.value },
                    }))
                  }
                  aria-label="Tekst nieuwsbrief"
                  className="min-h-[120px] w-full resize-y border-0 bg-transparent p-0 text-[14px] leading-[18px] text-ce-black outline-none placeholder:text-ce-beige"
                  placeholder="Tekst"
                />
                <div className="flex flex-wrap items-center gap-[12px]">
                  <PillButton
                    type="button"
                    variant="second"
                    disabled={busyId === item.id || !itemDirty(item)}
                    loading={busyId === item.id}
                    onClick={() => void saveItem(item.id)}
                  >
                    Opslaan
                  </PillButton>
                  <DeleteConfirmAction
                    onConfirm={() => void removeItem(item.id)}
                    disabled={busyId === item.id}
                    busy={busyId === item.id}
                    deleteLabel="Verwijderen"
                  />
                </div>
              </div>
            )
          })}
        </div>
      )}
    </StrategySection>
  )
}

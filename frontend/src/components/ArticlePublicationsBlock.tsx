import { useState } from 'react'
import { PillButton, pillActionsRowClass } from './PillButton'
import { hyperlinkClassBody } from '../design/identity.constants'
import type { PublicationDto } from '../types/api'

type ArticlePublicationsBlockProps = {
  publications: PublicationDto[]
  marking?: boolean
  onMarkPlaced?: (platform: string) => void
  /** Tekst om te kopiëren voor de handmatige adapter (headline + body + hashtags). */
  copyText?: string
}

function platformLabel(platform: string): string {
  const key = platform.toLowerCase()
  if (key === 'wordpress') return 'WordPress'
  if (key === 'joomla') return 'Joomla'
  if (key === 'linkedin') return 'LinkedIn'
  if (key === 'manual' || key === 'handmatig') return 'Handmatig'
  return platform
}

function statusClass(status: string): string {
  if (status === 'mislukt') return 'text-ce-orange'
  if (status === 'gepubliceerd') return 'text-ce-green-text'
  return 'text-ce-black'
}

/** Publicatiestatus per bestemming — onder het artikel, niet op het kalenderkaartje. */
export function ArticlePublicationsBlock({
  publications,
  marking = false,
  onMarkPlaced,
  copyText = '',
}: ArticlePublicationsBlockProps) {
  const [copied, setCopied] = useState(false)

  async function copyPublicationText() {
    const text = copyText.trim()
    if (text === '') return
    try {
      await navigator.clipboard.writeText(text)
      setCopied(true)
      window.setTimeout(() => setCopied(false), 2000)
    } catch {
      // ignore clipboard errors
    }
  }
  const rows = publications
  const hasManual = rows.some((row) => {
    const platform = row.platform.toLowerCase()
    return platform === 'manual' || platform === 'handmatig'
  })

  return (
    <div className="flex flex-col gap-[12px]">
      {rows.map((row, index) => {
        const key = row.id || `${row.platform}-${index}`
        const failed = row.status === 'mislukt'
        const placed = row.status === 'gepubliceerd'
        const url = row.externalUrl?.trim() ?? ''
        const platform = row.platform.toLowerCase()
        const isManual = platform === 'manual' || platform === 'handmatig' || platform === 'linkedin'

        return (
          <div key={key}>
            <p className="m-0 text-[14px] font-semibold leading-[18px] text-ce-black">
              {platformLabel(row.platform)}
              <span className={`font-semibold ${statusClass(row.status)}`}>
                {' '}
                _ {row.status}
              </span>
            </p>
            {failed && row.errorMessage ? (
              <p className="m-0 mt-[4px] text-[14px] leading-[18px] text-ce-orange">
                {row.errorMessage}
              </p>
            ) : null}
            {url ? (
              <a
                href={url}
                target="_blank"
                rel="noopener noreferrer"
                className={`mt-[4px] inline-block ${hyperlinkClassBody}`}
              >
                Open geplaatst bericht
              </a>
            ) : null}
            {isManual && !placed ? (
              <div className={pillActionsRowClass}>
                {copyText.trim() !== '' ? (
                  <PillButton variant="second" onClick={() => void copyPublicationText()}>
                    {copied ? 'Gekopieerd' : 'Kopieer tekst'}
                  </PillButton>
                ) : null}
                {onMarkPlaced ? (
                  <PillButton
                    variant="second"
                    onClick={() => onMarkPlaced(row.platform || 'manual')}
                    disabled={marking}
                    loading={marking}
                  >
                    Geplaatst
                  </PillButton>
                ) : null}
              </div>
            ) : null}
          </div>
        )
      })}

      {!hasManual && onMarkPlaced ? (
        <div>
          <p className="m-0 text-[14px] font-semibold leading-[18px] text-ce-black">
            Handmatig
            <span className="text-ce-beige"> _ nog niet geplaatst</span>
          </p>
          <div className={pillActionsRowClass}>
            {copyText.trim() !== '' ? (
              <PillButton variant="second" onClick={() => void copyPublicationText()}>
                {copied ? 'Gekopieerd' : 'Kopieer tekst'}
              </PillButton>
            ) : null}
            <PillButton
              variant="second"
              onClick={() => onMarkPlaced('manual')}
              disabled={marking}
              loading={marking}
            >
              Geplaatst
            </PillButton>
          </div>
        </div>
      ) : null}
    </div>
  )
}

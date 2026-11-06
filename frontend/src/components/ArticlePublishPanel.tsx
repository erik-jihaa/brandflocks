import { useState } from 'react'
import { PillButton, pillActionsRowClass } from './PillButton'
import { buildPublishCopy } from '../utils/publishCopy'
import { isPublishFailureNotice } from '../utils/publishNotice'
import { ActionNotice } from './ui/ActionNotice'
import type { SaveNoticeKind } from '../hooks/useSaveNotice'

type ArticlePublishPanelProps = {
  headline: string
  content: string
  hashtags: string
  platform: string
  publishing?: boolean
  publishingWp?: boolean
  publishNotice?: string | null
  publishNoticeKind?: SaveNoticeKind
  publishNoticeFading?: boolean
  /** Handmatig zonder tegenlezer — extra bevestiging vóór publiceren */
  confirmBeforePublish?: boolean
  onPublish: () => void
  onPublishWp?: () => void
}

/** CE-04.2 — Publiceer naar WordPress blog + copy voor social. */
export function ArticlePublishPanel({
  headline,
  content,
  hashtags,
  platform,
  publishing = false,
  publishingWp = false,
  publishNotice = null,
  publishNoticeKind = 'success',
  publishNoticeFading = false,
  confirmBeforePublish = false,
  onPublish,
  onPublishWp,
}: ArticlePublishPanelProps) {
  const [copied, setCopied] = useState(false)
  const [confirmOpen, setConfirmOpen] = useState(false)
  const copyText = buildPublishCopy(headline, content, hashtags)
  const isBlog = platform.toLowerCase() === 'blog'
  const publishHandler = onPublishWp ?? onPublish
  const busy = publishing || publishingWp
  const noticeIsError =
    publishNotice != null &&
    (publishNoticeKind === 'error' || isPublishFailureNotice(publishNotice))

  function handlePublishClick() {
    if (confirmBeforePublish && !confirmOpen) {
      setConfirmOpen(true)
      return
    }
    setConfirmOpen(false)
    publishHandler()
  }

  if (confirmOpen && confirmBeforePublish) {
    return (
      <div className="space-y-[12px]">
        <p className="m-0 text-[14px] leading-[18px] text-ce-black">
          Weet je zeker dat je wilt publiceren? Bij handmatig zonder tegenlezer gaat dit direct live
          naar je website en gekoppelde kanalen.
        </p>
        <div className={pillActionsRowClass}>
          <PillButton variant="main" onClick={handlePublishClick} disabled={busy} loading={busy}>
            Doorgaan met publiceren
          </PillButton>
          <PillButton variant="second" onClick={() => setConfirmOpen(false)} disabled={busy}>
            Annuleren
          </PillButton>
        </div>
      </div>
    )
  }

  async function handleCopy() {
    try {
      await navigator.clipboard.writeText(copyText)
      setCopied(true)
      window.setTimeout(() => setCopied(false), 2000)
    } catch {
      setCopied(false)
    }
  }

  return (
    <div className="space-y-[12px]">
      {publishNotice ? (
        <ActionNotice
          message={publishNotice}
          kind={noticeIsError ? 'error' : 'success'}
          fading={publishNoticeFading}
          className={noticeIsError ? 'font-semibold' : 'font-semibold !text-ce-black'}
        />
      ) : null}
      <div className={pillActionsRowClass}>
        <PillButton variant="main" onClick={handlePublishClick} disabled={busy} loading={busy}>
          Publiceer
        </PillButton>
        {!isBlog && (
          <PillButton variant="second" onClick={() => void handleCopy()}>
            {copied ? 'gekopieerd' : 'copy'}
          </PillButton>
        )}
      </div>
    </div>
  )
}

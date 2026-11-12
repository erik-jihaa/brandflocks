import { useState } from 'react'
import { ArticlePublishPanel } from './ArticlePublishPanel'
import { ArticlePublicationsBlock } from './ArticlePublicationsBlock'
import { PillButton, pillActionsRowClass } from './PillButton'
import { SaveNotice } from './ui/SaveNotice'
import { ActionNotice } from './ui/ActionNotice'
import { isPublishFailureNotice } from '../utils/publishNotice'
import type { PublishMode } from '../api/account'
import type { SaveNoticeKind } from '../hooks/useSaveNotice'
import type { PublicationDto, ReviewMode } from '../types/api'

type ArticleReviewPanelProps = {
  status: string
  headline: string
  content: string
  hashtags: string
  platform: string
  publishMode?: PublishMode
  reviewUrl?: string | null
  reviewerEmail?: string | null
  mailSent?: boolean
  mailWarning?: string | null
  saveMessage?: string | null
  saveNoticeKind?: SaveNoticeKind
  saveNoticeFading?: boolean
  publishNotice?: string | null
  publishNoticeKind?: SaveNoticeKind
  publishNoticeFading?: boolean
  onSubmit: (reviewMode: ReviewMode) => void
  onReedit?: () => void
  onSave?: () => void
  onPublish: () => void
  onPublishWp?: () => void
  submitting?: boolean
  reediting?: boolean
  reeditNotice?: string | null
  saving?: boolean
  publishing?: boolean
  publishingWp?: boolean
  wordpressPostId?: string | null
  publications?: PublicationDto[]
  markingPlaced?: boolean
  onMarkPlaced?: (platform: string) => void
  /** Tijdens CE-03 genereren — zelfde knop, niet klikbaar */
  reeditDisabled?: boolean
  /** Indienen alleen bij echte artikeltekst (niet bij themeOnly / lege stub). */
  hasArticleText?: boolean
}

const REEDIT_LABEL = 'Opnieuw bewerken'

function publicationCopyText(headline: string, content: string, hashtags: string): string {
  return [headline.trim(), content.trim(), hashtags.trim()].filter(Boolean).join('\n\n')
}

export function ArticleReviewPanel({
  status,
  headline,
  content,
  hashtags,
  platform,
  publishMode = 'manual_with_review',
  reviewUrl,
  reviewerEmail,
  mailSent,
  mailWarning,
  saveMessage,
  saveNoticeKind = 'success',
  saveNoticeFading = false,
  publishNotice,
  publishNoticeKind = 'success',
  publishNoticeFading = false,
  onSubmit,
  onReedit,
  onSave,
  onPublish,
  onPublishWp,
  submitting = false,
  reediting = false,
  reeditNotice = null,
  saving = false,
  publishing = false,
  publishingWp = false,
  wordpressPostId = null,
  publications = [],
  markingPlaced = false,
  onMarkPlaced,
  reeditDisabled = false,
  hasArticleText = true,
}: ArticleReviewPanelProps) {
  const [confirmPublishOpen, setConfirmPublishOpen] = useState(false)
  const busy = submitting || saving || publishing || reediting
  const pendingHuman = status === 'pending'
  const noReviewer = publishMode === 'manual_without_review'
  const primarySubmitLabel = noReviewer
      ? 'Publiceer'
      : 'Indienen ter goedkeuring'
  const showSubmit = hasArticleText

  function handleHumanSubmit() {
    if (noReviewer && !confirmPublishOpen) {
      setConfirmPublishOpen(true)
      return
    }
    setConfirmPublishOpen(false)
    onSubmit('human')
  }

  if (confirmPublishOpen && noReviewer) {
    return (
      <div className="space-y-[12px]">
        <p className="m-0 text-[14px] leading-[18px] text-ce-black">
          Weet je zeker dat je wilt publiceren? Er is geen tegenlezer — het artikel gaat direct naar
          je website en gekoppelde kanalen.
        </p>
        <div className={pillActionsRowClass}>
          <PillButton variant="main" onClick={handleHumanSubmit} disabled={busy} loading={submitting || publishing}>
            Doorgaan met publiceren
          </PillButton>
          <PillButton variant="second" onClick={() => setConfirmPublishOpen(false)} disabled={busy}>
            Annuleren
          </PillButton>
        </div>
      </div>
    )
  }

  async function copyReviewLink() {
    if (!reviewUrl) return
    try {
      await navigator.clipboard.writeText(reviewUrl)
    } catch {
      // ignore
    }
  }

  if (status === 'published' && wordpressPostId) {
    return (
      <div className="space-y-[10px]">
        <p className="m-0 text-[14px] leading-[18px] text-ce-black">
          Gepubliceerd op de website (WordPress #{wordpressPostId}) — archiveer later vanuit Archief.
        </p>
        {publishNotice ? (
          <ActionNotice
            message={publishNotice}
            kind={isPublishFailureNotice(publishNotice) ? 'error' : publishNoticeKind}
            fading={publishNoticeFading}
            className="font-semibold !text-ce-black"
          />
        ) : null}
        <ArticlePublishPanel
          headline={headline}
          content={content}
          hashtags={hashtags}
          platform={platform}
          publishing={publishing}
          publishingWp={publishingWp}
          publishNotice={null}
          confirmBeforePublish={noReviewer}
          onPublish={onPublish}
          onPublishWp={onPublishWp}
        />
        <p className="m-0 text-[13px] leading-[17px] text-ce-beige">
          Opnieuw op Publiceer werkt WordPress post #{wordpressPostId} bij (tekst, afbeelding,
          status). Handig na redactie of als de live URL niet klopt.
        </p>
        <ArticlePublicationsBlock
          publications={publications}
          marking={markingPlaced}
          onMarkPlaced={onMarkPlaced}
          copyText={publicationCopyText(headline, content, hashtags)}
        />
      </div>
    )
  }

  if (status === 'published' && !wordpressPostId) {
    return (
      <div className="space-y-[10px]">
        <p className="m-0 text-[14px] leading-[18px] text-ce-red">
          Status is gepubliceerd in CE, maar nog niet op je website. Controleer Integrations →
          WordPress (site-URL, gebruiker, app-wachtwoord) en klik opnieuw op Publiceer.
        </p>
        <ArticlePublishPanel
          headline={headline}
          content={content}
          hashtags={hashtags}
          platform={platform}
          publishing={publishing}
          publishingWp={publishingWp}
          publishNotice={publishNotice}
          publishNoticeKind={publishNoticeKind}
          publishNoticeFading={publishNoticeFading}
          confirmBeforePublish={noReviewer}
          onPublish={onPublish}
          onPublishWp={onPublishWp}
        />
        <ArticlePublicationsBlock
          publications={publications}
          marking={markingPlaced}
          onMarkPlaced={onMarkPlaced}
          copyText={publicationCopyText(headline, content, hashtags)}
        />
      </div>
    )
  }

  if (pendingHuman) {
    return (
      <div className="space-y-[12px]">
        <p className="m-0 text-[14px] leading-[18px] text-ce-black">
          Ingediend — wacht op hoofdredacteur
          {reviewerEmail ? ` (${reviewerEmail})` : ''}.
        </p>
        {mailSent === true && (
          <p className="m-0 text-[14px] leading-[18px] text-ce-green-text">Beoordelingsmail verstuurd.</p>
        )}
        {mailSent === false && mailWarning && (
          <p className="m-0 text-[14px] leading-[18px] text-ce-red">{mailWarning}</p>
        )}
        {reviewUrl && (
          <div className="rounded-[20px] bg-ce-pink px-[20px] py-[14px]">
            <p className="m-0 text-[14px] font-semibold text-ce-black">Beoordelingslink</p>
            <a
              href={reviewUrl}
              target="_blank"
              rel="noopener noreferrer"
              className="mt-[6px] block break-all text-[14px] text-ce-blue underline"
            >
              {reviewUrl}
            </a>
            <div className={pillActionsRowClass}>
              <PillButton variant="second" onClick={() => void copyReviewLink()}>
                Link kopiëren
              </PillButton>
              <PillButton
                variant="main"
                type="button"
                onClick={() => window.open(reviewUrl, '_blank', 'noopener,noreferrer')}
              >
                Beoordeling openen
              </PillButton>
            </div>
          </div>
        )}
      </div>
    )
  }

  if (status === 'feedback') {
    return (
      <div className="space-y-[12px]">
        <p className="m-0 text-[13px] leading-[17px] text-ce-beige">
          Opnieuw bewerken leest je aantekeningen in de copy en maakt daar een nieuwe versie van.
        </p>
        <div className={pillActionsRowClass}>
        {showSubmit ? (
          <PillButton variant="main" onClick={handleHumanSubmit} disabled={busy} loading={submitting || publishing}>
            {primarySubmitLabel}
          </PillButton>
        ) : null}
        {onReedit ? (
          <PillButton variant="second" onClick={onReedit} disabled={busy || reeditDisabled} loading={reediting}>
            {REEDIT_LABEL}
          </PillButton>
        ) : (
          <PillButton variant="second" onClick={() => onSubmit('ai')} disabled={busy} loading={submitting}>
            {REEDIT_LABEL}
          </PillButton>
        )}
        {onSave && (
          <PillButton variant="second" onClick={onSave} disabled={busy} loading={saving}>
            Opslaan
          </PillButton>
        )}
        </div>
        <SaveNotice
          message={saveMessage ?? null}
          kind={saveNoticeKind}
          fading={saveNoticeFading}
          className="mt-0"
        />
        {reeditNotice ? (
          <p className="m-0 text-[14px] leading-[18px] text-ce-beige">{reeditNotice}</p>
        ) : null}
      </div>
    )
  }

  if (status === 'approved') {
    return (
      <div className="space-y-[12px]">
        <ArticlePublishPanel
          headline={headline}
          content={content}
          hashtags={hashtags}
          platform={platform}
          publishing={publishing}
          publishingWp={publishingWp}
          publishNotice={publishNotice}
          publishNoticeKind={publishNoticeKind}
          publishNoticeFading={publishNoticeFading}
          confirmBeforePublish={noReviewer}
          onPublish={onPublish}
          onPublishWp={onPublishWp}
        />
        <ArticlePublicationsBlock
          publications={publications}
          marking={markingPlaced}
          onMarkPlaced={onMarkPlaced}
          copyText={publicationCopyText(headline, content, hashtags)}
        />
      </div>
    )
  }

  return (
    <div className="space-y-[12px]">
      <div className={pillActionsRowClass}>
        {showSubmit ? (
          <PillButton variant="main" onClick={handleHumanSubmit} disabled={busy} loading={submitting || publishing}>
            {primarySubmitLabel}
          </PillButton>
        ) : null}
        {onReedit ? (
          <PillButton variant="second" onClick={onReedit} disabled={busy || reeditDisabled} loading={reediting}>
            {REEDIT_LABEL}
          </PillButton>
        ) : showSubmit ? (
          <PillButton variant="second" onClick={() => onSubmit('ai')} disabled={busy} loading={submitting}>
            {REEDIT_LABEL}
          </PillButton>
        ) : null}
        {onSave && (
          <PillButton variant="second" onClick={onSave} disabled={busy} loading={saving}>
            Opslaan
          </PillButton>
        )}
      </div>
      <SaveNotice
        message={saveMessage ?? null}
        kind={saveNoticeKind}
        fading={saveNoticeFading}
        className="mt-0"
      />
      {reeditNotice ? (
        <p className="m-0 text-[14px] leading-[18px] text-ce-beige">{reeditNotice}</p>
      ) : null}
      {showSubmit ? (
        <p className="m-0 text-[13px] leading-[17px] text-ce-beige">
          {noReviewer
            ? 'Geen tegenlezer — Publiceer zet het artikel direct live (na goedkeuring in CE).'
            : 'Opnieuw bewerken verwerkt je aantekeningen in de copy tot een nieuwe versie.'}
        </p>
      ) : null}
    </div>
  )
}

import { useState } from 'react'
import { useLocation, useNavigate, useParams } from 'react-router-dom'
import { GridShell } from '../layout/GridShell'
import { PillButton, pillActionsRowClass } from '../components/PillButton'
import { statusRowActionClass } from '../components/ui/ActionLink'
import { submitArchiveFromAgenda, type ArchiveDraftPayload } from '../api/archive'
import { isApiConfigured } from '../api/client'

type ConfirmState = ArchiveDraftPayload & {
  slotId: string
  title: string
}

/** briefing/archive single post checkType+.png */
export function ArchivePostConfirmPage() {
  const navigate = useNavigate()
  const { slotId } = useParams<{ slotId: string }>()
  const location = useLocation()
  const state = location.state as ConfirmState | null

  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  if (!state || state.slotId !== slotId) {
    return (
      <GridShell
        pageTitle="Archive this post"
        activeNav="agenda"
        mainLayout="grid2"
        main={
          <p className="text-[14px] leading-[18px] text-ce-beige">
            No archive draft found.{' '}
            <button
              type="button"
              onClick={() => navigate('/agenda')}
              className={statusRowActionClass}
            >
              Back to Agenda
            </button>
          </p>
        }
      />
    )
  }

  async function handleConfirm() {
    if (!slotId) return
    setSubmitting(true)
    setError(null)

    try {
      if (isApiConfigured()) {
        await submitArchiveFromAgenda(slotId, {
          publishedAt: state!.publishedAt,
          promoBudget: state!.promoBudget,
          socialPostUrl: state!.socialPostUrl,
          archiveNote: state!.archiveNote,
        })
      }
      navigate('/archive')
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Archive failed')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <GridShell
      pageTitle="Archive this post"
      pageSubtitle={state.title}
      activeNav="agenda"
      mainLayout="grid2"
      main={
        <>
          {error && <p className="mb-[12px] text-[14px] leading-[18px] text-ce-red">{error}</p>}

          <p className="m-0 text-[14px] leading-[18px] text-ce-black">
            Are you sure you want to archive this post?{' '}
            {state!.publishedAt || state!.socialPostUrl || state!.archiveNote
              ? "You can't edit the article anymore."
              : 'This slot will move to Archive (no article written yet).'}
          </p>

          <div className={pillActionsRowClass}>
            <PillButton variant="second" onClick={() => void handleConfirm()} disabled={submitting} loading={submitting}>
              Yes Archive
            </PillButton>
            <PillButton variant="second" onClick={() => navigate('/agenda')} disabled={submitting}>
              Keep in agenda
            </PillButton>
          </div>

          <p className="mt-[16px] mb-0 text-[14px] leading-[18px] text-ce-beige">
            You can&apos;t edit the article anymore
          </p>
        </>
      }
    />
  )
}

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
        pageTitle="Deze post archiveren"
        activeNav="agenda"
        mainLayout="grid2"
        main={
          <p className="text-[14px] leading-[18px] text-ce-beige">
            Geen concept gevonden.{' '}
            <button
              type="button"
              onClick={() => navigate('/agenda')}
              className={statusRowActionClass}
            >
              Terug naar Kalender
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
      setError(err instanceof Error ? err.message : 'Archiveren mislukt')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <GridShell
      pageTitle="Deze post archiveren"
      pageSubtitle={state.title}
      activeNav="agenda"
      mainLayout="grid2"
      main={
        <>
          {error && <p className="mb-[12px] text-[14px] leading-[18px] text-ce-red">{error}</p>}

          <p className="m-0 text-[14px] leading-[18px] text-ce-black">
            Weet je zeker dat je deze post wilt archiveren?{' '}
            {state!.publishedAt || state!.socialPostUrl || state!.archiveNote
              ? 'Je kunt het artikel daarna niet meer bewerken.'
              : 'Dit slot gaat naar Alle artikelen (er is nog geen artikel geschreven).'}
          </p>

          <div className={pillActionsRowClass}>
            <PillButton variant="second" onClick={() => void handleConfirm()} disabled={submitting} loading={submitting}>
              Ja, archiveren
            </PillButton>
            <PillButton variant="second" onClick={() => navigate('/agenda')} disabled={submitting}>
              In kalender houden
            </PillButton>
          </div>

          <p className="mt-[16px] mb-0 text-[14px] leading-[18px] text-ce-beige">
            Je kunt het artikel daarna niet meer bewerken
          </p>
        </>
      }
    />
  )
}
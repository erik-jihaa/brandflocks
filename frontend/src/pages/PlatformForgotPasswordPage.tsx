import { type FormEvent, useState } from 'react'
import { Link } from 'react-router-dom'
import { GridShell } from '../layout/GridShell'
import { GRID2_REVIEW_MAIN_WIDTH } from '../layout/grid.constants'
import { FieldSingle } from '../components/ui/Field'
import { PillButton, pillActionsRowClass, pillButtonClass, designStackClass } from '../components/PillButton'
import { requestPlatformPasswordReset } from '../api/platform'
import { isApiConfigured } from '../api/client'

/** Platform — wachtwoord vergeten. */
export function PlatformForgotPasswordPage() {
  const [email, setEmail] = useState('')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [message, setMessage] = useState<string | null>(null)

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setLoading(true)
    setError(null)
    setMessage(null)

    if (!isApiConfigured()) {
      setError('API niet geconfigureerd')
      setLoading(false)
      return
    }

    try {
      const result = await requestPlatformPasswordReset(email)
      setMessage(result.message)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Verzoek mislukt')
    } finally {
      setLoading(false)
    }
  }

  return (
    <GridShell
      pageTitle="Forgot password"
      activeNav="logout"
      showNav={false}
      forceLightTheme
      logoVariant="platform"
      mainLayout="grid2"
      main={
        <form
          className="flex flex-col"
          style={{ maxWidth: GRID2_REVIEW_MAIN_WIDTH }}
          onSubmit={(e) => void onSubmit(e)}
        >
          <div className={designStackClass}>
          <p className="m-0 text-[14px] leading-[20px] text-ce-beige">
            Vul het e-mailadres in dat bij je platform-account hoort. Je ontvangt een link om een nieuw wachtwoord in
            te stellen.
          </p>
          {error && <p className="m-0 text-[14px] leading-[18px] text-ce-red">{error}</p>}
          {message && <p className="m-0 text-[14px] leading-[18px] text-ce-green-text">{message}</p>}
          <FieldSingle
            id="platform-forgot-email"
            type="email"
            placeholder="Email"
            autoComplete="email"
            value={email}
            onChange={setEmail}
            aria-label="Email"
            required
          />
          </div>
          <div className={pillActionsRowClass}>
            <PillButton variant="main" type="submit" disabled={loading || message !== null} loading={loading}>
              {message ? 'Reset-link verstuurd' : 'Verstuur reset-link'}
            </PillButton>
            <Link to="/platform/login" className={pillButtonClass('second')}>
              Terug naar login
            </Link>
          </div>
        </form>
      }
    />
  )
}

import { type FormEvent, useEffect, useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { GridShell } from '../layout/GridShell'
import { GRID2_REVIEW_MAIN_WIDTH } from '../layout/grid.constants'
import { FieldSingle } from '../components/ui/Field'
import { PillButton, pillButtonClass, designStackClass } from '../components/PillButton'
import { completePlatformPasswordReset, validatePlatformPasswordResetToken } from '../api/platform'
import { ApiError, isApiConfigured } from '../api/client'

const AUTH_SHELL = {
  activeNav: 'logout' as const,
  showNav: false,
  forceLightTheme: true,
  logoVariant: 'platform' as const,
  mainLayout: 'grid2' as const,
}

/** Platform — nieuw wachtwoord instellen via token uit reset-mail. */
export function PlatformResetPasswordPage() {
  const { token } = useParams<{ token?: string }>()
  const navigate = useNavigate()
  const [name, setName] = useState('')
  const [password, setPassword] = useState('')
  const [passwordRetype, setPasswordRetype] = useState('')
  const [loading, setLoading] = useState(true)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!token) {
      setError('Geen reset-link.')
      setLoading(false)
      return
    }
    if (!isApiConfigured()) {
      setError('API niet geconfigureerd')
      setLoading(false)
      return
    }

    setLoading(true)
    validatePlatformPasswordResetToken(token)
      .then((result) => {
        setName(result.name)
      })
      .catch((err: unknown) => {
        setError(err instanceof ApiError ? err.message : 'Link ongeldig of verlopen')
      })
      .finally(() => setLoading(false))
  }, [token])

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    if (!token) return

    setSubmitting(true)
    setError(null)
    try {
      const result = await completePlatformPasswordReset({ token, password, passwordRetype })
      if (result.authenticated) {
        navigate('/platform', { replace: true })
        return
      }
      navigate('/platform/login', {
        replace: true,
        state: { resetSuccess: 'Je wachtwoord is gewijzigd. Log in met je nieuwe wachtwoord.' },
      })
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Wachtwoord instellen mislukt')
    } finally {
      setSubmitting(false)
    }
  }

  if (loading) {
    return (
      <GridShell
        {...AUTH_SHELL}
        pageTitle="Nieuw wachtwoord"
        main={<p className="m-0 text-[14px] leading-[18px] text-ce-beige">Link controleren…</p>}
      />
    )
  }

  if (error && name === '') {
    return (
      <GridShell
        {...AUTH_SHELL}
        pageTitle="Nieuw wachtwoord"
        main={
          <div className={designStackClass} style={{ maxWidth: GRID2_REVIEW_MAIN_WIDTH }}>
            <p className="m-0 text-[14px] leading-[18px] text-ce-red">{error}</p>
            <Link to="/platform/forgot-password" className={pillButtonClass('second')}>
              Nieuwe reset-link aanvragen
            </Link>
          </div>
        }
      />
    )
  }

  return (
    <GridShell
      {...AUTH_SHELL}
      pageTitle="Nieuw wachtwoord"
      main={
        <form
          className={designStackClass}
          style={{ maxWidth: GRID2_REVIEW_MAIN_WIDTH }}
          onSubmit={(e) => void onSubmit(e)}
        >
          {name && (
            <p className="m-0 text-[14px] leading-[20px] text-ce-beige">
              Nieuw wachtwoord voor <strong className="font-semibold text-ce-black">{name}</strong>
            </p>
          )}
          {error && <p className="m-0 text-[14px] leading-[18px] text-ce-red">{error}</p>}
          <FieldSingle
            id="platform-reset-password"
            type="password"
            placeholder="Nieuw wachtwoord"
            autoComplete="new-password"
            value={password}
            onChange={setPassword}
            aria-label="Nieuw wachtwoord"
          />
          <FieldSingle
            id="platform-reset-password-retype"
            type="password"
            placeholder="Herhaal wachtwoord"
            autoComplete="new-password"
            value={passwordRetype}
            onChange={setPasswordRetype}
            aria-label="Herhaal wachtwoord"
          />
          <PillButton
            variant="main"
            type="submit"
            disabled={submitting || password === '' || passwordRetype === ''}
            loading={submitting}
          >
            Wachtwoord opslaan
          </PillButton>
        </form>
      }
    />
  )
}

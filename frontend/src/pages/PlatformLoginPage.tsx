import { type FormEvent, useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { GridShell } from '../layout/GridShell'
import { GRID2_REVIEW_MAIN_WIDTH } from '../layout/grid.constants'
import { FieldSingle } from '../components/ui/Field'
import { PillButton, pillActionsRowClass, pillButtonClass, designStackClass } from '../components/PillButton'
import { platformLogin } from '../api/platform'
import { isApiConfigured } from '../api/client'

export function PlatformLoginPage() {
  const navigate = useNavigate()
  const location = useLocation()
  const redirectTo = (location.state as { from?: string } | null)?.from ?? '/platform'
  const resetSuccess = (location.state as { resetSuccess?: string } | null)?.resetSuccess ?? null
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setLoading(true)
    setError(null)
    if (!isApiConfigured()) {
      setError('API niet geconfigureerd')
      setLoading(false)
      return
    }
    try {
      const result = await platformLogin({ email: email.trim(), password })
      if (!result.ok) {
        setError('Inloggen mislukt')
        return
      }
      navigate(redirectTo, { replace: true })
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Inloggen mislukt')
    } finally {
      setLoading(false)
    }
  }

  return (
    <GridShell
      pageTitle="Platform"
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
          {(resetSuccess || error) && (
            <div className="flex flex-col gap-[10px]">
              {resetSuccess && (
                <p className="m-0 text-[14px] leading-[18px] text-ce-green-text">{resetSuccess}</p>
              )}
              {error && <p className="m-0 text-[14px] leading-[18px] text-ce-red">{error}</p>}
            </div>
          )}
          <FieldSingle
            id="platform-login-email"
            type="email"
            placeholder="Email"
            value={email}
            onChange={setEmail}
            aria-label="Email"
            autoComplete="username"
            required
          />
          <FieldSingle
            id="platform-login-password"
            type="password"
            placeholder="Password"
            autoComplete="current-password"
            value={password}
            onChange={setPassword}
            aria-label="Password"
            required
          />
          </div>
          <div className={pillActionsRowClass}>
            <PillButton variant="main" type="submit" disabled={loading} loading={loading}>
              Inloggen
            </PillButton>
            <Link to="/platform/forgot-password" className={pillButtonClass('second')}>
              Forgot password
            </Link>
            <Link to="/login" className={pillButtonClass('second')}>
              Tenant-login
            </Link>
          </div>
        </form>
      }
    />
  )
}

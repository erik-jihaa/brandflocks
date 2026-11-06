import { type FormEvent, useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { GridShell } from '../layout/GridShell'
import { GRID2_REVIEW_MAIN_WIDTH } from '../layout/grid.constants'
import { FieldSingle } from '../components/ui/Field'
import { Pulldown } from '../components/ui/Pulldown'
import { PillButton, pillActionsRowClass, pillButtonClass, designStackClass } from '../components/PillButton'
import { login, clientPickLabel, type ClientSearchHit } from '../api/account'
import { isApiConfigured } from '../api/client'

/** Login-scherm — §5.9 (MVP) */
export function LoginPage() {
  const navigate = useNavigate()
  const location = useLocation()
  const redirectTo = (location.state as { from?: string } | null)?.from ?? '/'
  const resetSuccess = (location.state as { resetSuccess?: string } | null)?.resetSuccess ?? null
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [clientChoices, setClientChoices] = useState<ClientSearchHit[]>([])
  const [selectedClientLabel, setSelectedClientLabel] = useState('')

  async function completeLogin(clientId?: string) {
    const result = await login({
      email: email.trim() || undefined,
      password,
      clientId,
    })

    if ('requiresClientPick' in result && result.requiresClientPick) {
      setClientChoices(result.clients)
      setSelectedClientLabel('')
      return
    }

    if (!result.ok) {
      setError('Inloggen mislukt')
      return
    }

    navigate(redirectTo, { replace: true })
  }

  async function onSubmit(e: FormEvent) {
    e.preventDefault()
    setLoading(true)
    setError(null)
    setClientChoices([])
    setSelectedClientLabel('')
    if (!isApiConfigured()) {
      setError('API niet geconfigureerd')
      setLoading(false)
      return
    }
    try {
      await completeLogin()
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Inloggen mislukt')
    } finally {
      setLoading(false)
    }
  }

  async function pickClient(clientId: string) {
    setLoading(true)
    setError(null)
    try {
      await completeLogin(clientId)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Inloggen mislukt')
    } finally {
      setLoading(false)
    }
  }

  return (
    <GridShell
      pageTitle="Login"
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
          {(resetSuccess || error || clientChoices.length > 0) && (
            <div className="flex flex-col gap-[10px]">
              {resetSuccess && (
                <p className="m-0 text-[14px] leading-[18px] text-ce-green-text">{resetSuccess}</p>
              )}
              {error && <p className="m-0 text-[14px] leading-[18px] text-ce-red">{error}</p>}
              {clientChoices.length > 0 && (
                <Pulldown
                  placeholder="Kies je account"
                  options={clientChoices.map((client) => clientPickLabel(client))}
                  value={selectedClientLabel}
                  onChange={(label) => {
                    setSelectedClientLabel(label)
                    const client = clientChoices.find((hit) => clientPickLabel(hit) === label)
                    if (client) void pickClient(client.clientId)
                  }}
                />
              )}
            </div>
          )}
          <FieldSingle
            id="login-email"
            type="email"
            placeholder="Email"
            value={email}
            onChange={setEmail}
            aria-label="Email"
            required
          />
          <FieldSingle
            id="login-password"
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
              Login
            </PillButton>
            <Link to="/forgot-password" className={pillButtonClass('second')}>
              Forgot password
            </Link>
          </div>
        </form>
      }
    />
  )
}

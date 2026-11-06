import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { GridShell } from '../layout/GridShell'
import { GRID2_REVIEW_MAIN_WIDTH } from '../layout/grid.constants'
import { FieldSingle } from '../components/ui/Field'
import { FieldHint, FORM_FIELD_STACK_CLASS } from '../components/ui/FieldHint'
import { Pulldown } from '../components/ui/Pulldown'
import { PillButton, pillActionsRowClass, designStackClass } from '../components/PillButton'
import {
  fetchAccount,
  patchAccount,
  searchClients,
  switchAccountClient,
  uploadAccountLogo,
  type AccountDto,
  type ClientSearchHit,
  type ContactRole,
  type PublishMode,
} from '../api/account'
import { TENANT_LOGO_CHANGED_EVENT, TENANT_LOGO_IMG_CLASS, tenantLogoImgStyle } from '../components/Logo'
import { TenantLogoField } from '../components/TenantLogoField'
import { isApiConfigured } from '../api/client'
import { HorizontalRule } from '../components/ui/HorizontalRule'
import { SaveNotice } from '../components/ui/SaveNotice'
import { ActionNotice } from '../components/ui/ActionNotice'
import { useSaveNotice } from '../hooks/useSaveNotice'
import { useActionNotice } from '../hooks/useActionNotice'
import { useFocusSection } from '../hooks/useFocusSection'

const EMPTY: AccountDto = {
  clientId: '',
  company: '',
  contactName: '',
  contactRole: 'both',
  contactEmail: '',
  reviewerEmail: '',
  logoUrl: '',
  publishMode: 'manual_with_review',
  imageDatabaseUrl: '',
  hasImageDatabasePassword: false,
  active: true,
  planningHorizonWeeks: null,
  postsPerWeek: null,
  sourceChecksPerWeek: null,
  hasPassword: false,
  websiteCms: '',
  websiteBlogUrl: '',
  wordpressSiteUrl: '',
  wordpressUser: '',
  hasWordpressAppPassword: false,
  wordpressAuthorId: null,
  siteFtpHost: '',
  siteFtpPort: 21,
  siteFtpUser: '',
  hasSiteFtpPassword: false,
  siteFtpRemotePath: '',
  joomlaSiteUrl: '',
  joomlaCategoryId: null,
  hasJoomlaApiToken: false,
  publishExtraDestinations: [],
}

const ROLE_OPTIONS: { value: ContactRole; label: string }[] = [
  { value: 'contact', label: 'Contact' },
  { value: 'reviewer', label: 'Reviewer' },
  { value: 'both', label: 'Contact & reviewer' },
]

const PUBLISH_MODE_OPTIONS: { value: PublishMode; label: string }[] = [
  { value: 'manual_with_review', label: 'Handmatig met tegenlezer' },
  { value: 'manual_without_review', label: 'Handmatig zonder tegenlezer' },
]

const LOGO_SVG_MESSAGE = 'SVG-logo’s worden niet ondersteund. Upload een PNG met transparante achtergrond.'
const LOGO_HINT = 'PNG met transparante achtergrond werkt het best. JPG en WEBP kunnen ook; SVG niet.'

/** Account-scherm — briefing/account new.jpg */
export function AccountPage() {
  const logoInputRef = useRef<HTMLInputElement>(null)
  const [logoCacheKey, setLogoCacheKey] = useState(0)
  const [saved, setSaved] = useState<AccountDto>(EMPTY)
  const [draft, setDraft] = useState<AccountDto>(EMPTY)
  const [password, setPassword] = useState('')
  const [passwordRetype, setPasswordRetype] = useState('')
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [uploading, setUploading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [note, setNote] = useState<string | null>(null)
  const {
    message: saveMessage,
    kind: saveNoticeKind,
    fading: saveNoticeFading,
    showSaving: showSaveSaving,
    showSuccess: showSaveSuccess,
    showError: showSaveError,
  } = useSaveNotice('Account opgeslagen')
  const {
    message: logoNotice,
    kind: logoNoticeKind,
    fading: logoNoticeFading,
    showSuccess: showLogoSuccess,
    showError: showLogoError,
  } = useActionNotice()
  const [tenantQuery, setTenantQuery] = useState('')
  const [tenantHits, setTenantHits] = useState<ClientSearchHit[]>([])
  const [tenantSearchLoading, setTenantSearchLoading] = useState(false)
  const [tenantSwitching, setTenantSwitching] = useState(false)

  const load = useCallback(async (clientId?: string) => {
    setLoading(true)
    setError(null)
    if (!isApiConfigured()) {
      setSaved(EMPTY)
      setDraft(EMPTY)
      setNote('API niet geconfigureerd')
      setLoading(false)
      return
    }
    try {
      const data = await fetchAccount(clientId)
      if (data.error) setNote(data.error)
      const account = { ...EMPTY, ...(data.account ?? {}) }
      setSaved(account)
      setDraft(account)
      setPassword('')
      setPasswordRetype('')
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Laden mislukt')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  useFocusSection(['publish-mode'])

  useEffect(() => {
    if (tenantQuery.trim() !== '' || !isApiConfigured()) {
      return
    }
    let cancelled = false
    void (async () => {
      setTenantSearchLoading(true)
      try {
        // Zonder zoekterm geeft de API de tenants van het ingelogde e-mailadres.
        const { clients } = await searchClients()
        if (!cancelled) {
          setTenantHits(clients)
        }
      } catch {
        if (!cancelled) setTenantHits([])
      } finally {
        if (!cancelled) setTenantSearchLoading(false)
      }
    })()
    return () => {
      cancelled = true
    }
  }, [saved.clientId, tenantQuery])

  useEffect(() => {
    const q = tenantQuery.trim()
    if (q === '') {
      return
    }
    let cancelled = false
    const timer = window.setTimeout(() => {
      void (async () => {
        setTenantSearchLoading(true)
        try {
          const { clients } = await searchClients(q)
          if (!cancelled) setTenantHits(clients)
        } catch {
          if (!cancelled) setTenantHits([])
        } finally {
          if (!cancelled) setTenantSearchLoading(false)
        }
      })()
    }, 250)
    return () => {
      cancelled = true
      window.clearTimeout(timer)
    }
  }, [tenantQuery])

  const roleLabel =
    ROLE_OPTIONS.find((opt) => opt.value === draft.contactRole)?.label ?? ROLE_OPTIONS[0].label
  const publishModeLabel =
    PUBLISH_MODE_OPTIONS.find((opt) => opt.value === draft.publishMode)?.label ??
    PUBLISH_MODE_OPTIONS[0].label

  const dirty = useMemo(() => {
    const baseDirty =
      draft.company !== saved.company ||
      draft.contactName !== saved.contactName ||
      draft.contactRole !== saved.contactRole ||
      draft.contactEmail !== saved.contactEmail ||
      draft.reviewerEmail !== saved.reviewerEmail ||
      draft.logoUrl !== saved.logoUrl ||
      draft.publishMode !== saved.publishMode
    return baseDirty || password !== '' || passwordRetype !== ''
  }, [draft, saved, password, passwordRetype])

  function undo() {
    setDraft(saved)
    setPassword('')
    setPasswordRetype('')
  }

  async function switchTenant(target: ClientSearchHit) {
    if (target.clientId === draft.clientId) return
    if (
      dirty &&
      !window.confirm('Je hebt niet-opgeslagen wijzigingen. Toch van account wisselen?')
    ) {
      return
    }
    setTenantSwitching(true)
    setError(null)
    try {
      await switchAccountClient(draft.clientId, target.clientId)
      window.location.assign('/')
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Account wisselen mislukt')
      setTenantSwitching(false)
    }
  }

  async function save() {
    setSaving(true)
    setError(null)
    showSaveSaving()
    try {
      const body: Record<string, unknown> = {
        clientId: draft.clientId,
        company: draft.company,
        contactName: draft.contactName,
        contactRole: draft.contactRole,
        contactEmail: draft.contactEmail,
        reviewerEmail: draft.reviewerEmail,
        publishMode: draft.publishMode,
      }
      if (password !== '') {
        body.password = password
        body.passwordRetype = passwordRetype
      }
      const result = await patchAccount(body)
      const account = result.account ?? draft
      setSaved(account)
      setDraft(account)
      setPassword('')
      setPasswordRetype('')
      showSaveSuccess()
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Opslaan mislukt'
      setError(message)
      showSaveError(message)
    } finally {
      setSaving(false)
    }
  }

  async function onLogoChange(file: File | null) {
    if (!file || !draft.clientId) return
    if (file.type === 'image/svg+xml' || /\.svgz?$/i.test(file.name)) {
      if (logoInputRef.current) logoInputRef.current.value = ''
      setError(LOGO_SVG_MESSAGE)
      showLogoError(LOGO_SVG_MESSAGE)
      return
    }
    setUploading(true)
    setError(null)
    try {
      const result = await uploadAccountLogo(file, draft.clientId)
      setDraft((d) => ({ ...d, logoUrl: result.logoUrl }))
      setSaved((s) => ({ ...s, logoUrl: result.logoUrl }))
      setLogoCacheKey((k) => k + 1)
      if (logoInputRef.current) logoInputRef.current.value = ''
      window.dispatchEvent(new Event(TENANT_LOGO_CHANGED_EVENT))
      showLogoSuccess('Logo opgeslagen.')
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Logo upload mislukt'
      setError(message)
      showLogoError(message)
    } finally {
      setUploading(false)
    }
  }

  async function deleteLogo() {
    if (!draft.logoUrl || !isApiConfigured() || !draft.clientId) return
    setUploading(true)
    setError(null)
    try {
      const result = await patchAccount({ clientId: draft.clientId, logoUrl: '' })
      const account = result.account ?? { ...draft, logoUrl: '' }
      setDraft(account)
      setSaved(account)
      setLogoCacheKey((k) => k + 1)
      window.dispatchEvent(new Event(TENANT_LOGO_CHANGED_EVENT))
      showLogoSuccess('Logo verwijderd.')
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Logo verwijderen mislukt'
      setError(message)
      showLogoError(message)
    } finally {
      setUploading(false)
    }
  }

  if (loading) {
    return <GridShell pageTitle="Mijn account" activeNav="account" mainLayout="grid2" main={<p>Laden…</p>} />
  }

  return (
    <GridShell
      pageTitle="Mijn account"
      activeNav="account"
      mainLayout="grid2"
      main={
        <div className={designStackClass}>
          <div>
            <TenantLogoField placement="account" bordered>
              {draft.logoUrl ? (
                <img
                  src={`${draft.logoUrl}?v=${logoCacheKey}`}
                  alt="Klantlogo"
                  className={TENANT_LOGO_IMG_CLASS}
                  style={tenantLogoImgStyle()}
                />
              ) : null}
            </TenantLogoField>
            <input
              ref={logoInputRef}
              type="file"
              accept="image/png,image/jpeg,image/webp"
              className="sr-only"
              disabled={uploading || !draft.clientId}
              onChange={(e) => void onLogoChange(e.target.files?.[0] ?? null)}
            />
          </div>
          <div className={pillActionsRowClass}>
            <PillButton
              variant="main"
              disabled={uploading || !draft.clientId}
              loading={uploading}
              onClick={() => logoInputRef.current?.click()}
            >
              Upload logo
            </PillButton>
            {draft.logoUrl ? (
              <PillButton variant="second" disabled={uploading} onClick={() => void deleteLogo()}>
                Delete
              </PillButton>
            ) : null}
          </div>
          <ActionNotice message={logoNotice} kind={logoNoticeKind} fading={logoNoticeFading} />

          <p className="m-0 text-[14px] leading-[18px] text-ce-beige">{LOGO_HINT}</p>

          {note && <p className="m-0 text-[14px] leading-[18px] text-ce-beige">{note}</p>}
          {error && <p className="m-0 text-[14px] leading-[18px] text-ce-red">{error}</p>}

          <div className={FORM_FIELD_STACK_CLASS} style={{ maxWidth: GRID2_REVIEW_MAIN_WIDTH }}>
          <FieldSingle
            placeholder="Company"
            value={draft.company}
            onChange={(v) => setDraft((d) => ({ ...d, company: v }))}
          />
          <FieldSingle
            placeholder="Name"
            value={draft.contactName}
            onChange={(v) => setDraft((d) => ({ ...d, contactName: v }))}
          />

          <Pulldown
            placeholder="Role"
            options={ROLE_OPTIONS.map((opt) => opt.label)}
            value={roleLabel}
            onChange={(label) => {
              const match = ROLE_OPTIONS.find((opt) => opt.label === label)
              if (match) {
                setDraft((d) => ({ ...d, contactRole: match.value }))
              }
            }}
          />

          <FieldSingle
            placeholder="Email adres contact"
            type="email"
            value={draft.contactEmail}
            onChange={(v) => setDraft((d) => ({ ...d, contactEmail: v }))}
          />
          <FieldSingle
            placeholder="Email adres reviewer"
            type="email"
            value={draft.reviewerEmail}
            onChange={(v) => setDraft((d) => ({ ...d, reviewerEmail: v }))}
          />

          <HorizontalRule />

          <FieldSingle
            id="account-password"
            placeholder={saved.hasPassword ? '••••••••' : 'Password'}
            type="password"
            value={password}
            onChange={setPassword}
          />
          <FieldSingle
            id="account-password-retype"
            placeholder="Retype password"
            type="password"
            value={passwordRetype}
            onChange={setPasswordRetype}
          />

          <HorizontalRule />

          <div id="publish-mode">
          <Pulldown
            placeholder="Publication mode"
            options={PUBLISH_MODE_OPTIONS.map((opt) => opt.label)}
            value={publishModeLabel}
            onChange={(label) => {
              const match = PUBLISH_MODE_OPTIONS.find((opt) => opt.label === label)
              if (match) {
                setDraft((d) => ({ ...d, publishMode: match.value }))
              }
            }}
          />
          {draft.publishMode === 'manual_without_review' && (
            <FieldHint position="below">
              Bij Publiceer op het artikel vraagt CE eerst om bevestiging — er is geen tegenlezer.
              WordPress-koppeling staat op Integrations.
            </FieldHint>
          )}
          <p className="m-0 px-[30px] text-[14px] leading-[18px] text-ce-black">
            WordPress en website-publicatie stel je in op{' '}
            <Link to="/integrations" className="text-ce-blue underline">
              Integrations → Website
            </Link>
            .
          </p>

          </div>

          <HorizontalRule />

          <div className={FORM_FIELD_STACK_CLASS}>
            <p className="m-0 px-[30px] text-[14px] font-semibold leading-[18px] text-ce-black">
              Wissel van account
            </p>
            <p className="m-0 px-[30px] text-[14px] leading-[18px] text-ce-black">
              Huidig: <span className="font-semibold">{draft.company || '—'}</span>
            </p>
            <FieldSingle
              id="account-tenant-search"
              placeholder="Zoek op bedrijfsnaam of e-mail"
              value={tenantQuery}
              onChange={setTenantQuery}
              hint="Meerdere tenants op hetzelfde e-mailadres? Kies hier een ander account. Agenda, bronnen en instellingen zijn per tenant gescheiden."
            />
            {tenantSearchLoading && (
              <p className="m-0 px-[30px] text-[14px] leading-[18px] text-ce-beige">Zoeken…</p>
            )}
            {!tenantSearchLoading && tenantHits.length > 0 && (
              <div className="flex flex-col gap-[8px] px-[30px]">
                {tenantHits.map((hit) => {
                  const isCurrent = hit.clientId === draft.clientId
                  return (
                    <PillButton
                      key={hit.clientId}
                      variant={isCurrent ? 'main' : 'second'}
                      type="button"
                      disabled={tenantSwitching || isCurrent}
                      onClick={() => void switchTenant(hit)}
                    >
                      {hit.company}
                      {isCurrent ? ' (actief)' : ''}
                    </PillButton>
                  )
                })}
              </div>
            )}
            {!tenantSearchLoading && tenantHits.length === 0 && tenantQuery.trim() !== '' && (
              <p className="m-0 px-[30px] text-[14px] leading-[18px] text-ce-beige">
                Geen accounts gevonden.
              </p>
            )}
          </div>

          <div className={pillActionsRowClass}>
            <PillButton variant="main" onClick={() => void save()} disabled={saving || !dirty} loading={saving}>
              Opslaan
              </PillButton>
              <PillButton variant="second" onClick={undo} disabled={saving || !dirty}>
              Ongedaan maken
            </PillButton>
          </div>
          <SaveNotice message={saveMessage} kind={saveNoticeKind} fading={saveNoticeFading} />
          </div>
        </div>
      }
    />
  )
}

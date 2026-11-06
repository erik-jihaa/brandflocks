import { useCallback, useEffect, useMemo, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { GridShell } from '../layout/GridShell'
import { GRID2_REVIEW_MAIN_WIDTH, LIST_SECTION } from '../layout/grid.constants'
import { PillButton } from '../components/PillButton'
import { SaveNotice } from '../components/ui/SaveNotice'
import { WordPressPublishFields } from '../components/WordPressPublishFields'
import { IntegrationCard } from '../components/IntegrationCard'
import { useSaveNotice } from '../hooks/useSaveNotice'
import {
  fetchAccount,
  patchAccount,
  testWordPressConnection,
  testJoomlaConnection,
  type AccountDto,
} from '../api/account'
import {
  checkSource,
  fetchAllSources,
  startAnalyticsOAuth,
  startCalendarOAuth,
} from '../api/sources'
import {
  disconnectGoogleOAuth,
  fetchIntegrationsStatus,
  type IntegrationStatusEntry,
  type IntegrationsStatusResponse,
} from '../api/integrations'
import {
  GOOGLE_INTEGRATION_KEYS,
  INTEGRATION_GROUPS,
  integrationsForGroup,
  type IntegrationDef,
  type IntegrationKey,
} from '../config/integrations'
import { isApiConfigured } from '../api/client'

const EMPTY_ACCOUNT: AccountDto = {
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

function IntegrationStatusStrip({
  summary,
  filtered,
  onToggleFilter,
}: {
  summary: IntegrationsStatusResponse['summary']
  filtered: boolean
  onToggleFilter: () => void
}) {
  if (filtered) {
    return (
      <p className={`m-0 ${LIST_SECTION.itemClass} text-[14px] leading-[18px] text-ce-black`}>
        Gefilterd op actie nodig ·{' '}
        <button type="button" className="cursor-pointer border-0 bg-transparent p-0 text-ce-blue underline" onClick={onToggleFilter}>
          Toon alles
        </button>
      </p>
    )
  }

  return (
    <p className={`m-0 ${LIST_SECTION.itemClass} text-[14px] leading-[18px] text-ce-black`}>
      Koppelingen{' '}
      <span className="text-ce-green-text">{summary.active} actief</span>
      {' · '}
      {summary.actionRequired > 0 ? (
        <button
          type="button"
          className="cursor-pointer border-0 bg-transparent p-0 text-ce-red underline"
          onClick={onToggleFilter}
        >
          {summary.actionRequired} actie nodig
        </button>
      ) : (
        <span className="text-ce-beige">0 actie nodig</span>
      )}
      {' · '}
      <span className="text-ce-beige">{summary.notSet} niet ingesteld</span>
    </p>
  )
}

/** Integrations hub — uniforme kaarten in drie groepen. */
export function IntegrationsPage() {
  const [searchParams, setSearchParams] = useSearchParams()

  const [saved, setSaved] = useState<AccountDto>(EMPTY_ACCOUNT)
  const [draft, setDraft] = useState<AccountDto>(EMPTY_ACCOUNT)
  const [wordpressAppPassword, setWordpressAppPassword] = useState('')
  const [joomlaApiToken, setJoomlaApiToken] = useState('')
  const [siteFtpPassword, setSiteFtpPassword] = useState('')
  const [wpSaving, setWpSaving] = useState(false)
  const [wpError, setWpError] = useState<string | null>(null)
  const {
    message: wpSaveMessage,
    kind: wpSaveNoticeKind,
    fading: wpSaveNoticeFading,
    showSaving: showWpSaving,
    showSuccess: showWpSuccess,
    showError: showWpError,
  } = useSaveNotice('Website-instellingen opgeslagen')

  const [statusData, setStatusData] = useState<IntegrationsStatusResponse | null>(null)
  const [expandedKey, setExpandedKey] = useState<IntegrationKey | null>(null)
  const [filterActionRequired, setFilterActionRequired] = useState(false)
  const [loading, setLoading] = useState(true)
  const [busyKey, setBusyKey] = useState<IntegrationKey | null>(null)
  const [testingKey, setTestingKey] = useState<IntegrationKey | null>(null)
  const [testNotes, setTestNotes] = useState<Partial<Record<IntegrationKey, string>>>({})
  const [testOk, setTestOk] = useState<Partial<Record<IntegrationKey, boolean>>>({})

  const wpDirty = useMemo(() => {
    const baseDirty =
      draft.websiteCms !== saved.websiteCms ||
      draft.websiteBlogUrl !== saved.websiteBlogUrl ||
      draft.wordpressSiteUrl !== saved.wordpressSiteUrl ||
      draft.wordpressUser !== saved.wordpressUser ||
      draft.wordpressAuthorId !== saved.wordpressAuthorId ||
      draft.siteFtpHost !== saved.siteFtpHost ||
      draft.siteFtpPort !== saved.siteFtpPort ||
      draft.siteFtpUser !== saved.siteFtpUser ||
      draft.siteFtpRemotePath !== saved.siteFtpRemotePath ||
      draft.joomlaSiteUrl !== saved.joomlaSiteUrl ||
      draft.joomlaCategoryId !== saved.joomlaCategoryId
    return baseDirty || wordpressAppPassword !== '' || joomlaApiToken !== '' || siteFtpPassword !== ''
  }, [draft, saved, wordpressAppPassword, joomlaApiToken, siteFtpPassword])

  const loadAll = useCallback(async () => {
    if (!isApiConfigured()) return
    const [accountRes, statusRes] = await Promise.all([fetchAccount(), fetchIntegrationsStatus()])
    const account = { ...EMPTY_ACCOUNT, ...(accountRes.account ?? {}) }
    setSaved(account)
    setDraft(account)
    setWordpressAppPassword('')
    setJoomlaApiToken('')
    setSiteFtpPassword('')
    setStatusData(statusRes)
  }, [])

  useEffect(() => {
    setLoading(true)
    void loadAll()
      .catch(() => {
        // negeer
      })
      .finally(() => setLoading(false))
  }, [loadAll])

  useEffect(() => {
    const linkedinConnectedParam = searchParams.get('linkedin_connected')
    const linkedinOauthError = searchParams.get('linkedin_oauth_error')
    const connected = searchParams.get('connected')
    const oauthError = searchParams.get('oauth_error')
    if (linkedinConnectedParam === '1' || connected === '1' || linkedinOauthError || oauthError) {
      void loadAll()
    }
    if (linkedinConnectedParam || linkedinOauthError || connected || oauthError) {
      const next = new URLSearchParams(searchParams)
      next.delete('linkedin_connected')
      next.delete('linkedin_oauth_error')
      next.delete('connected')
      next.delete('oauth_error')
      setSearchParams(next, { replace: true })
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  const refreshStatus = useCallback(async () => {
    if (!isApiConfigured()) return
    const statusRes = await fetchIntegrationsStatus()
    setStatusData(statusRes)
  }, [])

  async function saveWebsiteCms() {
    if (!isApiConfigured()) return
    setWpSaving(true)
    setWpError(null)
    showWpSaving()
    try {
      const body: Record<string, unknown> = {
        clientId: draft.clientId,
        websiteCms: draft.websiteCms,
        websiteBlogUrl: draft.websiteBlogUrl,
      }
      if (draft.websiteCms === 'wordpress') {
        body.wordpressSiteUrl = draft.wordpressSiteUrl
        body.wordpressUser = draft.wordpressUser
        body.wordpressAuthorId = draft.wordpressAuthorId
        if (wordpressAppPassword !== '') body.wordpressAppPassword = wordpressAppPassword
      }
      if (draft.websiteCms === 'joomla') {
        body.joomlaSiteUrl = draft.joomlaSiteUrl
        body.joomlaCategoryId = draft.joomlaCategoryId
        if (joomlaApiToken !== '') body.joomlaApiToken = joomlaApiToken
      }
      if (draft.websiteCms === 'static') {
        body.siteFtpHost = draft.siteFtpHost
        body.siteFtpPort = draft.siteFtpPort
        body.siteFtpUser = draft.siteFtpUser
        body.siteFtpRemotePath = draft.siteFtpRemotePath
        if (siteFtpPassword !== '') body.siteFtpPassword = siteFtpPassword
      }
      const result = await patchAccount(body)
      const account = { ...EMPTY_ACCOUNT, ...(result.account ?? draft) }
      setSaved(account)
      setDraft(account)
      setWordpressAppPassword('')
      setJoomlaApiToken('')
      setSiteFtpPassword('')
      showWpSuccess()
      await refreshStatus()
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Opslaan mislukt'
      setWpError(message)
      showWpError(message)
    } finally {
      setWpSaving(false)
    }
  }

  async function runTest(key: IntegrationKey) {
    if (!isApiConfigured()) return
    setTestingKey(key)
    setTestNotes((prev) => ({ ...prev, [key]: undefined }))
    setTestOk((prev) => ({ ...prev, [key]: undefined }))
    try {
      if (key === 'website_cms') {
        if (draft.websiteCms === 'wordpress') {
          const body: Record<string, string> = {
            wordpressSiteUrl: draft.wordpressSiteUrl,
            wordpressUser: draft.wordpressUser,
          }
          if (wordpressAppPassword !== '') body.wordpressAppPassword = wordpressAppPassword
          const result = await testWordPressConnection(body)
          if (result.connected) {
            const who = result.authenticatedAs?.name ?? result.authenticatedAs?.slug ?? 'WordPress'
            setTestOk((prev) => ({ ...prev, [key]: true }))
            setTestNotes((prev) => ({ ...prev, [key]: `Verbinding OK — ingelogd als ${who}.` }))
          } else {
            setTestOk((prev) => ({ ...prev, [key]: false }))
            setTestNotes((prev) => ({ ...prev, [key]: result.authError ?? 'Verbinding mislukt.' }))
          }
        } else if (draft.websiteCms === 'joomla') {
          const body: Record<string, string | number> = {
            joomlaSiteUrl: draft.joomlaSiteUrl,
            joomlaCategoryId: draft.joomlaCategoryId ?? 0,
          }
          if (joomlaApiToken !== '') body.joomlaApiToken = joomlaApiToken
          const result = await testJoomlaConnection(body)
          if (result.connected) {
            const category = result.categoryTitle ? ` — categorie “${result.categoryTitle}”` : ''
            setTestOk((prev) => ({ ...prev, [key]: true }))
            setTestNotes((prev) => ({ ...prev, [key]: `Joomla API OK${category}.` }))
          } else {
            setTestOk((prev) => ({ ...prev, [key]: false }))
            setTestNotes((prev) => ({ ...prev, [key]: result.authError ?? 'Verbinding mislukt.' }))
          }
        } else {
          setTestOk((prev) => ({ ...prev, [key]: false }))
          setTestNotes((prev) => ({ ...prev, [key]: 'Kies WordPress, Joomla of FTP en sla op.' }))
        }
      } else if (key === 'ga4' || key === 'calendar' || key === 'mailbox' || key === 'trello' || key === 'apify_scraper') {
        const { sources } = await fetchAllSources()
        const category =
          key === 'ga4'
            ? 'Analythics'
            : key === 'calendar'
              ? 'Calendar'
              : key === 'mailbox'
                ? 'Mailbox'
                : key === 'trello'
                  ? 'Trello'
                  : null
        const matches = category
          ? sources.filter((s) => s.category === category)
          : sources.filter((s) => s.category === 'Scraper' || s.category === 'Linkedin')
        if (matches.length === 0) {
          setTestOk((prev) => ({ ...prev, [key]: false }))
          setTestNotes((prev) => ({ ...prev, [key]: 'Geen source — maak er een aan op Sources.' }))
        } else {
          let okCount = 0
          for (const source of matches) {
            const checked = await checkSource(source.id)
            if (checked.checkStatus === 'green') okCount++
          }
          const allOk = okCount === matches.length
          setTestOk((prev) => ({ ...prev, [key]: allOk }))
          setTestNotes((prev) => ({
            ...prev,
            [key]: allOk
              ? `${okCount} source${okCount === 1 ? '' : 's'} OK.`
              : `${okCount} van ${matches.length} sources OK — controleer Sources.`,
          }))
        }
      }
      await refreshStatus()
    } catch (err) {
      setTestOk((prev) => ({ ...prev, [key]: false }))
      setTestNotes((prev) => ({
        ...prev,
        [key]: err instanceof Error ? err.message : 'Test mislukt',
      }))
    } finally {
      setTestingKey(null)
    }
  }

  function integrationSettingsHref(def: IntegrationDef, entry: IntegrationStatusEntry): string {
    const primaryId =
      typeof entry.meta?.primarySourceId === 'string' ? entry.meta.primarySourceId.trim() : ''
    if (primaryId) return `/sources/${primaryId}/edit`
    return def.settingsHref ?? '/sources'
  }

  async function handleConnect(key: IntegrationKey) {
    if (!isApiConfigured()) return
    setBusyKey(key)
    try {
      const { sources } = await fetchAllSources()
      if (key === 'ga4') {
        const source = sources.find((s) => s.category === 'Analythics')
        if (!source) {
          window.location.href = '/sources/new/analytics'
          return
        }
        const result = await startAnalyticsOAuth(source.id, 'integrations')
        if (!result.configured || !result.authUrl) {
          setTestNotes((prev) => ({ ...prev, [key]: result.note ?? 'Google OAuth niet geconfigureerd.' }))
          return
        }
        window.location.href = result.authUrl
        return
      }
      if (key === 'calendar') {
        const source = sources.find((s) => s.category === 'Calendar')
        if (!source) {
          window.location.href = '/sources/new/calendar'
          return
        }
        const result = await startCalendarOAuth(source.id, 'integrations')
        if (!result.configured || !result.authUrl) {
          setTestNotes((prev) => ({ ...prev, [key]: result.note ?? 'Google OAuth niet geconfigureerd.' }))
          return
        }
        window.location.href = result.authUrl
      }
    } catch (err) {
      setTestNotes((prev) => ({
        ...prev,
        [key]: err instanceof Error ? err.message : 'Koppelen mislukt',
      }))
    } finally {
      setBusyKey(null)
    }
  }

  async function handleDisconnect(key: IntegrationKey) {
    if (!isApiConfigured()) return
    setBusyKey(key)
    try {
      if (GOOGLE_INTEGRATION_KEYS.includes(key)) {
        await disconnectGoogleOAuth()
      }
      await loadAll()
    } catch (err) {
      setTestNotes((prev) => ({
        ...prev,
        [key]: err instanceof Error ? err.message : 'Ontkoppelen mislukt',
      }))
    } finally {
      setBusyKey(null)
    }
  }

  function statusFor(key: IntegrationKey): IntegrationStatusEntry {
    return (
      statusData?.integrations[key] ?? {
        status: 'not_set',
        label: 'Niet ingesteld',
        context: '',
        note: null,
        since: null,
        meta: {},
      }
    )
  }

  function cardVisible(entry: IntegrationStatusEntry): boolean {
    if (!filterActionRequired) return true
    return entry.status === 'action_required'
  }

  function renderCardActions(def: IntegrationDef, entry: IntegrationStatusEntry) {
    const key = def.key
    const showConnect =
      key === 'ga4' || key === 'calendar'
        ? entry.status !== 'connected' && entry.status !== 'unavailable'
        : false
    const showDisconnect = GOOGLE_INTEGRATION_KEYS.includes(key)
      ? entry.status === 'connected' || entry.status === 'action_required'
      : false

    return {
      showConnect,
      showDisconnect,
      connectLabel:
        entry.status === 'action_required' && GOOGLE_INTEGRATION_KEYS.includes(key)
          ? 'Opnieuw koppelen'
          : 'Koppel',
    }
  }

  function renderExpandedContent(def: IntegrationDef) {
    if (def.key !== 'website_cms') return null
    return (
      <div className="space-y-[12px]">
        {wpError ? <p className="m-0 text-[14px] leading-[18px] text-ce-red">{wpError}</p> : null}
        <WordPressPublishFields
          draft={draft}
          saved={saved}
          onDraftChange={setDraft}
          wordpressAppPassword={wordpressAppPassword}
          onWordpressAppPasswordChange={setWordpressAppPassword}
          joomlaApiToken={joomlaApiToken}
          onJoomlaApiTokenChange={setJoomlaApiToken}
          siteFtpPassword={siteFtpPassword}
          onSiteFtpPasswordChange={setSiteFtpPassword}
        />
      </div>
    )
  }

  function renderWebsiteLeadingActions() {
    return (
      <PillButton variant="main" disabled={wpSaving || !wpDirty} loading={wpSaving} onClick={() => void saveWebsiteCms()}>
        Opslaan
      </PillButton>
    )
  }

  function renderWebsiteActionNotice() {
    return (
      <SaveNotice
        message={wpSaveMessage}
        kind={wpSaveNoticeKind}
        fading={wpSaveNoticeFading}
        className="mt-0"
      />
    )
  }

  function renderGroupCards(group: (typeof INTEGRATION_GROUPS)[number]['key']) {
    const defs = integrationsForGroup(group)
    const visible = defs.filter((def) => cardVisible(statusFor(def.key)))
    if (visible.length === 0) return null

    return (
      <section key={group} className={`${LIST_SECTION.sectionClass} px-[30px]`}>
        <h2 className={`m-0 ${LIST_SECTION.headerClass} text-ce-black`}>
          {INTEGRATION_GROUPS.find((g) => g.key === group)?.label}
        </h2>
        <div className="mt-[18px]">
          {visible.map((def) => {
            const entry = statusFor(def.key)
            const actions = renderCardActions(def, entry)
            const sourceIntegration =
              def.key === 'calendar' ||
              def.key === 'ga4' ||
              def.key === 'mailbox' ||
              def.key === 'trello' ||
              def.key === 'apify_scraper'
            const sourcesPrimary =
              sourceIntegration &&
              entry.status !== 'connected' &&
              entry.status !== 'unavailable'
            const settingsLabel = sourceIntegration ? 'Sources' : 'Instellingen'
            const isWebsite = def.key === 'website_cms'

            return (
              <IntegrationCard
                key={def.key}
                def={def}
                status={entry}
                expanded={expandedKey === def.key}
                onToggle={() => setExpandedKey((current) => (current === def.key ? null : def.key))}
                onConnect={() => void handleConnect(def.key)}
                onDisconnect={() => void handleDisconnect(def.key)}
                onTest={() => void runTest(def.key)}
                busy={busyKey === def.key}
                testing={testingKey === def.key}
                testNote={testNotes[def.key] ?? null}
                testOk={testOk[def.key] ?? null}
                showConnect={actions.showConnect}
                showDisconnect={actions.showDisconnect}
                connectLabel={actions.connectLabel}
                connectVariant={sourcesPrimary ? 'second' : 'main'}
                showTest
                settingsLabel={settingsLabel}
                settingsHref={integrationSettingsHref(def, entry)}
                settingsAsPill={sourcesPrimary}
                settingsPillVariant="main"
                settingsFirst={sourcesPrimary}
                leadingActions={isWebsite ? renderWebsiteLeadingActions() : undefined}
                actionNotice={isWebsite ? renderWebsiteActionNotice() : undefined}
              >
                {renderExpandedContent(def)}
              </IntegrationCard>
            )
          })}
        </div>
      </section>
    )
  }

  if (loading || !statusData) {
    return <GridShell pageTitle="Koppelingen" activeNav="integrations" mainLayout="grid2" main={<p>Laden…</p>} />
  }

  return (
    <GridShell
      pageTitle="Koppelingen"
      activeNav="integrations"
      mainLayout="grid2"
      main={
        <div className="space-y-0" style={{ maxWidth: GRID2_REVIEW_MAIN_WIDTH }}>
          <div className="px-[30px]">
            <IntegrationStatusStrip
              summary={statusData.summary}
              filtered={filterActionRequired}
              onToggleFilter={() => setFilterActionRequired((v) => !v)}
            />
          </div>

          {INTEGRATION_GROUPS.map((group) => renderGroupCards(group.key))}

          <p className={`m-0 px-[30px] ${LIST_SECTION.itemClass} text-[14px] leading-[18px] text-ce-beige`}>
            Publicatiemodus staat op{' '}
            <Link to="/account?focus=publish-mode" className="text-ce-blue underline">
              Account
            </Link>
            . Bronnen op{' '}
            <Link to="/sources" className="text-ce-blue underline">
              Sources
            </Link>
            .
          </p>
        </div>
      }
    />
  )
}

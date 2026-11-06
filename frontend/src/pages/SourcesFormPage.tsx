import { useCallback, useEffect, useState } from 'react'
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom'
import { GridShell } from '../layout/GridShell'
import { SourceRow } from '../components/SourceRow'
import {
  SourceForm,
  defaultSourceRowTitle,
  emptySourceFormValues,
  sourceDtoToFormValues,
  sourceFormValuesToPayload,
  type SourceFormValues,
} from '../components/SourceForm'
import {
  checkSource,
  createSource,
  deleteSource,
  discoverAsanaProjects,
  fetchSource,
  fetchAllSources,
  fetchAnalyticsOAuthStatus,
  fetchCalendarList,
  fetchCalendarOAuthStatus,
  fetchDriveOAuthStatus,
  fetchTrelloBoards,
  startAnalyticsOAuth,
  startCalendarOAuth,
  startDriveOAuth,
  updateSource,
  uploadSourceDocument,
} from '../api/sources'
import { isApiConfigured, ApiError } from '../api/client'
import { slugToSourceTab } from '../utils/sourceTabs'
import type { SourceDto, SourceFolderDto, SourceTab } from '../types/sources'

const NIL_SOURCE_ID = '00000000-0000-0000-0000-000000000000'

function isValidSourceId(sourceId?: string | null): sourceId is string {
  return Boolean(
    sourceId &&
      sourceId !== NIL_SOURCE_ID &&
      /^[0-9a-f-]{36}$/i.test(sourceId),
  )
}
import { fetchIntegrationsStatus } from '../api/integrations'
import { useFocusSection } from '../hooks/useFocusSection'
import { ActionNotice } from '../components/ui/ActionNotice'
import { useActionNotice } from '../hooks/useActionNotice'
import { MailboxNewsletterList } from '../components/MailboxNewsletterList'

/** sources 2/3Type+.png — statusrij + FieldSingle placeholders + Submit/Back */
export function SourcesFormPage() {
  const navigate = useNavigate()
  const [searchParams, setSearchParams] = useSearchParams()
  const { categorySlug, sourceId } = useParams<{ categorySlug?: string; sourceId?: string }>()
  const isEdit = Boolean(sourceId)

  const addCategory = categorySlug ? slugToSourceTab(categorySlug) : null
  const [category, setCategory] = useState<SourceTab | null>(addCategory)
  const [form, setForm] = useState<SourceFormValues>(() => emptySourceFormValues())
  const [sourceMeta, setSourceMeta] = useState<SourceDto | null>(null)
  const [loading, setLoading] = useState(isEdit)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const {
    message: actionMessage,
    kind: actionKind,
    fading: actionFading,
    showSuccess: showActionSuccess,
    clear: clearActionNotice,
  } = useActionNotice()

  const [trelloBoards, setTrelloBoards] = useState<Array<{ id: string; name: string; url: string }>>([])
  const [trelloBoardsNote, setTrelloBoardsNote] = useState<string | null>(null)
  const [loadingTrelloBoards, setLoadingTrelloBoards] = useState(false)

  const [analyticsOAuthConfigured, setAnalyticsOAuthConfigured] = useState(true)
  const [analyticsOAuthNote, setAnalyticsOAuthNote] = useState<string | null>(null)

  const [calendarOAuthConfigured, setCalendarOAuthConfigured] = useState(true)
  const [calendarOAuthNote, setCalendarOAuthNote] = useState<string | null>(null)
  const [calendarOptions, setCalendarOptions] = useState<Array<{ id: string; name: string; primary?: boolean }>>([])
  const [calendarOptionsNote, setCalendarOptionsNote] = useState<string | null>(null)
  const [loadingCalendarOptions, setLoadingCalendarOptions] = useState(false)
  const [asanaProjects, setAsanaProjects] = useState<
    Array<{ gid: string; name: string; workspaceGid: string; workspaceName: string }>
  >([])
  const [asanaProjectsNote, setAsanaProjectsNote] = useState<string | null>(null)
  const [loadingAsanaProjects, setLoadingAsanaProjects] = useState(false)
  const [driveOAuthConfigured, setDriveOAuthConfigured] = useState(true)
  const [driveOAuthNote, setDriveOAuthNote] = useState<string | null>(null)
  const [pendingDocumentFile, setPendingDocumentFile] = useState<File | null>(null)
  const [folders, setFolders] = useState<SourceFolderDto[]>([])
  const [integrationStatus, setIntegrationStatus] = useState<{
    status: 'connected' | 'action_required' | 'not_set' | 'unavailable'
    label: string
    note: string | null
  } | null>(null)
  const [newsletterRefreshKey, setNewsletterRefreshKey] = useState(0)

  const activeCategory = category ?? addCategory!

  useFocusSection(sourceId ? [`source-${sourceId}`] : [])

  useEffect(() => {
    if (!isApiConfigured()) return
    if (activeCategory !== 'Analythics' && activeCategory !== 'Calendar') {
      setIntegrationStatus(null)
      return
    }
    void fetchIntegrationsStatus()
      .then((res) => {
        const entry =
          activeCategory === 'Analythics' ? res.integrations.ga4 : res.integrations.calendar
        if (!entry) {
          setIntegrationStatus(null)
          return
        }
        setIntegrationStatus({ status: entry.status, label: entry.label, note: entry.note })
      })
      .catch(() => setIntegrationStatus(null))
  }, [
    activeCategory,
    sourceMeta?.id,
    sourceMeta?.analyticsOAuthConnected,
    sourceMeta?.calendarOAuthConnected,
    sourceMeta?.analyticsPropertyId,
  ])

  const loadCalendarOptions = useCallback(async (id: string) => {
    if (!isApiConfigured()) {
      setCalendarOptionsNote('API niet geconfigureerd.')
      return
    }
    setLoadingCalendarOptions(true)
    setCalendarOptionsNote(null)
    try {
      const data = await fetchCalendarList(id)
      const calendars = Array.isArray(data.calendars) ? data.calendars : []
      setCalendarOptions(
        calendars.map(({ id: calendarId, name, primary }) => ({
          id: calendarId,
          name,
          primary,
        })),
      )
      if (calendars.length === 0) {
        setCalendarOptionsNote(
          data.note ??
            (data.configured
              ? 'Geen agenda\'s gevonden. Schakel Google Calendar API in via Google Cloud Console, of koppel opnieuw via Inloggen met Google.'
              : 'Koppel eerst je Google-account.'),
        )
      } else if (data.note) {
        setCalendarOptionsNote(data.note)
      }
    } catch (err) {
      setCalendarOptionsNote(
        err instanceof ApiError ? err.message : 'Agenda\'s laden mislukt',
      )
    } finally {
      setLoadingCalendarOptions(false)
    }
  }, [])

  useEffect(() => {
    const connected = searchParams.get('connected') === '1'
    const oauthError = searchParams.get('oauth_error')

    if (oauthError) {
      setError(decodeURIComponent(oauthError))
      searchParams.delete('oauth_error')
      setSearchParams(searchParams, { replace: true })
      return
    }

    if (connected && isValidSourceId(sourceId) && isApiConfigured()) {
      void fetchSource(sourceId)
        .then((source) => {
          setSourceMeta(source)
          setCategory(source.category)
          setForm(sourceDtoToFormValues(source))
          setError(null)
          showActionSuccess(
            source.category === 'Calendar'
              ? 'Google Calendar gekoppeld.'
              : source.category === 'Drive'
                ? 'Google Drive gekoppeld.'
                : 'Google Analytics gekoppeld.',
          )
          if (source.calendarOAuthConnected) {
            void loadCalendarOptions(sourceId)
          }
        })
        .catch((err) => {
          setError(err instanceof Error ? err.message : 'Bron laden mislukt na koppeling')
        })
      searchParams.delete('connected')
      setSearchParams(searchParams, { replace: true })
      return
    }

    if (connected) {
      showActionSuccess(
        activeCategory === 'Calendar'
          ? 'Google Calendar gekoppeld.'
          : activeCategory === 'Drive'
            ? 'Google Drive gekoppeld.'
            : 'Google Analytics gekoppeld.',
      )
      searchParams.delete('connected')
      setSearchParams(searchParams, { replace: true })
    }
  }, [searchParams, setSearchParams, sourceId, activeCategory, loadCalendarOptions])

  useEffect(() => {
    if (isEdit || !addCategory) return

    const title = searchParams.get('title')
    const url = searchParams.get('url')
    const description = searchParams.get('description')
    const linkedinProfile = searchParams.get('linkedinProfile')
    if (!title && !url && !description && !linkedinProfile) return

    setForm((current) => ({
      ...current,
      title: title ?? current.title,
      url: url ?? current.url,
      description: description ?? current.description,
      scraperLinkedinProfile: linkedinProfile ?? current.scraperLinkedinProfile,
    }))

    const next = new URLSearchParams(searchParams)
    next.delete('title')
    next.delete('url')
    next.delete('description')
    next.delete('linkedinProfile')
    setSearchParams(next, { replace: true })
  }, [isEdit, addCategory, searchParams, setSearchParams])

  useEffect(() => {
    if (!isEdit || !isValidSourceId(sourceId) || !isApiConfigured()) {
      setLoading(false)
      return
    }

    void fetchSource(sourceId)
      .then((source) => {
        setSourceMeta(source)
        setCategory(source.category)
        setForm(sourceDtoToFormValues(source))
      })
      .catch((err) => {
        setError(err instanceof Error ? err.message : 'Bron laden mislukt')
      })
      .finally(() => setLoading(false))
  }, [isEdit, sourceId])

  useEffect(() => {
    if (!isApiConfigured()) return
    void fetchAllSources()
      .then((data) => setFolders(data.folders ?? []))
      .catch(() => setFolders([]))
  }, [])

  useEffect(() => {
    if (activeCategory !== 'Trello' || !isApiConfigured()) return

    setLoadingTrelloBoards(true)
    void fetchTrelloBoards()
      .then((data) => {
        setTrelloBoards(data.boards)
        setTrelloBoardsNote(data.configured ? null : data.note ?? null)
      })
      .catch(() => setTrelloBoardsNote('Trello boards laden mislukt'))
      .finally(() => setLoadingTrelloBoards(false))
  }, [activeCategory])

  useEffect(() => {
    if (activeCategory !== 'Analythics' || !isApiConfigured()) return

    void fetchAnalyticsOAuthStatus()
      .then((data) => {
        setAnalyticsOAuthConfigured(data.configured)
        setAnalyticsOAuthNote(data.configured ? null : data.note ?? null)
      })
      .catch(() => {
        setAnalyticsOAuthConfigured(false)
        setAnalyticsOAuthNote('Google OAuth status onbekend')
      })
  }, [activeCategory])

  useEffect(() => {
    if (activeCategory !== 'Calendar' || !isApiConfigured()) return

    void fetchCalendarOAuthStatus()
      .then((data) => {
        setCalendarOAuthConfigured(data.configured)
        setCalendarOAuthNote(data.configured ? null : data.note ?? null)
      })
      .catch(() => {
        setCalendarOAuthConfigured(false)
        setCalendarOAuthNote('Google OAuth status onbekend')
      })
  }, [activeCategory])

  useEffect(() => {
    if (activeCategory !== 'Drive' || !isApiConfigured()) return

    void fetchDriveOAuthStatus()
      .then((data) => {
        setDriveOAuthConfigured(data.configured)
        setDriveOAuthNote(data.configured ? null : data.note ?? null)
      })
      .catch(() => {
        setDriveOAuthConfigured(false)
        setDriveOAuthNote('Google OAuth status onbekend')
      })
  }, [activeCategory])

  useEffect(() => {
    if (activeCategory !== 'Calendar' || !sourceId || !isApiConfigured()) return
    if (!sourceMeta?.calendarOAuthConnected) return
    void loadCalendarOptions(sourceId)
  }, [activeCategory, sourceId, sourceMeta?.calendarOAuthConnected, loadCalendarOptions])

  async function ensureSourceId(): Promise<string | null> {
    if (sourceId) return sourceId
    if (!isApiConfigured() || !activeCategory) return null

    const created = await createSource(
      sourceFormValuesToPayload(activeCategory, form) as Parameters<typeof createSource>[0],
    )
    setSourceMeta(created)
    navigate(`/sources/${created.id}/edit`, { replace: true })
    return created.id
  }

  async function handleDiscoverAsana() {
    if (!isApiConfigured()) return
    const pat = form.asanaPatRef.trim()
    if (!pat && !sourceMeta?.asanaPatConfigured) {
      setAsanaProjectsNote('Vul eerst een Asana Personal Access Token in.')
      return
    }

    setLoadingAsanaProjects(true)
    setAsanaProjectsNote(null)
    try {
      if (isEdit && sourceId && pat) {
        await updateSource(sourceId, { asanaPatRef: pat })
      }
      const data = await discoverAsanaProjects(pat, isEdit && sourceId ? sourceId : undefined)
      const projects = Array.isArray(data.projects) ? data.projects : []
      setAsanaProjects(
        projects.map((project) => ({
          gid: String(project.gid),
          name: String(project.name),
          workspaceGid: String(project.workspaceGid ?? ''),
          workspaceName: String(project.workspaceName ?? ''),
        })),
      )
      if (projects.length === 0) {
        setAsanaProjectsNote('Geen projecten gevonden voor dit token.')
      }
    } catch (err) {
      setAsanaProjectsNote(err instanceof Error ? err.message : 'Asana-projecten laden mislukt')
    } finally {
      setLoadingAsanaProjects(false)
    }
  }

  async function handleConnectGoogleDrive() {
    if (!isApiConfigured()) return

    setBusy(true)
    setError(null)
    try {
      const id = await ensureSourceId()
      if (!id) return

      const { category: _category, ...patch } = sourceFormValuesToPayload(activeCategory, form)
      await updateSource(id, patch)

      const oauth = await startDriveOAuth(id)
      if (!oauth.configured || !oauth.authUrl) {
        setDriveOAuthConfigured(false)
        setDriveOAuthNote(oauth.note ?? 'Google OAuth is niet geconfigureerd.')
        return
      }

      window.location.href = oauth.authUrl
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Google Drive-koppeling mislukt')
    } finally {
      setBusy(false)
    }
  }

  async function handleSubmit() {
    if (!category) return
    if (!isApiConfigured()) {
      navigate('/sources')
      return
    }

    setBusy(true)
    setError(null)
    clearActionNotice()
    try {
      const isMailbox = category === 'Mailbox'
      const isDocument = category === 'Document'

      if (isEdit && sourceId) {
        const { category: _category, ...patch } = sourceFormValuesToPayload(category, form)
        const updated = await updateSource(sourceId, patch)

        if (isDocument && pendingDocumentFile) {
          const uploaded = await uploadSourceDocument(sourceId, pendingDocumentFile)
          setSourceMeta(uploaded.source)
          setForm(sourceDtoToFormValues(uploaded.source))
          setPendingDocumentFile(null)
          showActionSuccess(uploaded.message || `Document geüpload (${uploaded.itemCount ?? 0} fragmenten).`)
          navigate('/sources')
          return
        }

        if (isMailbox) {
          const checked = await checkSource(sourceId, {
            mailboxPasswordRef: form.mailboxPasswordRef || undefined,
          })
          setSourceMeta(checked)
          setForm(sourceDtoToFormValues(checked))
          if (checked.checkStatus !== 'green') {
            setError(
              checked.checkMessage ||
                'Mailbox-verbinding mislukt — controleer e-mail en wachtwoord.',
            )
            return
          }
          setNewsletterRefreshKey((key) => key + 1)
          showActionSuccess(checked.checkMessage || 'Mailbox gekoppeld; nieuwsbrieven gesynchroniseerd.')
          return
        }
        setSourceMeta(updated)
        navigate('/sources')
      } else {
        const created = await createSource(
          sourceFormValuesToPayload(category, form) as Parameters<typeof createSource>[0],
        )

        if (isDocument && pendingDocumentFile) {
          const uploaded = await uploadSourceDocument(created.id, pendingDocumentFile)
          setPendingDocumentFile(null)
          if (uploaded.source.checkStatus !== 'green') {
            setError(uploaded.message || 'Document-upload voltooid — controleer de bron.')
            navigate(`/sources/${created.id}/edit`, { replace: true })
            return
          }
        }

        if (isMailbox) {
          const checked = await checkSource(created.id, {
            mailboxPasswordRef: form.mailboxPasswordRef || undefined,
          })
          if (checked.checkStatus !== 'green') {
            setError(
              checked.checkMessage ||
                'Mailbox-verbinding mislukt — controleer e-mail en wachtwoord.',
            )
            navigate(`/sources/${created.id}/edit`, { replace: true })
            return
          }
          showActionSuccess(checked.checkMessage || 'Mailbox gekoppeld; nieuwsbrieven gesynchroniseerd.')
          navigate(`/sources/${created.id}/edit`, { replace: true })
          return
        }
        navigate('/sources')
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Opslaan mislukt')
    } finally {
      setBusy(false)
    }
  }

  async function handleConnectGoogle() {
    if (!isApiConfigured()) return

    setBusy(true)
    setError(null)
    try {
      const id = await ensureSourceId()
      if (!id) return

      const { category: _category, ...patch } = sourceFormValuesToPayload(activeCategory, form)
      await updateSource(id, patch)

      const oauth = await startAnalyticsOAuth(id)
      if (!oauth.configured || !oauth.authUrl) {
        setAnalyticsOAuthConfigured(false)
        setAnalyticsOAuthNote(oauth.note ?? 'Google OAuth is niet geconfigureerd.')
        return
      }

      window.location.href = oauth.authUrl
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Google-koppeling mislukt')
    } finally {
      setBusy(false)
    }
  }

  async function handleConnectGoogleCalendar() {
    if (!isApiConfigured()) return

    setBusy(true)
    setError(null)
    try {
      const id = await ensureSourceId()
      if (!id) return

      const { category: _category, ...patch } = sourceFormValuesToPayload(activeCategory, form)
      await updateSource(id, patch)

      const oauth = await startCalendarOAuth(id)
      if (!oauth.configured || !oauth.authUrl) {
        setCalendarOAuthConfigured(false)
        setCalendarOAuthNote(oauth.note ?? 'Google OAuth is niet geconfigureerd.')
        return
      }

      window.location.href = oauth.authUrl
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Google Calendar-koppeling mislukt')
    } finally {
      setBusy(false)
    }
  }

  async function handleCheck() {
    if (!sourceId || !isApiConfigured()) return
    setBusy(true)
    try {
      const updated = await checkSource(
        sourceId,
        activeCategory === 'Mailbox'
          ? { mailboxPasswordRef: form.mailboxPasswordRef || undefined }
          : undefined,
      )
      setSourceMeta(updated)
      if (activeCategory === 'Mailbox' && updated.checkStatus === 'green') {
        setNewsletterRefreshKey((key) => key + 1)
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Check mislukt')
    } finally {
      setBusy(false)
    }
  }

  async function handleDelete() {
    if (!sourceId || !isApiConfigured()) return

    setBusy(true)
    setError(null)
    try {
      await deleteSource(sourceId)
      navigate('/sources')
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Verwijderen mislukt')
    } finally {
      setBusy(false)
    }
  }

  if (!isEdit && !addCategory) {
    return (
      <GridShell
        pageTitle="Bronnen"
        activeNav="sources"
        main={
          <p className="text-[14px] leading-[18px] text-ce-red">
            Onbekend brontype.{' '}
            <Link to="/sources" className="text-ce-blue underline">
              Terug
            </Link>
          </p>
        }
      />
    )
  }

  const rowTitle =
    form.title.trim() ||
    sourceMeta?.title ||
    defaultSourceRowTitle(activeCategory)

  return (
    <GridShell
      pageTitle="Bronnen"
      activeNav="sources"
      main={
        <>
          {error && (
            <p className="mb-[12px] text-[14px] leading-[18px] text-ce-red">{error}</p>
          )}
          <ActionNotice message={actionMessage} kind={actionKind} fading={actionFading} />

          {loading ? (
            <p className="text-[14px] leading-[18px] text-ce-beige">Laden…</p>
          ) : (
            <>
              <SourceRow
                title={rowTitle}
                checkStatus={sourceMeta?.checkStatus ?? 'none'}
                showBudget={sourceMeta?.showBudget ?? (activeCategory === 'Scraper' || activeCategory === 'Linkedin')}
                busy={busy}
                onCheck={() => void handleCheck()}
                onDelete={isEdit ? () => void handleDelete() : undefined}
              />

              <div className="mt-[12px]" id={sourceId ? `source-${sourceId}` : undefined}>
                <SourceForm
                  category={activeCategory}
                  values={form}
                  onChange={setForm}
                  onSubmit={() => void handleSubmit()}
                  onCancel={() => navigate('/sources')}
                  submitLabel="Submit"
                  busy={busy}
                  trelloBoards={trelloBoards.map(({ id, name }) => ({ id, name }))}
                  loadingTrelloBoards={loadingTrelloBoards}
                  trelloBoardsNote={trelloBoardsNote}
                  analyticsOAuthConnected={sourceMeta?.analyticsOAuthConnected ?? false}
                  analyticsOAuthEmail={sourceMeta?.analyticsOAuthEmail}
                  analyticsOAuthConfigured={analyticsOAuthConfigured}
                  analyticsOAuthNote={analyticsOAuthNote}
                  onConnectGoogle={() => void handleConnectGoogle()}
                  calendarOAuthConnected={sourceMeta?.calendarOAuthConnected ?? false}
                  calendarOAuthEmail={sourceMeta?.calendarOAuthEmail}
                  calendarOAuthConfigured={calendarOAuthConfigured}
                  calendarOAuthNote={calendarOAuthNote}
                  onConnectGoogleCalendar={() => void handleConnectGoogleCalendar()}
                  calendarOptions={calendarOptions}
                  loadingCalendarOptions={loadingCalendarOptions}
                  calendarOptionsNote={calendarOptionsNote}
                  mailboxPasswordConfigured={sourceMeta?.mailboxPasswordConfigured ?? false}
                  scraperTokenConfigured={sourceMeta?.scraperTokenConfigured ?? false}
                  scraperLinkedinPasswordConfigured={
                    sourceMeta?.scraperLinkedinPasswordConfigured ?? false
                  }
                  asanaProjects={asanaProjects}
                  loadingAsanaProjects={loadingAsanaProjects}
                  asanaProjectsNote={asanaProjectsNote}
                  asanaPatConfigured={sourceMeta?.asanaPatConfigured ?? false}
                  onDiscoverAsana={() => void handleDiscoverAsana()}
                  driveOAuthConnected={sourceMeta?.driveOAuthConnected ?? false}
                  driveOAuthEmail={sourceMeta?.driveOAuthEmail}
                  driveOAuthConfigured={driveOAuthConfigured}
                  driveOAuthNote={driveOAuthNote}
                  onConnectGoogleDrive={() => void handleConnectGoogleDrive()}
                  documentFilename={sourceMeta?.documentFilename}
                  documentUploadedAt={sourceMeta?.documentUploadedAt}
                  documentChunkCount={sourceMeta?.documentChunkCount}
                  onDocumentFileChange={setPendingDocumentFile}
                  folders={folders}
                  integrationStatus={integrationStatus?.status}
                  integrationStatusLabel={integrationStatus?.label}
                  integrationStatusNote={integrationStatus?.note}
                />
              </div>

              {isEdit && activeCategory === 'Mailbox' && isValidSourceId(sourceId) ? (
                <div className="mt-[18px]">
                  <MailboxNewsletterList sourceId={sourceId} refreshKey={newsletterRefreshKey} />
                </div>
              ) : null}
            </>
          )}
        </>
      }
    />
  )
}

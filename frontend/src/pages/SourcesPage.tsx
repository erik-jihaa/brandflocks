import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { GridShell } from '../layout/GridShell'
import { LIST_SECTION } from '../layout/grid.constants'
import { SourceTabs } from '../components/SourceTabs'
import { SourceInspirationPanel } from '../components/SourceInspirationPanel'
import { pillLineGapAfterClass, pillLineGapBeforeClass } from '../design/identity.constants'
import { SourceRow } from '../components/SourceRow'
import { SourceFolderHeader } from '../components/SourceFolderHeader'
import { FieldSingle } from '../components/ui/Field'
import { Pulldown } from '../components/ui/Pulldown'
import { PillButton } from '../components/PillButton'
import {
  checkSource,
  createSourceFolder,
  deleteSource,
  deleteSourceFolder,
  fetchAllSources,
  updateSourceFolder,
} from '../api/sources'
import { ApiError, isApiConfigured } from '../api/client'
import { sourcesMock } from '../data/sources.mock'
import type { SourceDto, SourceFolderDto } from '../types/sources'
import { fetchObjectives } from '../api/objectives'
import type { ObjectiveDto } from '../types/objectives'
import { fetchIntegrationsStatus, type IntegrationsStatusResponse } from '../api/integrations'
import { isSourceIntegrationConnected } from '../utils/sourceIntegrations'
import { sourceTabToSlug, slugToSourceTab } from '../utils/sourceTabs'
import type { SourceTab } from '../types/sources'
import { ActionNotice } from '../components/ui/ActionNotice'
import { useActionNotice } from '../hooks/useActionNotice'
import { objectiveDisplayLabel } from '../utils/objectives'

type SourceGroup = {
  key: string
  label: string
  sources: SourceDto[]
  objectiveId?: string | null
  objectiveLabel?: string | null
}

function groupSources(sources: SourceDto[], folders: SourceFolderDto[]): SourceGroup[] {
  const byFolder = new Map<string, SourceDto[]>()
  for (const source of sources) {
    const key = source.folderId ?? '__none__'
    const bucket = byFolder.get(key) ?? []
    bucket.push(source)
    byFolder.set(key, bucket)
  }

  const groups: SourceGroup[] = []
  for (const folder of folders) {
    groups.push({
      key: folder.id,
      label: folder.name,
      sources: byFolder.get(folder.id) ?? [],
      objectiveId: folder.objectiveId ?? null,
      objectiveLabel: folder.objectiveLabel ?? null,
    })
    byFolder.delete(folder.id)
  }

  const uncategorized = byFolder.get('__none__') ?? []
  if (uncategorized.length > 0) {
    groups.push({ key: '__none__', label: 'Overig', sources: uncategorized })
  }

  return groups
}

/** Grid #1 — Sources lijst (sources 1Type+.png) */
export function SourcesPage() {
  const navigate = useNavigate()
  const [searchParams, setSearchParams] = useSearchParams()
  const [sources, setSources] = useState<SourceDto[]>([])
  const [folders, setFolders] = useState<SourceFolderDto[]>([])
  const [loading, setLoading] = useState(true)
  const [usingMock, setUsingMock] = useState(false)
  const [busyId, setBusyId] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)
  const {
    message: actionMessage,
    kind: actionKind,
    fading: actionFading,
    showSuccess: showActionSuccess,
    showError: showActionError,
    clear: clearActionNotice,
  } = useActionNotice()
  const [newFolderName, setNewFolderName] = useState('')
  const [newFolderObjectiveId, setNewFolderObjectiveId] = useState('')
  const [objectives, setObjectives] = useState<ObjectiveDto[]>([])
  const [creatingFolder, setCreatingFolder] = useState(false)
  const [integrationStatus, setIntegrationStatus] = useState<IntegrationsStatusResponse | null>(null)
  const [collapsedFolders, setCollapsedFolders] = useState<Set<string>>(() => new Set())
  const [busyFolderId, setBusyFolderId] = useState<string | null>(null)
  const categoryRedirectRef = useRef<string | null>(null)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)

    if (!isApiConfigured()) {
      setSources(
        sourcesMock.map((row, i) => ({
          id: `mock-${i}`,
          category: row.category,
          title: row.title,
          url: null,
          description: null,
          checkStatus: row.checkStatus,
          showBudget: row.showBudget,
          budgetPerUnit: null,
          maxUnitsPerRun: null,
          active: true,
          lastCheckedAt: null,
        })),
      )
      setFolders([])
      setUsingMock(true)
      setLoading(false)
      return
    }

    try {
      const [data, integrations, objectivesPayload] = await Promise.all([
        fetchAllSources(),
        fetchIntegrationsStatus().catch(() => null),
        fetchObjectives().catch(() => ({ objectives: [] as ObjectiveDto[] })),
      ])
      setSources(data.sources)
      setFolders(data.folders ?? [])
      setObjectives(objectivesPayload.objectives ?? [])
      setIntegrationStatus(integrations)
      setUsingMock(false)
    } catch (err) {
      if (err instanceof ApiError && err.status === 503) {
        setUsingMock(true)
        setSources(
          sourcesMock.map((row, i) => ({
            id: `mock-${i}`,
            category: row.category,
            title: row.title,
            url: null,
            description: null,
            checkStatus: row.checkStatus,
            showBudget: row.showBudget,
            budgetPerUnit: null,
            maxUnitsPerRun: null,
            active: true,
            lastCheckedAt: null,
          })),
        )
        setFolders([])
      } else {
        setError(err instanceof Error ? err.message : 'Laden mislukt')
      }
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  useEffect(() => {
    const oauthError = searchParams.get('oauth_error')
    if (oauthError) {
      setError(decodeURIComponent(oauthError))
      const next = new URLSearchParams(searchParams)
      next.delete('oauth_error')
      setSearchParams(next, { replace: true })
    }
  }, [searchParams, setSearchParams])

  useEffect(() => {
    const categorySlug = searchParams.get('category')
    if (!categorySlug || loading || usingMock) return
    if (categoryRedirectRef.current === categorySlug) return

    const tab = slugToSourceTab(categorySlug)
    const next = new URLSearchParams(searchParams)
    next.delete('category')
    setSearchParams(next, { replace: true })
    categoryRedirectRef.current = categorySlug

    if (!tab) return
    const match = sources.find((source) => source.category === tab)
    if (match) {
      navigate(`/sources/${match.id}/edit`)
      return
    }
    navigate(`/sources/new/${categorySlug}`)
  }, [loading, usingMock, sources, searchParams, setSearchParams, navigate])

  const groupedSources = useMemo(() => groupSources(sources, folders), [sources, folders])

  const objectiveOptions = useMemo(
    () => objectives.map((objective) => objectiveDisplayLabel(objective)),
    [objectives],
  )

  const newFolderObjectiveLabel = useMemo(() => {
    if (!newFolderObjectiveId) return ''
    const match = objectives.find((objective) => objective.id === newFolderObjectiveId)
    return match ? objectiveDisplayLabel(match) : ''
  }, [newFolderObjectiveId, objectives])

  function handleAdd(tab: SourceTab) {
    navigate(`/sources/new/${sourceTabToSlug(tab)}`)
  }

  async function handleCreateFolder() {
    const selectedObjective = objectives.find((objective) => objective.id === newFolderObjectiveId)
    const objectiveTitle = selectedObjective ? objectiveDisplayLabel(selectedObjective) : ''
    const name = newFolderName.trim() || objectiveTitle
    if (!name || usingMock) return
    setCreatingFolder(true)
    setError(null)
    try {
      await createSourceFolder(name, newFolderObjectiveId || null)
      setNewFolderName('')
      setNewFolderObjectiveId('')
      await load()
      showActionSuccess(
        newFolderObjectiveId
          ? `Map "${name}" gekoppeld aan doel "${objectiveTitle}". Research start hier, daarna andere bronnen.`
          : `Map "${name}" aangemaakt.`,
      )
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Map aanmaken mislukt')
    } finally {
      setCreatingFolder(false)
    }
  }

  function toggleFolderCollapsed(folderKey: string) {
    setCollapsedFolders((current) => {
      const next = new Set(current)
      if (next.has(folderKey)) {
        next.delete(folderKey)
      } else {
        next.add(folderKey)
      }
      return next
    })
  }

  async function handleRenameFolder(
    folderId: string,
    name: string,
    objectiveId?: string | null,
  ) {
    if (usingMock) return
    setBusyFolderId(folderId)
    setError(null)
    clearActionNotice()
    try {
      await updateSourceFolder(folderId, {
        name,
        ...(objectiveId !== undefined ? { objectiveId } : {}),
      })
      await load()
      showActionSuccess(`Map hernoemd naar "${name}".`)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Map hernoemen mislukt')
    } finally {
      setBusyFolderId(null)
    }
  }

  async function handleDeleteFolder(folderId: string, sourceCount: number, label: string) {
    if (usingMock) return
    setBusyFolderId(folderId)
    setError(null)
    clearActionNotice()
    try {
      await deleteSourceFolder(folderId)
      setCollapsedFolders((current) => {
        const next = new Set(current)
        next.delete(folderId)
        return next
      })
      await load()
      if (sourceCount > 0) {
        showActionSuccess(
          `Map "${label}" verwijderd. ${sourceCount} source${sourceCount === 1 ? '' : 's'} staan nu onder Overig.`,
        )
      } else {
        showActionSuccess(`Map "${label}" verwijderd.`)
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Map verwijderen mislukt')
    } finally {
      setBusyFolderId(null)
    }
  }

  async function handleCheck(sourceId: string) {
    if (usingMock) return
    setBusyId(sourceId)
    setError(null)
    clearActionNotice()
    try {
      const checked = await checkSource(sourceId)
      await load()
      if (checked.checkMessage) {
        if (checked.checkStatus === 'green') {
          showActionSuccess(checked.checkMessage)
        } else {
          showActionError(checked.checkMessage)
        }
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Check mislukt')
    } finally {
      setBusyId(null)
    }
  }

  async function handleDelete(sourceId: string, category?: SourceDto['category']) {
    if (usingMock) {
      setError('Demo-sources — log in om sources te verwijderen.')
      return
    }

    setBusyId(sourceId)
    setError(null)
    clearActionNotice()
    try {
      await deleteSource(sourceId)
      await load()
      if (category === 'Scraper' || category === 'Linkedin') {
        showActionSuccess('Bron verwijderd. Scrape-geschiedenis blijft in scrape_runs staan.')
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Verwijderen mislukt')
    } finally {
      setBusyId(null)
    }
  }

  const isEmpty = !loading && sources.length === 0 && !usingMock

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
          {!usingMock && sources.length > 0 && !sources.some((source) => source.sourceTier === 'core') ? (
            <p className="mb-[12px] text-[14px] leading-[18px] text-ce-black">
              Voeg minstens één kern-bron toe (eigen website of document). Zonder eigen materiaal mist de tool jullie perspectief.
            </p>
          ) : null}

          <div className={`${pillLineGapBeforeClass} ${pillLineGapAfterClass}`}>
            <div className="flex flex-wrap items-start gap-[12px]">
              <SourceTabs onAdd={handleAdd} />
              {!usingMock && <SourceInspirationPanel disabled={loading} />}
            </div>
          </div>

          {!usingMock && (
            <div className="mb-[20px]">
              <div className="grid grid-cols-1 items-end gap-[12px] md:grid-cols-[minmax(220px,1fr)_minmax(220px,1fr)_auto]">
                {objectives.length > 0 ? (
                  <Pulldown
                    placeholder="Doel"
                    value={newFolderObjectiveLabel}
                    options={['Geen doel', ...objectiveOptions]}
                    onChange={(label) => {
                      if (label === 'Geen doel') {
                        setNewFolderObjectiveId('')
                        return
                      }
                      const match = objectives.find(
                        (objective) => objectiveDisplayLabel(objective) === label,
                      )
                      const nextId = match?.id ?? ''
                      setNewFolderObjectiveId(nextId)
                      if (match) {
                        setNewFolderName(objectiveDisplayLabel(match))
                      }
                    }}
                  />
                ) : (
                  <div />
                )}
                <FieldSingle
                  className="min-w-0"
                  placeholder={objectives.length > 0 ? 'Mapnaam (vult automatisch bij doel)' : 'Nieuwe map'}
                  value={newFolderName}
                  onChange={setNewFolderName}
                  aria-label="Mapnaam"
                />
                <PillButton
                  variant="second"
                  className="!mb-0 shrink-0"
                  disabled={
                    creatingFolder || (!newFolderName.trim() && !newFolderObjectiveId)
                  }
                  onClick={() => void handleCreateFolder()}
                >
                  Map toevoegen
                </PillButton>
              </div>
              {objectives.length > 0 ? (
                <p className="m-0 mt-[8px] text-[14px] leading-[18px] text-ce-beige">
                  Kies een doel als titel (bv. Promotie MailFlow). De mapnaam volgt automatisch; research start in deze map, daarna andere bronnen.
                </p>
              ) : null}
            </div>
          )}

          {loading ? (
            <p className="text-[14px] leading-[18px] text-ce-beige">Laden…</p>
          ) : isEmpty ? (
            <p className="m-0 text-[14px] leading-[18px] font-semibold text-ce-black">
              Start met je eerste source
            </p>
          ) : (
            <div>
              {groupedSources.map((group) => {
                const isVirtualGroup = group.key === '__none__'
                const collapsed = collapsedFolders.has(group.key)
                const manageable = !usingMock && !isVirtualGroup

                return (
                  <div key={group.key} className="mb-[24px]">
                    {isVirtualGroup && groupedSources.length === 1 ? null : (
                      <SourceFolderHeader
                        label={group.label}
                        sourceCount={group.sources.length}
                        objectiveLabel={group.objectiveLabel}
                        collapsed={collapsed}
                        onToggle={() => toggleFolderCollapsed(group.key)}
                        manageable={manageable}
                        busy={busyFolderId === group.key}
                        objectiveOptions={
                          objectives.length > 0
                            ? objectives.map((objective) => ({
                                id: objective.id,
                                label: objectiveDisplayLabel(objective),
                              }))
                            : undefined
                        }
                        selectedObjectiveId={group.objectiveId ?? null}
                        onRename={
                          manageable
                            ? (name, objectiveId) =>
                                handleRenameFolder(group.key, name, objectiveId)
                            : undefined
                        }
                        onDelete={
                          manageable
                            ? () =>
                                void handleDeleteFolder(
                                  group.key,
                                  group.sources.length,
                                  group.label,
                                )
                            : undefined
                        }
                      />
                    )}
                    {!collapsed ? (
                      group.sources.length === 0 ? (
                        manageable ? (
                          <p
                            className={`m-0 mb-[8px] text-[14px] leading-[18px] text-ce-beige ${LIST_SECTION.sourcesFolderChildIndentClass}`}
                          >
                            Geen sources in deze map.
                          </p>
                        ) : null
                      ) : (
                        group.sources.map((row) => (
                          <SourceRow
                            key={row.id}
                            title={row.title}
                            inFolder={!isVirtualGroup}
                            checkStatus={row.checkStatus}
                            showBudget={row.showBudget}
                            busy={busyId === row.id}
                            dimmed={
                              !usingMock &&
                              !isSourceIntegrationConnected(
                                row.category,
                                integrationStatus?.integrations,
                              )
                            }
                            onEdit={() => navigate(`/sources/${row.id}/edit`)}
                            onCheck={() => void handleCheck(row.id)}
                            onBudget={() => navigate(`/sources/${row.id}/edit`)}
                            onDelete={() => void handleDelete(row.id, row.category)}
                          />
                        ))
                      )
                    ) : null}
                  </div>
                )
              })}
            </div>
          )}
        </>
      }
    />
  )
}

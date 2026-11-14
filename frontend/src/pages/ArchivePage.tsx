import { useCallback, useEffect, useMemo, useState } from 'react'
import { GridShell } from '../layout/GridShell'
import { LIST_SECTION } from '../layout/grid.constants'
import { ArchiveRow } from '../components/StatusRow'
import { ArchiveSearchPanel } from '../components/ArchiveSearchPanel'
import { ArchiveSearchResultRow } from '../components/ArchiveSearchResultRow'
import { PillButton } from '../components/PillButton'
import { fetchArchive, type ArchiveItemDto, type ArchiveReportDto } from '../api/archive'
import { isApiConfigured } from '../api/client'
import { archiveMock, archiveReportsMock, groupArchiveByWeek } from '../data/archive.mock'
import {
  filterArchiveItems,
  filterArchiveReports,
  type ArchiveSearchType,
} from '../utils/archiveSearch'
import { reportTitleForKlant } from '../utils/reportTitleKlant'

/** Archive — briefing/archive.png + archive searchType+.png */
export function ArchivePage() {
  const [items, setItems] = useState<ArchiveItemDto[]>([])
  const [reports, setReports] = useState<ArchiveReportDto[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [usingMock, setUsingMock] = useState(false)
  const [searchMode, setSearchMode] = useState(false)
  const [searchType, setSearchType] = useState<ArchiveSearchType | null>(null)
  const [draftQuery, setDraftQuery] = useState('')
  const [appliedQuery, setAppliedQuery] = useState('')
  const [searchSubmitted, setSearchSubmitted] = useState(false)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)

    if (!isApiConfigured()) {
      setItems(archiveMock)
      setReports(archiveReportsMock)
      setUsingMock(true)
      setLoading(false)
      return
    }

    try {
      const data = await fetchArchive()
      setItems(data.items)
      setReports(data.reports)
      setUsingMock(false)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to load')
      setItems(archiveMock)
      setReports(archiveReportsMock)
      setUsingMock(true)
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  const articleResults = useMemo(
    () => (searchSubmitted && searchType === 'article' ? filterArchiveItems(items, appliedQuery) : []),
    [items, searchSubmitted, searchType, appliedQuery],
  )
  const reportResults = useMemo(
    () => (searchSubmitted && searchType === 'report' ? filterArchiveReports(reports, appliedQuery) : []),
    [reports, searchSubmitted, searchType, appliedQuery],
  )
  const groups = useMemo(() => groupArchiveByWeek(items), [items])

  function enterSearchMode() {
    setSearchMode(true)
  }

  function exitSearchMode() {
    setSearchMode(false)
    setSearchType(null)
    setDraftQuery('')
    setAppliedQuery('')
    setSearchSubmitted(false)
  }

  function submitSearch() {
    if (!searchType) return
    setAppliedQuery(draftQuery)
    setSearchSubmitted(true)
  }

  if (loading) {
    return <GridShell pageTitle="Alle artikelen" activeNav="archive" main={<p>Laden…</p>} />
  }

  const resultCount = searchType === 'article' ? articleResults.length : reportResults.length
  const emptySearchMessage =
    searchType === 'article' ? 'No articles match your search' : 'No reports match your search'

  return (
    <GridShell
      pageTitle="Alle artikelen"
      pageSubtitle={searchMode ? 'Search' : 'Scroll or search'}
      activeNav="archive"
      main={
        <>
          {usingMock && (
            <p className="mb-[12px] text-[14px] leading-[18px] text-ce-beige">
              Mock archive — configure DB + API for live data.
            </p>
          )}
          {error && <p className="mb-[12px] text-[14px] leading-[18px] text-ce-red">{error}</p>}

          {searchMode ? (
            <>
              <ArchiveSearchPanel
                searchType={searchType}
                draftQuery={draftQuery}
                onSearchTypeChange={(type) => {
                  setSearchType(type)
                  setSearchSubmitted(false)
                }}
                onDraftQueryChange={setDraftQuery}
                onSearch={submitSearch}
                onBack={exitSearchMode}
              />

              {searchSubmitted && (
                <section className="pt-[10px]">
                  <div className={`${LIST_SECTION.bodyClass} border-t border-ce-beige`}>
                    {resultCount === 0 ? (
                      <p className="m-0 text-[14px] leading-[18px] text-ce-beige">{emptySearchMessage}</p>
                    ) : (
                      <div className={LIST_SECTION.bodyStackClass}>
                        {searchType === 'article' &&
                          articleResults.map((row) => (
                            <ArchiveSearchResultRow
                              key={row.slotId}
                              title={row.title}
                              date={row.publishedAt}
                              actionLabel="Artikel bekijken"
                              actionTo={`/archive/${row.slotId}`}
                            />
                          ))}
                        {searchType === 'report' &&
                          reportResults.map((report) => (
                            <ArchiveSearchResultRow
                              key={report.reportId}
                              title={reportTitleForKlant(report.title)}
                              date={report.generatedAt}
                              actionLabel="Rapport bekijken"
                              actionTo="/report"
                            />
                          ))}
                      </div>
                    )}
                  </div>
                </section>
              )}
            </>
          ) : (
            <>
              <div className="mb-[20px]">
                <PillButton variant="second" onClick={enterSearchMode}>
                  Search
                </PillButton>
              </div>

              {groups.length === 0 ? (
                <p className="m-0 pt-[20px] text-[14px] leading-[18px] text-ce-beige">
                  No archived posts at the moment
                </p>
              ) : (
                groups.map((group) => (
                  <section key={group.week} className={LIST_SECTION.sectionClass}>
                    <h2 className={`${LIST_SECTION.headerClass} text-ce-black`}>{group.week}</h2>
                    <div className={LIST_SECTION.bodyClass}>
                      <div className={LIST_SECTION.bodyStackClass}>
                        {group.rows.map((row) => (
                          <ArchiveRow key={row.slotId} slotId={row.slotId} title={row.title} />
                        ))}
                      </div>
                    </div>
                  </section>
                ))
              )}
            </>
          )}
        </>
      }
    />
  )
}

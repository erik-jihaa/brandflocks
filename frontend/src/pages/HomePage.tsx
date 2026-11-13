import { useEffect, useMemo, useState } from 'react'
import {
  emptyHomeDashboard,
  fetchHomeDashboardCached,
  getHomeDashboardCache,
  type HomeDashboard,
} from '../api/home'
import { fetchHbaProgress, type HbaProgress } from '../api/hbaV2'
import { ApiError, isApiConfigured } from '../api/client'
import { HomeTodoRow } from '../components/StatusRow'
import { LIST_SECTION } from '../layout/grid.constants'
import { GridShell } from '../layout/GridShell'
import { SignalBlock } from '../components/ui/SignalBlock'
import { HomeStatusMeters } from '../components/HomeStatusMeters'
import { HomeBuildProgress } from '../components/HomeBuildProgress'
import { HomeReturnSummary } from '../components/HomeReturnSummary'
import { HomePendingConnections } from '../components/HomePendingConnections'
import type { HomeOverviewSignal } from '../api/home'
import {
  buildHomeOverviewMeters,
  homeOverviewEarlyBuild,
} from '../utils/homeOverviewMeters'

/** Actielabel voor signaalblok — API-label (Agenda) of pad-afleiding. */
function overviewSignalActionLabel(signal: HomeOverviewSignal): string | undefined {
  const fromApi = signal.actionLabel?.trim()
  if (fromApi) {
    if (fromApi === 'Report' || fromApi === 'Rapport') return 'Naar rapport'
    if (fromApi === 'Theme') return 'Bekijk thema’s'
    if (fromApi === 'Merkarchitectuur') return 'Open merkarchitectuur'
    return fromApi
  }
  const path = signal.actionPath
  if (path.startsWith('/theme')) return 'Bekijk thema’s'
  if (path.startsWith('/report')) return 'Naar rapport'
  if (path.startsWith('/agenda')) return 'Bekijk agenda'
  if (path.startsWith('/brandarchitecture')) return 'Open merkarchitectuur'
  return 'Bekijken'
}

/** Overview — meters + wat op je wacht (notitie 15) */
export function HomePage() {
  const apiLive = isApiConfigured()
  const cached = apiLive ? getHomeDashboardCache() : null
  const [data, setData] = useState<HomeDashboard | null>(cached)
  const [loading, setLoading] = useState(apiLive && !cached)
  const [error, setError] = useState<string | null>(null)
  const [loadFailed, setLoadFailed] = useState(false)
  const [hbaProgress, setHbaProgress] = useState<HbaProgress | null>(null)

  useEffect(() => {
    if (!apiLive) return
    void fetchHbaProgress().then(setHbaProgress).catch(() => setHbaProgress(null))
  }, [apiLive])

  useEffect(() => {
    if (!apiLive) {
      setLoading(false)
      return
    }

    void fetchHomeDashboardCached({ force: true })
      .then((dashboard) => {
        setData(dashboard)
        setLoadFailed(false)
        setError(null)
      })
      .catch((err) => {
        if (err instanceof ApiError && err.status === 503) {
          setError('Database niet geconfigureerd — overzicht niet beschikbaar.')
        } else {
          setError(err instanceof Error ? err.message : 'Laden mislukt')
        }
        if (!getHomeDashboardCache()) {
          setData(emptyHomeDashboard())
          setLoadFailed(true)
        }
      })
      .finally(() => setLoading(false))
  }, [apiLive])

  const dashboard = data ?? emptyHomeDashboard()
  const todoItems = dashboard.todos ?? []
  const overviewSignal = dashboard.overviewSignal ?? null
  const pendingConnections = dashboard.pendingConnections ?? []
  const earlyBuild = homeOverviewEarlyBuild(hbaProgress)
  const meters = useMemo(
    () => buildHomeOverviewMeters(hbaProgress, dashboard.result),
    [hbaProgress, dashboard.result],
  )

  if (loading && !data) {
    return <GridShell pageTitle="Te doen" activeNav="home" main={<p className="text-[14px] text-ce-black">Laden…</p>} />
  }

  return (
    <GridShell
      pageTitle="Te doen"
      activeNav="home"
      main={
        <>
          {error && (
            <p className="mb-[12px] text-[14px] leading-[18px] text-ce-red">{error}</p>
          )}
          {!apiLive && (
            <p className="mb-[12px] text-[14px] leading-[18px] text-ce-black">
              Configureer de API om live overzichtsdata te zien.
            </p>
          )}

          {dashboard.returnSummary?.message ? (
            <HomeReturnSummary
              message={dashboard.returnSummary.message}
              reportPath={dashboard.returnSummary.reportPath}
            />
          ) : null}

          {overviewSignal?.message ? (
            <SignalBlock
              problem={overviewSignal.message}
              actionLabel={overviewSignalActionLabel(overviewSignal)}
              actionPath={overviewSignal.actionPath}
            />
          ) : null}

          {earlyBuild && hbaProgress?.ok ? (
            <HomeBuildProgress progress={hbaProgress} />
          ) : null}

          {!loadFailed && meters.length > 0 ? <HomeStatusMeters meters={meters} /> : null}

          <section className={`min-w-0 ${LIST_SECTION.dashboardSectionClass}`}>
            <h2 className={`${LIST_SECTION.headerClass} text-ce-black`}>Wat op je wacht</h2>
            {todoItems.length > 0 ? (
              <div className={`flex flex-col gap-[12px] ${LIST_SECTION.listTopClass}`}>
                {todoItems.map((item) => (
                  <HomeTodoRow
                    key={item.id}
                    label={item.label}
                    reason={item.reason}
                    path={item.path}
                    blocking={item.blocking}
                  />
                ))}
              </div>
            ) : loadFailed ? (
              <p className={`m-0 text-[14px] leading-[18px] text-ce-black ${LIST_SECTION.listTopClass}`}>
                Overzicht kon niet geladen worden. Probeer de pagina te verversen.
              </p>
            ) : (
              <p className={`m-0 text-[14px] leading-[18px] text-ce-black ${LIST_SECTION.listTopClass}`}>
                Geen open taken — alles loopt op schema.
              </p>
            )}
          </section>

          <HomePendingConnections items={pendingConnections} />
        </>
      }
    />
  )
}

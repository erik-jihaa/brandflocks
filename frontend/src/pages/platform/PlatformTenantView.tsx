import { useCallback, useEffect, useState } from 'react'
import { useLocation, useParams } from 'react-router-dom'
import { LIST_ROW, LIST_SECTION } from '../../layout/grid.constants'
import { SaveNotice } from '../../components/ui/SaveNotice'
import { useSaveNotice } from '../../hooks/useSaveNotice'
import { usePlatformData } from '../../components/platform/PlatformDataContext'
import { PlatformActionsPulldown } from '../../components/platform/PlatformActionsPulldown'
import { PlatformIntegrationsPanel } from '../../components/platform/PlatformIntegrationsPanel'
import { PlatformActionItemsSection } from '../../components/platform/PlatformActionItemsSection'
import { PlatformDiagnosticRow } from '../../components/platform/PlatformDiagnosticRow'
import { PlatformEventLogSection } from '../../components/platform/PlatformEventLogSection'
import { PlatformServiceSection } from '../../components/platform/PlatformServiceSection'
import { PlatformTenantDeleteSection } from '../../components/platform/PlatformTenantDeleteSection'
import { PlatformStatusDot, platformStatusLabel } from '../../components/platform/platformStatus'
import { formatCount, formatDaysAgo, formatEur } from '../../components/platform/platformFormat'
import {
  PLATFORM_ACTION_ITEMS_ANCHOR,
  fetchPlatformSession,
  fetchPlatformTenant,
  fetchPlatformCmsDiagnose,
  fetchPlatformIntegrationsStatus,
  runPlatformMailboxSync,
  runPlatformScraperSync,
  runPlatformThemeRefill,
  type PlatformTenantDetail,
  type PlatformUser,
  type PlatformCmsDiagnose,
} from '../../api/platform'
import type { IntegrationsStatusResponse } from '../../api/integrations'
import { collectTenantActionItems } from '../../utils/platformActionItems'

export function PlatformTenantView() {
  const { clientId } = useParams<{ clientId: string }>()
  const location = useLocation()
  const { reload: reloadOverview, status } = usePlatformData()
  const { message, kind, fading, showSaving, showSuccess, showError } = useSaveNotice()

  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [detail, setDetail] = useState<PlatformTenantDetail | null>(null)
  const [platformUser, setPlatformUser] = useState<PlatformUser | null>(null)
  const [cmsDiagnose, setCmsDiagnose] = useState<PlatformCmsDiagnose | null>(null)
  const [integrationsStatus, setIntegrationsStatus] = useState<IntegrationsStatusResponse | null>(null)
  const [cmsLoading, setCmsLoading] = useState(false)
  const [busyAction, setBusyAction] = useState<string | null>(null)

  const loadDetail = useCallback(() => {
    if (!clientId) return
    setLoading(true)
    setError(null)
    void Promise.all([
      fetchPlatformTenant(clientId),
      fetchPlatformSession(),
      fetchPlatformIntegrationsStatus(clientId),
    ])
      .then(([tenantRes, sessionRes, integrationsRes]) => {
        setDetail(tenantRes)
        setPlatformUser(sessionRes.user)
        setIntegrationsStatus(integrationsRes)
      })
      .catch((err) => {
        setError(err instanceof Error ? err.message : 'Laden mislukt')
      })
      .finally(() => setLoading(false))
  }, [clientId])

  useEffect(() => {
    loadDetail()
    setCmsDiagnose(null)
    setIntegrationsStatus(null)
  }, [loadDetail])

  useEffect(() => {
    if (loading || location.hash !== `#${PLATFORM_ACTION_ITEMS_ANCHOR}`) return
    const frame = window.requestAnimationFrame(() => {
      document.getElementById(PLATFORM_ACTION_ITEMS_ANCHOR)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
    })
    return () => window.cancelAnimationFrame(frame)
  }, [loading, location.hash, detail])

  const tenant = detail?.tenant ?? null
  const diagnostics = detail?.diagnostics ?? null
  const tenantEvents = detail?.recentEvents ?? []
  const isAdmin = platformUser?.role === 'admin'
  const actionItems = tenant
    ? collectTenantActionItems(tenant, tenantEvents, integrationsStatus?.integrations ?? null)
    : []

  async function refreshAfterPlatformAction() {
    loadDetail()
    reloadOverview()
  }

  const platformActionOptions = [
    {
      id: 'cms-diagnose',
      label: 'CMS-diagnose',
      description: 'Test de website-koppeling (WordPress, Joomla of FTP) met de opgeslagen credentials.',
    },
    ...(isAdmin
      ? [
          {
            id: 'theme-refill',
            label: 'Theme-refill',
            description:
              'Vervang placeholder-thema\'s in de planning-horizon en start CE-01 opnieuw voor deze tenant.',
          },
        ]
      : []),
    {
      id: 'mailbox-sync',
      label: 'Mailbox-sync',
      description: 'Haal nieuwsbrieven op via IMAP voor alle mailbox-sources en werk de bronstatus bij.',
    },
    {
      id: 'scraper-sync',
      label: 'Scraper-sync',
      description: 'Draai scraper- en LinkedIn-sources opnieuw en werk de bereikbaarheid bij.',
    },
  ]

  function handlePlatformAction(actionId: string) {
    switch (actionId) {
      case 'cms-diagnose':
        void handleCmsDiagnose()
        break
      case 'theme-refill':
        void handleThemeRefill()
        break
      case 'mailbox-sync':
        void handleMailboxSync()
        break
      case 'scraper-sync':
        void handleScraperSync()
        break
    }
  }

  async function handleCmsDiagnose() {
    if (!clientId) return
    setCmsLoading(true)
    showSaving('CMS-diagnose…')
    try {
      const result = await fetchPlatformCmsDiagnose(clientId)
      setCmsDiagnose(result)
      if (result.healthy) {
        showSuccess(`${result.cmsLabel ?? 'CMS'} OK`)
      } else {
        showError(result.authError ?? 'CMS-diagnose mislukt')
      }
      await refreshAfterPlatformAction()
    } catch (err) {
      showError(err instanceof Error ? err.message : 'CMS-diagnose mislukt')
    } finally {
      setCmsLoading(false)
    }
  }

  async function handleThemeRefill() {
    if (!clientId || !isAdmin) return
    if (
      !window.confirm(
        'Theme-refill draaien voor de planning-horizon van deze tenant? Bestaande placeholder-thema\'s worden vervangen.',
      )
    ) {
      return
    }
    setBusyAction('theme-refill')
    showSaving('Theme-refill…')
    try {
      const result = await runPlatformThemeRefill(clientId)
      if (result.ok) {
        showSuccess(`Theme-refill gestart (${result.weeks.length} week/weken)`)
        loadDetail()
        reloadOverview()
      } else {
        showError('Theme-refill mislukt')
      }
    } catch (err) {
      showError(err instanceof Error ? err.message : 'Theme-refill mislukt')
    } finally {
      setBusyAction(null)
    }
  }

  async function handleMailboxSync() {
    if (!clientId) return
    setBusyAction('mailbox-sync')
    showSaving('Mailbox-sync…')
    try {
      const result = await runPlatformMailboxSync(clientId)
      if (result.ok) {
        showSuccess(`Mailbox-sync klaar (${result.results.length} bron/bronnen)`)
      } else {
        showError('Mailbox-sync deels mislukt')
      }
      await refreshAfterPlatformAction()
    } catch (err) {
      showError(err instanceof Error ? err.message : 'Mailbox-sync mislukt')
    } finally {
      setBusyAction(null)
    }
  }

  async function handleScraperSync() {
    if (!clientId) return
    setBusyAction('scraper-sync')
    showSaving('Scraper-sync…')
    try {
      const result = await runPlatformScraperSync(clientId)
      if (result.ok) {
        showSuccess(`Scraper-sync klaar (${result.results.length} bron/bronnen)`)
      } else {
        showError('Scraper-sync deels mislukt')
      }
      await refreshAfterPlatformAction()
    } catch (err) {
      showError(err instanceof Error ? err.message : 'Scraper-sync mislukt')
    } finally {
      setBusyAction(null)
    }
  }

  if (loading) {
    return <p className="m-0 text-[14px] leading-[18px] text-ce-beige">Laden…</p>
  }
  if (error) {
    return <p className="m-0 text-[14px] leading-[18px] text-ce-red">{error}</p>
  }
  if (!tenant || !diagnostics || !detail) {
    return (
      <p className="m-0 text-[14px] leading-[18px] text-ce-beige">
        Tenant niet gevonden. Kies een tenant in de navigatie.
      </p>
    )
  }

  return (
    <div className={LIST_SECTION.bodyStackClass}>
      <section className={LIST_SECTION.bodyClass}>
        <div className="flex items-center gap-[10px]">
          <PlatformStatusDot status={tenant.status} title={platformStatusLabel(tenant.status)} />
          <h2 className={`m-0 ${LIST_SECTION.headerClass} text-ce-blue`}>{tenant.company}</h2>
        </div>
        <p className="m-0 mt-[8px] text-[14px] leading-[18px] text-ce-beige">{tenant.contactEmail}</p>
        <p className="m-0 mt-[4px] text-[14px] leading-[18px] text-ce-black">
          {platformStatusLabel(tenant.status)}
        </p>
      </section>

      <PlatformActionItemsSection
        items={actionItems}
        showTenant={false}
        emptyLabel="Geen open actiepunten voor deze tenant"
      />

      <section className={LIST_SECTION.bodyClass}>
        <h3 className={`m-0 ${LIST_SECTION.headerClass} text-ce-black`}>Acties</h3>
        <div className="mt-[20px]">
          <PlatformActionsPulldown
            options={platformActionOptions}
            onSelect={handlePlatformAction}
            disabled={cmsLoading || busyAction !== null}
          />
        </div>
        <SaveNotice message={message} kind={kind} fading={fading} />

        {cmsDiagnose && (
          <div className="mt-[18px] border-t border-ce-black/10 pt-[18px] text-[14px] leading-[18px]">
            <div className={`${LIST_ROW.rowClass} text-ce-black`}>
              <span className="font-semibold">{cmsDiagnose.cmsLabel ?? 'CMS'}-resultaat</span>
              <span
                className={`${LIST_ROW.actionsClass} font-semibold ${
                  cmsDiagnose.healthy ? 'text-ce-green-text' : 'text-ce-red'
                }`}
              >
                {cmsDiagnose.healthy ? 'OK' : 'Probleem'}
              </span>
            </div>
            {cmsDiagnose.authenticatedAs?.name && (
              <p className="m-0 mt-[8px] text-ce-beige">
                Ingelogd als {cmsDiagnose.authenticatedAs.name}
                {cmsDiagnose.resolvedPostAuthorId != null
                  ? ` · author ID ${cmsDiagnose.resolvedPostAuthorId}`
                  : ''}
              </p>
            )}
            {cmsDiagnose.authError && (
              <p className="m-0 mt-[8px] text-ce-red">{cmsDiagnose.authError}</p>
            )}
            {cmsDiagnose.usingPlatformFallback && (
              <p className="m-0 mt-[8px] text-ce-beige">Gebruikt platform-fallback credentials</p>
            )}
          </div>
        )}
      </section>

      <section className={LIST_SECTION.bodyClass}>
        <h3 className={`m-0 ${LIST_SECTION.headerClass} text-ce-black`}>Gebruik</h3>
        <dl className="m-0 mt-[20px] grid gap-[10px] text-[14px] leading-[18px]">
          <div className="flex justify-between gap-[12px] border-b border-ce-black/10 pb-[10px]">
            <dt className="text-ce-black">Modus</dt>
            <dd className="m-0 font-semibold">{tenant.publishMode || '—'}</dd>
          </div>
          <div className="flex justify-between gap-[12px] border-b border-ce-black/10 pb-[10px]">
            <dt className="text-ce-black">Laatste login</dt>
            <dd className="m-0 font-semibold">{formatDaysAgo(tenant.daysSinceLastLogin)}</dd>
          </div>
          <div className="flex justify-between gap-[12px] border-b border-ce-black/10 pb-[10px]">
            <dt className="text-ce-black">Laatste publicatie</dt>
            <dd className="m-0 font-semibold">{formatDaysAgo(tenant.daysSinceLastPublish)}</dd>
          </div>
          <div className="flex justify-between gap-[12px] border-b border-ce-black/10 pb-[10px]">
            <dt className="text-ce-black">Artikelen deze maand</dt>
            <dd className="m-0 font-semibold">
              {tenant.articlesThisMonth}/{tenant.expectedArticlesMonth}
            </dd>
          </div>
          <div className="border-b border-ce-black/10 pb-[10px]">
            <div className="flex justify-between gap-[12px]">
              <dt className="text-ce-black">Fouten (7d)</dt>
              <dd className={`m-0 font-semibold ${tenant.errors7d > 0 ? 'text-ce-red' : ''}`}>
                {tenant.errors7d}
              </dd>
            </div>
            {tenant.errors7d === 0 ? (
              <p className="m-0 mt-[6px] text-[14px] leading-[18px] text-ce-green-text">
                Geen technische fouten in het logboek.
              </p>
            ) : (
              <p className="m-0 mt-[6px] text-[14px] leading-[18px] text-ce-beige">
                Technische fouten uit het logboek — sync, publiceren of koppelingen.
              </p>
            )}
          </div>
          <div className="flex justify-between gap-[12px] border-b border-ce-black/10 pb-[10px]">
            <dt className="text-ce-black">n8n executions deze maand</dt>
            <dd className="m-0 font-semibold">{formatCount(tenant.n8nExecutionsThisMonth)}</dd>
          </div>
          <div className="flex justify-between gap-[12px]">
            <dt className="text-ce-black">Kosten deze maand</dt>
            <dd className="m-0 font-semibold">{formatEur(tenant.costEurThisMonth)}</dd>
          </div>
        </dl>
      </section>

      <section className={LIST_SECTION.bodyClass}>
        <h3 className={`m-0 ${LIST_SECTION.headerClass} text-ce-black`}>Koppelingen</h3>
        <div className="m-0 mt-[20px]">
          {integrationsStatus ? (
            <PlatformIntegrationsPanel
              integrations={integrationsStatus.integrations}
              summary={integrationsStatus.summary}
            />
          ) : (
            <p className="m-0 text-[14px] leading-[18px] text-ce-beige">Koppelingen laden…</p>
          )}
        </div>
      </section>

      <section className={LIST_SECTION.bodyClass}>
        <h3 className={`m-0 ${LIST_SECTION.headerClass} text-ce-black`}>Configuratie</h3>
        <div className="m-0 mt-[20px] grid gap-[10px]">
          <PlatformDiagnosticRow
            label="BrandElements"
            value={diagnostics.styleGuideReady ? 'Klaar' : 'Nog invullen'}
            tone={diagnostics.styleGuideReady ? 'ok' : 'warn'}
          />
          <PlatformDiagnosticRow
            label="Objectives"
            value={diagnostics.objectivesCount > 0 ? `${diagnostics.objectivesCount} ingesteld` : 'Geen'}
            tone={diagnostics.objectivesCount > 0 ? 'ok' : 'warn'}
          />
          <PlatformDiagnosticRow
            label="Bronnen"
            value={`${diagnostics.activeSourcesCount} actief`}
            tone={diagnostics.activeSourcesCount > 0 ? 'ok' : 'warn'}
          />
          <PlatformDiagnosticRow
            label="Gallery"
            value={`${diagnostics.galleryImageCount} beeld(en)`}
            tone={diagnostics.galleryImageCount > 0 ? 'ok' : 'warn'}
          />
        </div>
      </section>

      {detail.service && clientId && (
        <PlatformServiceSection
          clientId={clientId}
          service={detail.service}
          isAdmin={isAdmin}
          onReload={loadDetail}
        />
      )}

      <PlatformEventLogSection
        id="logboek"
        events={tenantEvents}
        title="Logboek (30 dagen)"
        emptyLabel="Geen fouten of waarschuwingen in de afgelopen 30 dagen"
      />

      {isAdmin && clientId && (
        <PlatformTenantDeleteSection
          clientId={clientId}
          company={tenant.company}
          environment={status?.environment ?? 'local'}
          onDeleted={reloadOverview}
        />
      )}
    </div>
  )
}

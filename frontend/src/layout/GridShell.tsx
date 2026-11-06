import { useEffect } from 'react'
import {
  GRID,
  GRID_MAIN_PAD,
  GRID_TEMPLATE,
  platformGridTemplate,
  MAIN_CONTENT_NAV_ALIGN,
  PAGE_HEADER,
  TENANT_LOGO_BASELINE,
  gridTemplateFlush,
} from './grid.constants'
import { Logo } from '../components/Logo'
import { TenantLogoField } from '../components/TenantLogoField'
import { SidebarNav, isSettingsChapterNav, type NavKey } from '../components/SidebarNav'
import { EnvironmentBanner } from '../components/EnvironmentBanner'

const ACCOUNT_DARK_CLASS = 'ce-theme-account-dark'

type GridShellProps = {
  pageTitle: React.ReactNode
  /** Direct onder paginatitel — briefing/research.png */
  pageSubtitle?: React.ReactNode
  activeNav: NavKey
  main: React.ReactNode
  meta?: React.ReactNode
  /** Grid #2/#3 — main content max 740px (stramien 2) */
  mainLayout?: 'default' | 'grid2'
  /** Grid #2 — override main wrapper width (bijv. 500px naast meta-sidebar) */
  mainContentMaxWidth?: number
  /** Override breedte kolom 3 (main), bijv. Platform 500px */
  mainColumnWidth?: number
  /**
   * `flush` — meta (incl. HBA-hint) direct naast de contentkolom.
   * `grid` — meta op de 1440-rechterrand (Feedback).
   */
  metaAlign?: 'grid' | 'flush'
  /** Override gap tussen header en content (default 40px) */
  headerContentGap?: number
  /** briefing/theme.png — Week 17 als zwarte sectietitel i.p.v. blauwe paginatitel */
  pageTitleTone?: 'page' | 'section'
  /** platform = iGiSNext (login); tenant = klantlogo in app-sidebar (fallback iGiSNext) */
  logoVariant?: 'platform' | 'tenant'
  /** Login / auth — geen sidebar-menu */
  showNav?: boolean
  /** Vervangt SidebarNav (platform-dashboard). */
  sidebarNav?: React.ReactNode
  /** Auth-schermen — altijd licht (niet Account-dark via activeNav) */
  forceLightTheme?: boolean
}

export function GridShell({
  pageTitle,
  pageSubtitle,
  activeNav,
  main,
  meta = null,
  mainLayout = 'default',
  mainContentMaxWidth,
  mainColumnWidth,
  metaAlign = 'grid',
  headerContentGap = GRID.headerContentGap,
  pageTitleTone = 'page',
  logoVariant = 'tenant',
  showNav = true,
  sidebarNav,
  forceLightTheme = false,
}: GridShellProps) {
  const isGrid2 = mainLayout === 'grid2'
  const grid2MainMaxWidth = mainContentMaxWidth ?? GRID.colMainInner
  const metaFlush = metaAlign === 'flush' && meta != null
  const gridTemplateColumns = metaFlush
    ? gridTemplateFlush(grid2MainMaxWidth)
    : mainColumnWidth != null && meta == null
      ? platformGridTemplate(mainColumnWidth)
      : mainColumnWidth != null
        ? `${GRID.colNav}px ${GRID.colGap}px ${mainColumnWidth}px ${GRID.colGapMeta}px ${GRID.colMeta}px`
        : GRID_TEMPLATE
  const mainPad = metaFlush
    ? { ...GRID_MAIN_PAD, paddingRight: 0 }
    : GRID_MAIN_PAD
  const settingsDark = !forceLightTheme && isSettingsChapterNav(activeNav)

  useEffect(() => {
    const root = document.documentElement
    if (settingsDark) {
      root.classList.add(ACCOUNT_DARK_CLASS)
    } else {
      root.classList.remove(ACCOUNT_DARK_CLASS)
    }
    return () => {
      root.classList.remove(ACCOUNT_DARK_CLASS)
    }
  }, [settingsDark])

  return (
    <div
      className="min-h-screen bg-ce-offwhite pb-[60px]"
      style={{ paddingTop: GRID.topMargin }}
      data-theme={settingsDark ? 'account-dark' : 'light'}
    >
      <EnvironmentBanner />
      <div
        className="mx-auto grid w-full"
        style={{
          maxWidth: GRID.totalWidth,
          gridTemplateColumns,
        }}
      >
        <div
          className="sticky z-10 self-start bg-ce-offwhite text-right"
          style={{
            gridColumn: 1,
            gridRow: '1 / 4',
            top: GRID.topMargin,
          }}
        >
          {settingsDark ? (
            <div
              className={`${PAGE_HEADER.rowClass} justify-end`}
              style={{ height: GRID.headerHeight }}
            >
              <Logo variant="platform" tone="dark" />
            </div>
          ) : logoVariant === 'tenant' ? (
            <TenantLogoField placement="sidebar">
              <Logo variant="tenant" tone="light" />
            </TenantLogoField>
          ) : (
            <div
              className={`${PAGE_HEADER.rowClass} justify-end`}
              style={{ height: GRID.headerHeight }}
            >
              <Logo variant="platform" tone="light" />
            </div>
          )}
          <div style={{ height: GRID.headerContentGap }} />
          {showNav ? (sidebarNav ?? <SidebarNav active={activeNav} />) : null}
        </div>

        {/* Zelfde kolomstructuur als sidebar: header → gap → content (Home = lijst-top) */}
        <div
          style={{
            gridColumn: 3,
            gridRow: '1 / 4',
            ...mainPad,
          }}
          className="box-border flex min-w-0 flex-col"
        >
          <div className="box-border flex min-w-0 shrink-0 flex-col">
            <div className={PAGE_HEADER.rowClass}>
              <h1
                className={
                  pageTitleTone === 'section'
                    ? `${PAGE_HEADER.sectionTitleClass} ${PAGE_HEADER.titleOffsetClass}`
                    : PAGE_HEADER.pageTitleClass
                }
              >
                {pageTitle}
              </h1>
            </div>
            {pageSubtitle && (
              <p className="m-0 mt-[4px] whitespace-normal break-words text-[14px] leading-[18px] text-ce-black">
                {pageSubtitle}
              </p>
            )}
          </div>
          <div className="shrink-0" style={{ height: headerContentGap }} aria-hidden />
          <main
            className="box-border min-w-0 flex-1"
            style={{ marginTop: -MAIN_CONTENT_NAV_ALIGN }}
          >
            {isGrid2 ? (
              <div className="box-border overflow-visible" style={{ maxWidth: grid2MainMaxWidth }}>
                {main}
              </div>
            ) : (
              main
            )}
          </main>
        </div>

        <aside
          style={{
            gridColumn: 5,
            gridRow: '1 / 4',
            paddingTop: TENANT_LOGO_BASELINE + headerContentGap,
          }}
          aria-label={meta ? 'Metadata' : undefined}
        >
          {meta}
        </aside>
      </div>
    </div>
  )
}

export { GridShell as Grid2Shell }

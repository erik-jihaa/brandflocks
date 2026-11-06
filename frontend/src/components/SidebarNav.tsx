import { useEffect, useRef, useState, type CSSProperties } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { logoutAuth } from '../api/account'
import { ArrowIcon } from './ArrowIcon'

export type NavKey =
  | 'home'
  | 'brandArchitecture'
  | 'strategy'
  | 'identity'
  | 'search'
  | 'targets'
  | 'planning'
  | 'sources'
  | 'gallery'
  | 'agenda'
  | 'article'
  | 'research'
  | 'theme'
  | 'archive'
  | 'report'
  | 'fastlane'
  | 'campagne'
  | 'account'
  | 'integrations'
  | 'support'
  | 'logout'

type SectionId = 'identity' | 'knowledge' | 'content' | 'settings'

type NavItemDef = {
  key: NavKey
  label: string
  path?: string
  action?: 'logout'
}

type NavSectionDef = {
  id: SectionId
  iconSrc: string
  label: string
  items: NavItemDef[]
}

const NAV_SECTIONS: NavSectionDef[] = [
  {
    id: 'identity',
    iconSrc: '/svg/Identity.svg',
    label: 'Identiteit',
    items: [
      { key: 'brandArchitecture', label: 'Merkarchitectuur', path: '/brandarchitecture' },
      { key: 'strategy', label: 'Merkelementen', path: '/styleguide' },
      { key: 'targets', label: 'Doelen', path: '/targets' },
    ],
  },
  {
    id: 'knowledge',
    iconSrc: '/svg/Knowledge.svg',
    label: 'Kennis',
    items: [
      { key: 'sources', label: 'Bronnen', path: '/sources' },
      { key: 'theme', label: 'Thema\'s', path: '/theme' },
      { key: 'research', label: 'Onderzoek', path: '/research' },
      { key: 'search', label: 'SEO/GEO', path: '/search' },
      { key: 'planning', label: 'Planning', path: '/planning' },
    ],
  },
  {
    id: 'content',
    iconSrc: '/svg/Content.svg',
    label: 'Publicatie',
    items: [
      { key: 'fastlane', label: 'Snel artikel', path: '/fastlane' },
      { key: 'campagne', label: 'Campagnes', path: '/campagnes' },
      { key: 'article', label: 'Artikel', path: '/article' },
      { key: 'agenda', label: 'Agenda', path: '/agenda' },
      { key: 'gallery', label: 'Beeldbank', path: '/gallery' },
      { key: 'archive', label: 'Archief', path: '/archive' },
      { key: 'report', label: 'Rapport', path: '/report' },
    ],
  },
  {
    id: 'settings',
    iconSrc: '/svg/Settings.svg',
    label: 'Instellingen',
    items: [
      { key: 'account', label: 'Mijn account', path: '/account' },
      { key: 'integrations', label: 'Koppelingen', path: '/integrations' },
      { key: 'support', label: 'Help', path: '/support' },
      { key: 'logout', label: 'Uitloggen', action: 'logout' },
    ],
  },
]

const IDENTITY_KEYS = new Set<NavKey>(['brandArchitecture', 'strategy', 'identity', 'targets'])
const KNOWLEDGE_KEYS = new Set<NavKey>(['sources', 'theme', 'research', 'search', 'planning'])
const SETTINGS_KEYS = new Set<NavKey>(['account', 'integrations', 'support', 'logout'])

export const SETTINGS_NAV_KEYS = new Set<NavKey>(['account', 'integrations', 'support', 'logout'])
export const ACCOUNT_NAV_KEYS = SETTINGS_NAV_KEYS

export function isSettingsChapterNav(active: NavKey): boolean {
  return SETTINGS_NAV_KEYS.has(active)
}

export function isAccountChapterNav(active: NavKey): boolean {
  return isSettingsChapterNav(active)
}

function sectionForActive(active: NavKey, pathname: string): SectionId | null {
  if (active === 'home' || pathname === '/') return null
  if (IDENTITY_KEYS.has(active)) return 'identity'
  if (KNOWLEDGE_KEYS.has(active)) return 'knowledge'
  if (SETTINGS_KEYS.has(active)) return 'settings'
  if (pathname.startsWith('/article')) return 'content'
  return 'content'
}

function firstNavigablePath(section: NavSectionDef): string | null {
  return section.items.find((item) => item.path)?.path ?? null
}

const NAV_ARROW_CLASS = 'h-[11px] w-[11px] shrink-0'
const NAV_ICON_SIZE = 21
const OVERVIEW_ICON = '/svg/Overview.svg'
/** Labels blijven zichtbaar naast iconen (B5). */
const MAIN_LABEL_FADE_MS = 0
const MAIN_LABEL_FADE_DURATION_MS = 0

const subLinkClass = (active: boolean) =>
  `block text-right text-[14px] leading-[24px] font-semibold text-ce-black transition-colors duration-[1500ms] ease-in-out ${
    active
      ? 'underline underline-offset-[3px] decoration-ce-black decoration-[1.5px]'
      : 'hover:text-ce-blue'
  }`

/**
 * Fade-logica voor alle hoofditem-labels (Overview + Identity/Knowledge/Content/Settings).
 * - Na 5s verdwijnt de naam (1.5s fade), icoon blijft — ook als de sectie dicht is.
 * - Hover op het hoofditem: naam komt meteen terug; na mouse-out opnieuw 5s-timer.
 * Inline styles zodat globale theme-transitions opacity niet overrulen.
 */
function useMainLabelFade() {
  const [faded, setFaded] = useState(false)
  const [hovering, setHovering] = useState(false)
  const timerRef = useRef<number | null>(null)

  const clearTimer = () => {
    if (timerRef.current !== null) {
      window.clearTimeout(timerRef.current)
      timerRef.current = null
    }
  }

  useEffect(() => {
    clearTimer()
    if (hovering) {
      setFaded(false)
      return
    }
    setFaded(false)
    if (MAIN_LABEL_FADE_MS > 0) {
      timerRef.current = window.setTimeout(() => setFaded(true), MAIN_LABEL_FADE_MS)
    }
    return clearTimer
  }, [hovering])

  const labelStyle: CSSProperties = {
    color: 'var(--color-ce-black)',
    opacity: MAIN_LABEL_FADE_MS > 0 && faded && !hovering ? 0 : 1,
    transition:
      faded && !hovering
        ? `opacity ${MAIN_LABEL_FADE_DURATION_MS}ms ease-in-out`
        : 'opacity 0ms linear',
  }

  const hoverHandlers = {
    onPointerEnter: () => setHovering(true),
    onPointerLeave: () => setHovering(false),
  }

  return { labelStyle, hoverHandlers }
}

type SidebarNavProps = {
  active: NavKey
}

function NavItem({
  label,
  path,
  active,
  onLogout,
}: {
  label: string
  path?: string
  active: boolean
  onLogout?: () => void
}) {
  if (onLogout) {
    return (
      <button type="button" onClick={onLogout} className={`${subLinkClass(active)} w-full`}>
        {label}
      </button>
    )
  }

  if (path) {
    return (
      <Link to={path} className={subLinkClass(active)} aria-current={active ? 'page' : undefined}>
        {label}
      </Link>
    )
  }

  return <span className={subLinkClass(false)}>{label}</span>
}

function SectionHeader({
  section,
  open,
  onActivate,
}: {
  section: NavSectionDef
  open: boolean
  onActivate: () => void
}) {
  const { labelStyle, hoverHandlers } = useMainLabelFade()

  return (
    <div className="flex justify-end">
      <button
        type="button"
        onClick={onActivate}
        className="nav-main-row flex items-center gap-[8px] border-0 bg-transparent p-0"
        aria-expanded={open}
        aria-label={`${section.label} menu`}
        {...hoverHandlers}
      >
        <span className="nav-main-label text-[14px] font-semibold leading-[24px]" style={labelStyle}>
          {section.label}
        </span>
        <img
          src={section.iconSrc}
          alt=""
          width={NAV_ICON_SIZE}
          height={NAV_ICON_SIZE}
          className="block shrink-0"
          aria-hidden
        />
        <ArrowIcon direction={open ? 'down' : 'up'} className={NAV_ARROW_CLASS} />
      </button>
    </div>
  )
}

function OverviewMainRow({ active }: { active: boolean }) {
  const { labelStyle, hoverHandlers } = useMainLabelFade()

  return (
    <Link
      to="/"
      className="nav-main-row flex items-center justify-end gap-[8px]"
      aria-current={active ? 'page' : undefined}
      {...hoverHandlers}
    >
      <span className="nav-main-label text-[14px] font-semibold leading-[24px]" style={labelStyle}>
        Overzicht
      </span>
      <img
        src={OVERVIEW_ICON}
        alt=""
        width={NAV_ICON_SIZE}
        height={NAV_ICON_SIZE}
        className="block shrink-0"
        aria-hidden
      />
    </Link>
  )
}

export function SidebarNav({ active }: SidebarNavProps) {
  const location = useLocation()
  const navigate = useNavigate()
  const articleActive = active === 'article' || location.pathname.startsWith('/article')
  const [openSection, setOpenSection] = useState<SectionId | null>(() =>
    sectionForActive(active, location.pathname),
  )

  useEffect(() => {
    setOpenSection(sectionForActive(active, location.pathname))
  }, [active, location.pathname])

  async function handleLogout() {
    try {
      await logoutAuth()
    } catch {
      // sessie kan al verlopen zijn
    }
    navigate('/login')
  }

  function isItemActive(key: NavKey): boolean {
    if (key === 'article') return articleActive
    return key === active
  }

  function activateSection(section: NavSectionDef) {
    setOpenSection(section.id)
    const path = firstNavigablePath(section)
    if (path) navigate(path)
  }

  return (
    <nav aria-label="Hoofdnavigatie" className="text-right">
      <div>
        <OverviewMainRow active={isItemActive('home')} />
      </div>
      <div className="mt-[32px]">
        {NAV_SECTIONS.map((section, index) => {
          const open = openSection === section.id
          return (
            <div key={section.id} className={index === 0 ? '' : 'mt-[32px]'}>
              <SectionHeader
                section={section}
                open={open}
                onActivate={() => activateSection(section)}
              />
              {open ? (
                <div className="mt-[4px]">
                  {section.items.map(({ key, label, path, action }) => (
                    <NavItem
                      key={key}
                      label={label}
                      path={path}
                      active={isItemActive(key)}
                      onLogout={action === 'logout' ? () => void handleLogout() : undefined}
                    />
                  ))}
                </div>
              ) : null}
            </div>
          )
        })}
      </div>
    </nav>
  )
}

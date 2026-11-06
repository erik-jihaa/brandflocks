import { useEffect, useState } from 'react'
import { Link, useLocation, useNavigate } from 'react-router-dom'
import { ArrowIcon } from '../ArrowIcon'
import { platformLogout, type PlatformTenantRow } from '../../api/platform'
import { usePlatformData } from './PlatformDataContext'
import { PlatformStatusDot, platformStatusLabel } from './platformStatus'

type PlatformSection = 'overview' | 'tenants' | 'finance' | 'hygiene' | 'settings'

const NAV_ARROW_CLASS = 'h-[11px] w-[11px] shrink-0'
const OVERVIEW_ICON = '/svg/Overview.svg'
const CONTENT_ICON = '/svg/Content.svg'
const SETTINGS_ICON = '/svg/Settings.svg'
const NAV_ICON_SIZE = 21

const subLinkClass = (active: boolean) =>
  `block text-right text-[14px] leading-[24px] font-semibold text-ce-black transition-colors duration-150 ease-in-out ${
    active
      ? 'underline underline-offset-[3px] decoration-ce-black decoration-[1.5px]'
      : 'hover:text-ce-blue'
  }`

function sectionForPath(pathname: string): PlatformSection {
  if (pathname.startsWith('/platform/tenant')) return 'tenants'
  if (pathname.startsWith('/platform/finance')) return 'finance'
  if (pathname.startsWith('/platform/hygiene')) return 'hygiene'
  if (pathname.startsWith('/platform/settings')) return 'settings'
  return 'overview'
}

function MainNavLink({
  to,
  label,
  iconSrc,
  active,
}: {
  to: string
  label: string
  iconSrc?: string
  active: boolean
}) {
  return (
    <Link
      to={to}
      className="nav-main-row flex items-center justify-end gap-[8px]"
      aria-current={active ? 'page' : undefined}
    >
      <span className="text-[14px] font-semibold leading-[24px] text-ce-black">{label}</span>
      {iconSrc ? (
        <img
          src={iconSrc}
          alt=""
          width={NAV_ICON_SIZE}
          height={NAV_ICON_SIZE}
          className="block shrink-0"
          aria-hidden
        />
      ) : (
        <span className="inline-block h-[21px] w-[21px] shrink-0" aria-hidden />
      )}
    </Link>
  )
}

function TenantsSection({
  open,
  onToggle,
  tenants,
  activeTenantId,
  isAdmin,
}: {
  open: boolean
  onToggle: () => void
  tenants: PlatformTenantRow[]
  activeTenantId: string | null
  isAdmin: boolean
}) {
  const location = useLocation()
  const createActive = location.pathname === '/platform/tenant/new'
  const sectionActive = activeTenantId !== null || open || createActive

  return (
    <div>
      <div className="flex justify-end">
        <button
          type="button"
          onClick={onToggle}
          className="nav-main-row flex items-center gap-[8px] border-0 bg-transparent p-0"
          aria-expanded={open}
        >
          <span className="text-[14px] font-semibold leading-[24px] text-ce-black">Tenants</span>
          <img
            src={CONTENT_ICON}
            alt=""
            width={NAV_ICON_SIZE}
            height={NAV_ICON_SIZE}
            className="block shrink-0"
            aria-hidden
          />
          <ArrowIcon direction={open ? 'down' : 'up'} className={NAV_ARROW_CLASS} />
        </button>
      </div>
      {open && (
        <ul className="m-0 mt-[8px] list-none space-y-[4px] p-0">
          {isAdmin && (
            <li>
              <Link
                to="/platform/tenant/new"
                className={subLinkClass(createActive)}
                aria-current={createActive ? 'page' : undefined}
              >
                + Nieuwe tenant
              </Link>
            </li>
          )}
          {tenants.map((tenant) => {
            const active = tenant.clientId === activeTenantId
            return (
              <li key={tenant.clientId}>
                <Link
                  to={`/platform/tenant/${tenant.clientId}`}
                  className={`${subLinkClass(active)} flex items-center justify-end gap-[8px]`}
                  aria-current={active ? 'page' : undefined}
                  title={platformStatusLabel(tenant.status)}
                >
                  <span className="truncate">{tenant.company}</span>
                  <PlatformStatusDot status={tenant.status} />
                </Link>
              </li>
            )
          })}
        </ul>
      )}
      {!open && sectionActive && tenants.length > 0 && (
        <p className="m-0 mt-[4px] text-right text-[12px] leading-[16px] text-ce-beige">
          {tenants.length} tenant{tenants.length === 1 ? '' : 's'}
        </p>
      )}
    </div>
  )
}

export function PlatformSidebarNav() {
  const location = useLocation()
  const navigate = useNavigate()
  const { tenants, platformUser } = usePlatformData()
  const section = sectionForPath(location.pathname)
  const [tenantsOpen, setTenantsOpen] = useState(section === 'tenants')
  const isAdmin = platformUser?.role === 'admin'

  const tenantMatch = /^\/platform\/tenant\/([0-9a-f-]{36})/i.exec(location.pathname)
  const activeTenantId = tenantMatch?.[1] ?? null

  useEffect(() => {
    if (section === 'tenants') {
      setTenantsOpen(true)
    }
  }, [section])

  async function handleLogout() {
    try {
      await platformLogout()
    } finally {
      window.location.href = '/platform/login'
    }
  }

  function openTenants() {
    setTenantsOpen(true)
    if (!location.pathname.startsWith('/platform/tenant') && tenants[0]) {
      navigate(`/platform/tenant/${tenants[0].clientId}`)
    }
  }

  return (
    <nav aria-label="Platform navigation" className="text-right">
      <MainNavLink to="/platform" label="Overview" iconSrc={OVERVIEW_ICON} active={section === 'overview'} />

      <div className="mt-[32px]">
        <TenantsSection
          open={tenantsOpen}
          onToggle={() => (tenantsOpen ? setTenantsOpen(false) : openTenants())}
          tenants={tenants}
          activeTenantId={activeTenantId}
          isAdmin={isAdmin}
        />
      </div>

      <div className="mt-[32px]">
        <MainNavLink
          to="/platform/hygiene"
          label="Hygiëne"
          active={section === 'hygiene'}
        />
      </div>

      <div className="mt-[32px]">
        <MainNavLink
          to="/platform/finance"
          label="Finance"
          active={section === 'finance'}
        />
      </div>

      <div className="mt-[32px]">
        <div className="flex justify-end">
          <Link
            to="/platform/settings"
            className="nav-main-row flex items-center justify-end gap-[8px]"
            aria-current={section === 'settings' ? 'page' : undefined}
          >
            <span className="text-[14px] font-semibold leading-[24px] text-ce-black">Settings</span>
            <img
              src={SETTINGS_ICON}
              alt=""
              width={NAV_ICON_SIZE}
              height={NAV_ICON_SIZE}
              className="block shrink-0"
              aria-hidden
            />
          </Link>
        </div>
        {section === 'settings' && (
          <ul className="m-0 mt-[8px] list-none space-y-[4px] p-0">
            <li>
              <Link to="/login" className={subLinkClass(false)}>
                Tenant-app
              </Link>
            </li>
            <li>
              <button type="button" onClick={() => void handleLogout()} className={`${subLinkClass(false)} w-full`}>
                Logout
              </button>
            </li>
          </ul>
        )}
      </div>
    </nav>
  )
}

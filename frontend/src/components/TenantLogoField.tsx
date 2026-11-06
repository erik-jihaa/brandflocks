import type { CSSProperties, ReactNode } from 'react'
import { GRID, TENANT_LOGO_LAYOUT } from '../layout/grid.constants'

/** Vaste logo-box — logo altijd rechtsonder in het vlak. */
export const TENANT_LOGO_FIELD_OUTER_CLASS =
  'flex shrink-0 flex-col justify-end overflow-hidden'

/** Zelfde max-afmetingen voor elke tenant — aspectratio via object-contain. */
export const TENANT_LOGO_IMG_CLASS =
  'block object-contain object-right object-bottom bg-transparent'

export function tenantLogoImgStyle(): CSSProperties {
  return {
    maxWidth: GRID.tenantLogoMaxWidth,
    maxHeight: GRID.tenantLogoMaxHeight,
    width: 'auto',
    height: 'auto',
  }
}

export function tenantLogoFieldStyle(
  placement: 'sidebar' | 'account',
): CSSProperties {
  return {
    width: GRID.tenantLogoMaxWidth,
    height: GRID.tenantLogoHeight,
    marginTop:
      placement === 'sidebar'
        ? TENANT_LOGO_LAYOUT.sidebarMarginTop
        : TENANT_LOGO_LAYOUT.accountMarginTop,
  }
}

type TenantLogoFieldProps = {
  placement: 'sidebar' | 'account'
  bordered?: boolean
  children: ReactNode
}

export function TenantLogoField({
  placement,
  bordered = false,
  children,
}: TenantLogoFieldProps) {
  return (
    <div
      className={`${TENANT_LOGO_FIELD_OUTER_CLASS}${placement === 'sidebar' ? ' ml-auto' : ''}${bordered ? ' border-[1.5px] border-ce-beige' : ''}`}
      style={tenantLogoFieldStyle(placement)}
    >
      <div className="flex h-full w-full items-end justify-end leading-[0]">
        {children}
      </div>
    </div>
  )
}

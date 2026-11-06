import { Link } from 'react-router-dom'
import { LIST_SECTION } from '../../layout/grid.constants'
import { hyperlinkClassBody } from '../../design/identity.constants'
import { PLATFORM_ACTION_ITEMS_ANCHOR } from '../../api/platform'
import type { PlatformActionItem } from '../../utils/platformActionItems'
import { platformTenantActionHref } from '../../utils/platformActionItems'

type PlatformActionItemsSectionProps = {
  items: PlatformActionItem[]
  title?: string
  showTenant?: boolean
  emptyLabel?: string
}

function labelClass(tone: PlatformActionItem['tone']): string {
  return tone === 'error' ? 'text-ce-red' : 'text-ce-orange'
}

/** Actiepunten — support, rolling agenda, tech-fouten met concrete vervolgstap. */
export function PlatformActionItemsSection({
  items,
  title = 'Actiepunten',
  showTenant = true,
  emptyLabel = 'Geen open actiepunten',
}: PlatformActionItemsSectionProps) {
  return (
    <section id={PLATFORM_ACTION_ITEMS_ANCHOR} className={`${LIST_SECTION.bodyClass} scroll-mt-[24px]`}>
      <h3 className={`m-0 ${LIST_SECTION.headerClass} text-ce-black`}>{title}</h3>
      {items.length === 0 ? (
        <p className="m-0 mt-[20px] text-[14px] leading-[18px] text-ce-green-text">{emptyLabel}</p>
      ) : (
        <ul className={`m-0 mt-[20px] list-none p-0 ${LIST_SECTION.bodyStackClass}`}>
          {items.map((item, index) => (
            <li
              key={`${item.clientId}-${item.label}-${index}`}
              className={`${LIST_SECTION.itemClass} text-[14px] leading-[18px]`}
            >
              {showTenant ? (
                <div className="font-semibold text-ce-black">
                  <Link
                    to={platformTenantActionHref(item.clientId)}
                    className={hyperlinkClassBody}
                  >
                    {item.company}
                  </Link>
                </div>
              ) : null}
              <p className={`m-0 ${showTenant ? 'mt-[4px]' : ''} font-semibold ${labelClass(item.tone)}`}>
                {item.label}
              </p>
              {item.detail ? (
                <p className="m-0 mt-[4px] text-ce-black">{item.detail}</p>
              ) : null}
              <p className="m-0 mt-[6px] text-ce-blue">{item.action}</p>
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}

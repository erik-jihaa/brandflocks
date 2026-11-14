import { Link } from 'react-router-dom'

export type NavBreadcrumbItem = {
  label: string
  to?: string
}

/** Klikmodel — Publicatie › Artikel › Onderzoek */
export function NavBreadcrumb({ items }: { items: NavBreadcrumbItem[] }) {
  if (items.length === 0) return null

  return (
    <nav aria-label="Broodkruimel" className="mb-[6px]">
      <p className="m-0 text-[14px] leading-[18px] text-ce-beige">
        {items.map((item, index) => (
          <span key={`${item.label}-${index}`}>
            {index > 0 ? (
              <span className="px-[6px] text-ce-beige" aria-hidden>
                ›
              </span>
            ) : null}
            {item.to ? (
              <Link to={item.to} className="font-semibold text-ce-blue no-underline">
                {item.label}
              </Link>
            ) : (
              <span className="font-semibold text-ce-black">{item.label}</span>
            )}
          </span>
        ))}
      </p>
    </nav>
  )
}

export const BREADCRUMB_PUBLICATION_ARTICLE: NavBreadcrumbItem[] = [
  { label: 'Publicatie', to: '/article' },
  { label: 'Artikel', to: '/article' },
]
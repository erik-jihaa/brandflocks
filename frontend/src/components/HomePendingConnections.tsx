import { Link } from 'react-router-dom'
import { LIST_SECTION } from '../layout/grid.constants'
import type { HomePendingConnection } from '../api/home'

type HomePendingConnectionsProps = {
  items: HomePendingConnection[]
}

/** §1.6 — optionele koppelingen onderaan Overzicht. */
export function HomePendingConnections({ items }: HomePendingConnectionsProps) {
  if (items.length === 0) return null

  const labels = items.map((item) => item.label).join(', ')

  return (
    <section className={LIST_SECTION.dashboardSectionClass}>
      <p className="m-0 text-[14px] leading-[21px] text-ce-black">
        Nog niet gekoppeld: {labels}.{' '}
        <Link
          to="/integrations"
          className="font-semibold text-ce-blue underline-offset-[3px] hover:underline"
        >
          Instellen
        </Link>
      </p>
    </section>
  )
}

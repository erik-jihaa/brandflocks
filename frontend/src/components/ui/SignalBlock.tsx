import { Link } from 'react-router-dom'
import { LIST_SECTION } from '../../layout/grid.constants'

type SignalBlockProps = {
  problem: string
  actionLabel?: string
  actionPath?: string
}

/** Signaalblok (notitie 15 §1.2) — lijn eronder, zoals Agenda-secties. */
export function SignalBlock({ problem, actionLabel, actionPath }: SignalBlockProps) {
  return (
    <section className={LIST_SECTION.dashboardSectionClass}>
      <p className="m-0 text-[14px] leading-[21px] font-semibold text-ce-black">{problem}</p>
      {actionLabel && actionPath ? (
        <Link
          to={actionPath}
          className="mt-[8px] inline-block text-[14px] leading-[18px] font-semibold text-ce-blue underline-offset-[3px] hover:underline"
        >
          {actionLabel} →
        </Link>
      ) : null}
    </section>
  )
}

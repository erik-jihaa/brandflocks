import { Link } from 'react-router-dom'
import { LIST_ROW } from '../layout/grid.constants'
import { statusRowActionClass } from './ui/ActionLink'
import { formatArchiveSearchDate } from '../utils/archiveDates'

type ArchiveSearchResultRowProps = {
  title: string
  date: string | null | undefined
  actionLabel: string
  actionTo: string
}

/** briefing/archive search — titel + datum, View Article/Report rechts */
export function ArchiveSearchResultRow({
  title,
  date,
  actionLabel,
  actionTo,
}: ArchiveSearchResultRowProps) {
  const dateLabel = formatArchiveSearchDate(date)

  return (
    <div className={`${LIST_ROW.rowClass} text-ce-black`}>
      <div className="min-w-0 pr-[16px]">
        <p className="m-0 text-[14px] leading-[18px] font-semibold">{title}</p>
        {dateLabel && (
          <p className="m-0 mt-[4px] text-[14px] leading-[18px] text-ce-beige">{dateLabel}</p>
        )}
      </div>
      <Link to={actionTo} className={`${statusRowActionClass} shrink-0`}>
        {actionLabel}
      </Link>
    </div>
  )
}

import { LIST_ROW, LIST_SECTION } from '../layout/grid.constants'
import { ActionLink, ActionSep } from './ui/ActionLink'
import { DeleteConfirmAction } from './ui/DeleteConfirmAction'
import type { CheckStatus } from './StatusRow'

function CheckDot({
  status,
  busy,
  onCheck,
}: {
  status: CheckStatus
  busy?: boolean
  onCheck?: () => void
}) {
  return (
    <span className="inline-flex items-baseline gap-[6px]">
      <ActionLink variant="statusRow" onClick={onCheck} disabled={busy}>
        Check
      </ActionLink>
      {status === 'none' ? (
        <span className="relative top-[1px] inline-block h-[8px] w-[8px] rounded-full bg-ce-beige" />
      ) : (
        <span
          className={`relative top-[1px] inline-block h-[8px] w-[8px] rounded-full ${
            status === 'red' ? 'bg-ce-red' : 'bg-ce-green-fill'
          }`}
        />
      )}
    </span>
  )
}

type SourceRowProps = {
  title: string
  checkStatus: CheckStatus
  showBudget?: boolean
  busy?: boolean
  /** Lijn titel uit met map-header (binnen source_folders) */
  inFolder?: boolean
  /** Integratie/check-waarschuwing — alleen titel beige; Edit/Check/Delete blijven klikbaar */
  dimmed?: boolean
  onCheck?: () => void
  onEdit?: () => void
  onBudget?: () => void
  onDelete?: () => void
}

export function SourceRow({
  title,
  checkStatus,
  showBudget = false,
  busy = false,
  dimmed = false,
  inFolder = false,
  onCheck,
  onEdit,
  onBudget,
  onDelete,
}: SourceRowProps) {
  function handleDeleteClick() {
    if (!onDelete || busy) {
      return
    }
    onDelete()
  }

  const titleClass = `min-w-0 flex-1 break-words pr-[12px] text-left text-[14px] font-semibold leading-[18px] ${
    dimmed ? 'text-ce-beige' : 'text-ce-black'
  }`

  return (
    <section className={LIST_SECTION.sourcesItemClass}>
      <div
        className={`${LIST_ROW.rowClass} ${inFolder ? LIST_SECTION.sourcesFolderChildIndentClass : ''}`}
      >
        {onEdit ? (
          <button type="button" className={`${titleClass} cursor-pointer border-0 bg-transparent p-0`} onClick={onEdit} disabled={busy}>
            {title}
          </button>
        ) : (
          <span className={titleClass}>{title}</span>
        )}
        <div className={`${LIST_ROW.actionsClass} relative z-[1] shrink-0`}>
          {onEdit && (
            <>
              <ActionLink variant="statusRow" onClick={onEdit} disabled={busy}>
                Edit
              </ActionLink>
              <ActionSep dimmed={false} />
            </>
          )}
          <CheckDot status={checkStatus} busy={busy} onCheck={onCheck} />
          {showBudget && (
            <>
              <ActionSep dimmed={false} />
              <ActionLink variant="statusRow" onClick={onBudget} disabled={busy}>
                Budget
              </ActionLink>
            </>
          )}
          {onDelete && (
            <>
              <ActionSep dimmed={false} />
              <DeleteConfirmAction
                onConfirm={handleDeleteClick}
                disabled={busy}
                busy={busy}
              />
            </>
          )}
        </div>
      </div>
    </section>
  )
}

export type { CheckStatus }

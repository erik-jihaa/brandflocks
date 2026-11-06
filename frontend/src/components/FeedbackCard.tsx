import { statusRowArchiveClass } from './ui/ActionLink'
import { DeleteConfirmAction } from './ui/DeleteConfirmAction'

type FeedbackCardProps = {
  name: string
  comment: string
  actionLabel?: string | null
  /** Briefing: image-comment rood, tekst-comment oranje */
  commentTone?: 'text' | 'image'
  onOkay?: () => void
  onDelete?: () => void
  resolved?: boolean
}

export function FeedbackCard({
  comment,
  commentTone = 'text',
  onOkay,
  onDelete,
  resolved,
}: FeedbackCardProps) {
  const commentClass = commentTone === 'image' ? 'text-ce-red' : 'text-ce-orange'

  return (
    <div className="min-w-0">
      <div className="border-y border-ce-beige py-[8px]">
        <p className={`m-0 whitespace-pre-wrap break-words text-[14px] leading-[18px] font-semibold ${commentClass}`}>
          {comment}
        </p>
      </div>

      {resolved ? (
        <p className="mb-0 mt-[6px] text-[14px] leading-[18px] font-semibold text-ce-beige">Akkoord</p>
      ) : (
        <div className="mt-[6px]">
          <button
            type="button"
            onMouseDown={(e) => e.preventDefault()}
            onClick={onOkay}
            className={statusRowArchiveClass}
          >
            Akkoord
          </button>
          <span className="mx-[6px] text-[14px] font-semibold leading-[18px] text-ce-black">|</span>
          <DeleteConfirmAction
            onConfirm={() => onDelete?.()}
            deleteLabel="Verwijderen"
            confirmLabel="Verwijderen bevestigen"
            className={statusRowArchiveClass}
            onMouseDown={(e) => e.preventDefault()}
          />
        </div>
      )}
    </div>
  )
}

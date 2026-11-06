import { PillButton, pillActionsRowClass } from './PillButton'
import { renderContentDiff } from '../utils/contentDiff'
import type { ReviewEditDto } from '../types/api'

type ReviewEditsPanelProps = {
  edits: ReviewEditDto[]
  busy?: boolean
  onResolve: (editId: string, status: 'accepted' | 'rejected') => void
}

/** CE-04.1quater — reviewer-suggesties inline (tweekleurig) + accepteren/afwijzen. */
export function ReviewEditsPanel({ edits, busy = false, onResolve }: ReviewEditsPanelProps) {
  const pending = edits.filter((edit) => edit.status === 'pending')
  if (pending.length === 0) return null

  return (
    <div className="mb-[16px] space-y-[12px]">
      <p className="m-0 text-[14px] leading-[18px] font-semibold text-ce-black">
        Suggesties van reviewer
      </p>
      {pending.map((edit) => (
        <div
          key={edit.id}
          className="rounded-[20px] border-[1.5px] border-ce-beige bg-ce-white px-[20px] py-[14px]"
        >
          <div className="whitespace-pre-wrap text-[14px] leading-[18px] text-ce-black">
            {renderContentDiff(edit.originalText, edit.suggestedText)}
          </div>
          <div className={pillActionsRowClass}>
            <PillButton variant="main" disabled={busy} onClick={() => onResolve(edit.id, 'accepted')}>
              accept
            </PillButton>
            <PillButton variant="second" disabled={busy} onClick={() => onResolve(edit.id, 'rejected')}>
              reject
            </PillButton>
          </div>
        </div>
      ))}
    </div>
  )
}

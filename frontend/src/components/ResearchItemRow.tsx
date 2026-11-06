import { ActionLink, ActionSep, statusRowActionClass } from './ui/ActionLink'

type ResearchItemRowProps = {
  title: string
  snippet: string
  url?: string
  rationale?: string
  feedback: string
  likeLabel?: string
  forgetLabel?: string
  busy?: boolean
  onLike?: () => void
  onForget?: () => void
}

/** Research-item met like/forget — Theme + Research schermen */
export function ResearchItemRow({
  title,
  snippet,
  url,
  rationale,
  feedback,
  likeLabel = 'like',
  forgetLabel = 'forget',
  busy = false,
  onLike,
  onForget,
}: ResearchItemRowProps) {
  const liked = feedback === 'liked' || feedback === 'like'
  const forgotten = feedback === 'forgotten' || feedback === 'forget' || feedback === 'deleted'

  return (
    <div className="rounded-[20px] bg-ce-pink px-[20px] py-[14px]">
      <div className="flex items-start justify-between gap-[12px]">
        <p className="m-0 text-[14px] leading-[18px] font-semibold text-ce-blue">{title}</p>
        {url && (
          <a
            href={url}
            target="_blank"
            rel="noreferrer"
            className={`shrink-0 ${statusRowActionClass}`}
          >
            source
          </a>
        )}
      </div>
      {snippet && (
        <p className="mt-[8px] mb-0 text-[14px] leading-[18px] text-ce-black">{snippet}</p>
      )}
      {rationale && (
        <p className="mt-[8px] mb-0 text-[14px] leading-[18px] text-ce-beige">{rationale}</p>
      )}
      <div className="mt-[10px]">
        <ActionLink variant="statusRow" disabled={busy || liked} onClick={onLike}>
          {liked ? `${likeLabel}d` : likeLabel}
        </ActionLink>
        <ActionSep />
        <ActionLink variant="statusRow" disabled={busy || forgotten} onClick={onForget}>
          {forgotten ? `${forgetLabel}ten` : forgetLabel}
        </ActionLink>
      </div>
    </div>
  )
}

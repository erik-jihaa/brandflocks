import type { SaveNoticeKind } from '../../hooks/useSaveNotice'

type SaveNoticeProps = {
  message: string | null
  kind?: SaveNoticeKind
  fading?: boolean
  className?: string
}

export function SaveNotice({
  message,
  kind = 'success',
  fading = false,
  className = '',
}: SaveNoticeProps) {
  if (!message) return null

  const tone =
    kind === 'error'
      ? 'text-ce-red'
      : kind === 'success'
        ? 'text-ce-green-text'
        : kind === 'saving' || kind === 'info'
          ? 'text-ce-black'
          : 'text-ce-beige'

  return (
    <p
      role="status"
      aria-live="polite"
      className={`m-0 mt-[8px] text-[14px] leading-[18px] transition-opacity duration-500 ${tone} ${
        fading ? 'opacity-0' : 'opacity-100'
      } ${className}`}
    >
      {message}
    </p>
  )
}

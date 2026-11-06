import { SaveNotice } from './SaveNotice'
import type { SaveNoticeKind } from '../../hooks/useSaveNotice'

type ActionNoticeProps = {
  message: string | null
  kind?: SaveNoticeKind
  fading?: boolean
  className?: string
}

/** Korte actie-feedback — verdwijnt na 5 s (via useActionNotice). */
export function ActionNotice({
  message,
  kind = 'success',
  fading = false,
  className = '',
}: ActionNoticeProps) {
  return (
    <SaveNotice
      message={message}
      kind={kind}
      fading={fading}
      className={`mb-[12px] ${className}`.trim()}
    />
  )
}

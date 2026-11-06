import { ARTICLE_BODY_MIN_HEIGHT_CLASS } from '../utils/editorQuestions'
import { LoadingDotsInline } from './LoadingDots'

type ArticleWritingNoticeProps = {
  compact?: boolean
  slow?: boolean
}

/** Zichtbare wachtstatus terwijl CE-03 het artikel schrijft. */
export function ArticleWritingNotice({ compact = false, slow = false }: ArticleWritingNoticeProps) {
  return (
    <div
      className={`flex flex-col items-center justify-center gap-[12px] bg-ce-pink px-[24px] py-[32px] text-center ${
        compact ? 'min-h-[160px]' : ARTICLE_BODY_MIN_HEIGHT_CLASS
      }`}
      role="status"
      aria-live="polite"
    >
      <p className="m-0 inline-flex items-center justify-center gap-[8px] text-[16px] leading-[22px] text-ce-blue">
        <span>Artikel wordt geschreven</span>
        <LoadingDotsInline className="text-ce-blue" />
      </p>
      <p className="m-0 max-w-[420px] text-[14px] leading-[18px] text-ce-black">
        {slow
          ? 'Dit duurt langer dan verwacht. We blijven wachten — de pagina ververst automatisch zodra de tekst klaar is.'
          : 'Dit duurt meestal 1–2 minuten. De pagina ververst automatisch zodra de tekst klaar is.'}
      </p>
    </div>
  )
}

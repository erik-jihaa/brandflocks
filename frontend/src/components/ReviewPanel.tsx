import { PINK_BOX_CLASS } from '../layout/grid.constants'
import { RadioOption } from './ui/RadioOption'

type ReviewPanelProps = {
  name: string
  panelTitle?: string
  primaryLabel: string
  secondaryLabel: string
  primaryChecked: boolean
  secondaryChecked: boolean
  busy?: boolean
  onPrimary: () => void
  onSecondary: () => void
}

/** Artboard 20 — Review-kolom (research.png + theme.png); geen Submit */
export function ReviewPanel({
  name,
  panelTitle = 'Beoordelen',
  primaryLabel,
  secondaryLabel,
  primaryChecked,
  secondaryChecked,
  busy = false,
  onPrimary,
  onSecondary,
}: ReviewPanelProps) {
  return (
    <div className="w-full">
      <p className="m-0 mb-[10px] text-[14px] leading-[18px] font-semibold text-ce-black">{panelTitle}</p>
      <div className={`flex flex-col gap-[8px] ${busy ? 'pointer-events-none opacity-50' : ''}`}>
        <RadioOption
          name={name}
          value="primary"
          label={primaryLabel}
          checked={primaryChecked}
          onChange={onPrimary}
        />
        <RadioOption
          name={name}
          value="secondary"
          label={secondaryLabel}
          checked={secondaryChecked}
          onChange={onSecondary}
        />
      </div>
    </div>
  )
}

type PinkContentBoxProps = {
  children: React.ReactNode
  className?: string
}

/** Gedeeld pink kader — research.png, Artboard 20 */
export function PinkContentBox({ children, className = '' }: PinkContentBoxProps) {
  return <div className={`${PINK_BOX_CLASS} ${className}`.trim()}>{children}</div>
}

export const fieldReset =
  'w-full border-0 bg-transparent p-0 outline-none placeholder:text-ce-beige focus:ring-0'

/** @deprecated gebruik ARTICLE_TITLE_CLASS uit ArticleFormatBox */
export const themeSectionTitleClass = 'm-0 text-[24px] leading-[28px] font-semibold text-ce-blue'

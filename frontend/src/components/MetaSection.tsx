import { ArrowIcon } from './ArrowIcon'
import { RadioSelected } from './ui/RadioOption'

type MetaSectionProps = {
  label: string
  /** Alleen relevant bij collapsible (default). */
  open?: boolean
  onToggle?: () => void
  selected?: string
  children?: React.ReactNode
  /** When false, always show children without a toggle (HBA Details panel). */
  collapsible?: boolean
}

export function MetaSection({
  label,
  open = false,
  onToggle,
  selected,
  children,
  collapsible = true,
}: MetaSectionProps) {
  if (!collapsible) {
    return (
      <section className="mb-[18px] last:mb-0">
        <p className="m-0 mb-[8px] text-[14px] leading-[18px] font-semibold text-ce-black">{label}</p>
        {children}
      </section>
    )
  }

  return (
    <section className="mb-[18px] last:mb-0">
      <button
        type="button"
        onClick={onToggle}
        className="flex w-full cursor-pointer items-center gap-[6px] border-0 bg-transparent p-0 text-left text-[14px] leading-[18px] font-semibold text-ce-black"
      >
        <span>{label}</span>
        <ArrowIcon direction={open ? 'down' : 'up'} className="h-[10px] w-[10px]" />
      </button>

      {open && children}

      {!open && selected !== undefined && <RadioSelected label={selected} />}
    </section>
  )
}

export { RadioOption } from './ui/RadioOption'

type SourceAccordionProps = {
  label: string
  url: string
}

export function SourceAccordion({ label, url }: SourceAccordionProps) {
  return (
    <details className="group mt-[18px] border-t border-ce-beige pt-[18px]">
      <summary className="flex cursor-pointer list-none items-center gap-[6px] text-[14px] leading-[18px] font-semibold text-ce-black [&::-webkit-details-marker]:hidden">
        <span>{label}</span>
        <span className="inline-flex transition-transform group-open:rotate-180">
          <ArrowIcon direction="up" className="h-[9px] w-[9px]" />
        </span>
      </summary>
      <div className="pt-[6px] text-[14px] leading-[18px]">
        <a href={url} className="text-ce-blue underline decoration-[1.5px]" target="_blank" rel="noopener noreferrer">
          {url}
        </a>
      </div>
    </details>
  )
}

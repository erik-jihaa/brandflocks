import type { ReactNode } from 'react'

type RadioOptionProps = {
  name: string
  value: string
  label: string
  checked: boolean
  suggested?: boolean
  onChange: (value: string) => void
}

const RADIO_SIZE = 'inline-flex h-[9px] w-[9px] shrink-0 rounded-full'

/** Artboard 20 — selected: volledig gevuld; not selected: outline only */
export function RadioIndicator({ checked }: { checked: boolean }) {
  if (checked) {
    return <span className={`${RADIO_SIZE} bg-ce-black`} aria-hidden />
  }

  return (
    <span className={`${RADIO_SIZE} border-[1.5px] border-ce-black bg-transparent`} aria-hidden />
  )
}

export function RadioOption({ name, value, label, checked, suggested = false, onChange }: RadioOptionProps) {
  return (
    <label className="relative flex cursor-pointer items-center gap-[8px] text-[14px] leading-[18px] text-ce-black">
      <input
        type="radio"
        name={name}
        value={value}
        checked={checked}
        onChange={() => onChange(value)}
        className="ce-visually-hidden"
      />
      <RadioIndicator checked={checked} />
      <span>{label}</span>
      {suggested && (
        <span className="rounded-[8px] bg-ce-beige/20 px-[6px] py-[1px] text-[10px] font-semibold uppercase tracking-wide text-ce-beige">
          AI
        </span>
      )}
    </label>
  )
}

export function RadioSelected({ label }: { label: string }) {
  return (
    <p className="m-0 flex items-center gap-[8px] text-[14px] leading-[18px] text-ce-black">
      <RadioIndicator checked />
      <span>{label}</span>
    </p>
  )
}

type CheckboxOptionProps = {
  checked: boolean
  onChange: (checked: boolean) => void
  /** Eénregel label — zelfde markup als RadioOption */
  label?: string
  children?: ReactNode
  className?: string
  align?: 'center' | 'start'
  disabled?: boolean
}

/** Zelfde zwarte ronde indicator als meta-kolom RadioOption */
export function CheckboxOption({
  checked,
  onChange,
  label,
  children,
  className = '',
  align = 'center',
  disabled = false,
}: CheckboxOptionProps) {
  const alignClass = align === 'start' ? 'items-start' : 'items-center'
  const indicatorWrapClass =
    align === 'start' ? 'mt-[2px] inline-flex shrink-0 self-start' : 'inline-flex shrink-0 self-center'

  return (
    <label
      className={`relative flex gap-[8px] text-[14px] leading-[18px] text-ce-black ${disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer'} ${alignClass} ${className}`.trim()}
    >
      <input
        type="checkbox"
        checked={checked}
        disabled={disabled}
        onChange={(e) => onChange(e.target.checked)}
        className="ce-visually-hidden"
      />
      <span className={indicatorWrapClass}>
        <RadioIndicator checked={checked} />
      </span>
      {label != null ? <span>{label}</span> : <span className="min-w-0">{children}</span>}
    </label>
  )
}

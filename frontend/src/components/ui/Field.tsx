import { useState, type ReactNode } from 'react'
import { borderedSectionClass } from '../../design/identity.constants'
import { PencilIcon } from '../PencilIcon'
import { ARTICLE_TITLE_INPUT_CLASS } from '../ArticleFormatBox'
import { FieldHint, type FieldHintTone } from './FieldHint'

function PasswordVisibilityToggle({
  visible,
  onToggle,
}: {
  visible: boolean
  onToggle: () => void
}) {
  return (
    <button
      type="button"
      onClick={onToggle}
      className="absolute right-[16px] top-1/2 flex h-[28px] w-[28px] -translate-y-1/2 items-center justify-center rounded-full text-ce-beige hover:text-ce-black focus:outline-none focus-visible:ring-[1.5px] focus-visible:ring-ce-blue"
      aria-label={visible ? 'Wachtwoord verbergen' : 'Wachtwoord tonen'}
      tabIndex={0}
    >
      {visible ? (
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden>
          <path
            d="M3 3l18 18M10.58 10.58A2 2 0 0012 15a2 2 0 002.42-2.42M9.88 5.09A10.94 10.94 0 0112 5c5 0 9.27 3.11 11 7-1.02 2.28-2.78 4.22-5 5.28M6.11 6.11C3.6 7.72 1.78 10.26 1 13c1.73 3.89 6 7 11 7 1.38 0 2.68-.24 3.88-.67"
            stroke="currentColor"
            strokeWidth="1.75"
            strokeLinecap="round"
            strokeLinejoin="round"
          />
        </svg>
      ) : (
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden>
          <path
            d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"
            stroke="currentColor"
            strokeWidth="1.75"
            strokeLinecap="round"
            strokeLinejoin="round"
          />
          <circle cx="12" cy="12" r="3" stroke="currentColor" strokeWidth="1.75" />
        </svg>
      )}
    </button>
  )
}

type FieldSingleProps = {
  value: string
  onChange: (value: string) => void
  label?: string
  placeholder?: string
  id?: string
  type?: 'text' | 'password' | 'email' | 'date' | 'month'
  variant?: 'default' | 'title'
  /** Placeholder kleur — default beige; research URLs zwart; theme blauw */
  placeholderTone?: 'beige' | 'black' | 'blue'
  showPencil?: boolean
  className?: string
  onBlur?: () => void
  /** Selecteer de huidige waarde bij focus, zodat typen niet achter het cijfer plakt. */
  selectOnFocus?: boolean
  autoComplete?: string
  required?: boolean
  'aria-label'?: string
  /** Toelichting via uitklapbaar i-bolletje (niet permanent zichtbaar). */
  hint?: ReactNode
  helpKey?: string
  hintPosition?: 'above' | 'below'
  hintTone?: FieldHintTone
}

/** Artboard 20 — single line: 40px hoog, radius 20, padding 30; default 14px black, title 21px blue */
export function FieldSingle({
  value,
  onChange,
  label,
  placeholder,
  id,
  type = 'text',
  variant = 'default',
  placeholderTone = 'beige',
  showPencil = false,
  className = '',
  onBlur,
  selectOnFocus = false,
  autoComplete,
  required,
  'aria-label': ariaLabel,
  hint,
  helpKey,
  hintPosition = 'below',
  hintTone = 'beige',
}: FieldSingleProps) {
  const [passwordVisible, setPasswordVisible] = useState(false)
  const [dateFocused, setDateFocused] = useState(false)
  const isTitle = variant === 'title'
  const isPassword = type === 'password'
  const inputType = isPassword && passwordVisible ? 'text' : type
  const placeholderClass =
    placeholderTone === 'black'
      ? 'placeholder:text-ce-black'
      : placeholderTone === 'blue'
        ? 'placeholder:text-ce-blue'
        : 'placeholder:text-ce-beige'

  const titleTypography = 'text-[21px] font-semibold leading-[28px] text-ce-blue'
  const inputClass = `border-0 bg-transparent p-0 outline-none focus:ring-0 ${placeholderClass} ${
    isTitle ? titleTypography : 'text-[14px] leading-[14px] text-ce-black'
  }`

  const pencilInputClass = `${inputClass} w-auto ${
    isTitle ? ARTICLE_TITLE_INPUT_CLASS : ''
  }`

  const field = showPencil ? (
    <div className="flex h-[40px] w-full items-center rounded-[20px] bg-ce-pink px-[30px]">
      <span className="inline-flex max-w-full items-baseline gap-[6px]">
        <input
          id={id}
          type={type}
          value={value}
          placeholder={placeholder}
          onChange={(e) => onChange(e.target.value)}
          onBlur={onBlur}
          aria-label={ariaLabel ?? label ?? placeholder}
          size={Math.max(value.length || 1, placeholder?.length ?? 8)}
          className={pencilInputClass}
        />
        <PencilIcon />
      </span>
    </div>
  ) : type === 'date' || type === 'month' ? (
    <div className="relative h-[40px] w-full">
      {!value && placeholder && !dateFocused && (
        <span
          aria-hidden
          className="pointer-events-none absolute inset-y-0 left-[30px] z-[1] flex items-center text-[14px] leading-[14px] text-ce-black"
        >
          {placeholder}
        </span>
      )}
      <input
        id={id}
        type={type}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        onFocus={() => setDateFocused(true)}
        onBlur={() => {
          setDateFocused(false)
          onBlur?.()
        }}
        aria-label={ariaLabel ?? label ?? placeholder}
        className={`h-[40px] w-full rounded-[20px] border-0 bg-ce-pink px-[30px] outline-none focus:ring-[1.5px] focus:ring-ce-blue text-[14px] leading-[14px] ${
          value || dateFocused
            ? 'text-ce-black'
            : 'text-transparent [&::-webkit-datetime-edit]:opacity-0 [&::-webkit-datetime-edit-fields-wrapper]:opacity-0 [&::-webkit-datetime-edit-text]:opacity-0 [&::-webkit-datetime-edit-month-field]:opacity-0 [&::-webkit-datetime-edit-day-field]:opacity-0 [&::-webkit-datetime-edit-year-field]:opacity-0'
        }`}
      />
    </div>
  ) : isPassword ? (
    <div className="relative w-full">
      <input
        id={id}
        type={inputType}
        value={value}
        placeholder={placeholder}
        autoComplete={autoComplete}
        required={required}
        onChange={(e) => onChange(e.target.value)}
        onBlur={onBlur}
        aria-label={ariaLabel ?? label ?? placeholder}
        className={`h-[40px] w-full rounded-[20px] border-0 bg-ce-pink pr-[52px] pl-[30px] text-[14px] leading-[14px] text-ce-black outline-none focus:ring-[1.5px] focus:ring-ce-blue ${placeholderClass}`}
      />
      <PasswordVisibilityToggle
        visible={passwordVisible}
        onToggle={() => setPasswordVisible((v) => !v)}
      />
    </div>
  ) : (
    <input
      id={id}
      type={type}
      value={value}
      placeholder={placeholder}
      autoComplete={autoComplete}
      required={required}
      onChange={(e) => onChange(e.target.value)}
      onFocus={(e) => {
        if (!selectOnFocus) return
        const el = e.currentTarget
        requestAnimationFrame(() => el.select())
      }}
      onMouseUp={(e) => {
        if (selectOnFocus) e.preventDefault()
      }}
      onBlur={onBlur}
      aria-label={ariaLabel ?? label ?? placeholder}
      className={`h-[40px] w-full rounded-[20px] border-0 px-[30px] outline-none focus:ring-[1.5px] focus:ring-ce-blue ${placeholderClass} ${
        isTitle
          ? 'bg-ce-pink text-[21px] font-semibold text-ce-blue'
          : 'bg-ce-pink text-[14px] leading-[14px] text-ce-black'
      }`}
    />
  )

  return (
    <div className={className}>
      {label && (
        <label
          htmlFor={id}
          className={`mb-[6px] block px-[30px] ${
            isTitle
              ? 'text-[24px] leading-[28px] font-semibold text-ce-blue'
              : 'text-[14px] leading-[18px] font-semibold text-ce-black'
          }`}
        >
          {label}
        </label>
      )}
      {helpKey && hintPosition === 'above' ? (
        <FieldHint position="above" tone={hintTone} helpKey={helpKey} />
      ) : hint && hintPosition === 'above' ? (
        <FieldHint position="above" tone={hintTone}>
          {hint}
        </FieldHint>
      ) : null}
      {field}
      {helpKey && hintPosition !== 'above' ? (
        <FieldHint position="below" tone={hintTone} helpKey={helpKey} />
      ) : hint && hintPosition !== 'above' ? (
        <FieldHint position="below" tone={hintTone}>
          {hint}
        </FieldHint>
      ) : null}
    </div>
  )
}

type FieldMultiProps = {
  value: string
  onChange: (value: string) => void
  label?: string
  placeholder?: string
  id?: string
  minHeight?: number
  className?: string
}

/** Artboard 20 — multi line: radius 20, padding 30, corps 14/18 */
export function FieldMulti({
  value,
  onChange,
  label,
  placeholder,
  id,
  minHeight = 160,
  className = '',
}: FieldMultiProps) {
  return (
    <div className={className}>
      {label && (
        <label
          htmlFor={id}
          className="mb-[6px] block text-[14px] leading-[18px] font-semibold text-ce-black"
        >
          {label}
        </label>
      )}
      <textarea
        id={id}
        value={value}
        placeholder={placeholder}
        onChange={(e) => onChange(e.target.value)}
        aria-label={label ?? placeholder}
        style={{ minHeight }}
        className="w-full resize-y rounded-[20px] border-0 bg-ce-pink px-[30px] py-[16px] text-[14px] leading-[18px] text-ce-black outline-none placeholder:text-ce-beige focus:ring-[1.5px] focus:ring-ce-blue"
      />
    </div>
  )
}

type FieldStrategyProps = {
  label: string
  value: string
  onChange: (value: string) => void
  id?: string
  minHeight?: number
  placeholder?: string
  className?: string
  /** inline = label naast veld (default); stacked = label boven veld, marge 1 regelhoogte */
  layout?: 'inline' | 'stacked'
}

export type StrategyFieldProps = {
  title: string
  hint?: string
  /** Suggestie uit strategiedocument — persistente beige hint, geen auto-invul */
  documentSuggestion?: string
  value: string
  onChange: (value: string) => void
  id?: string
  minHeight?: number
  placeholder?: string
  className?: string
}

function formatDocumentSuggestionLabel(text: string): string {
  return `Uit je document: "${text}". Schrijf hieronder in je eigen woorden wat dit voor jullie merk betekent.`
}

/** Strategy Grid #1 — blauwe header, beige hint, zwarte invultekst, lijn eronder (Account-kolombreedte). */
export function StrategyField({
  title,
  hint,
  documentSuggestion,
  value,
  onChange,
  id,
  minHeight = 100,
  placeholder,
  className = '',
}: StrategyFieldProps) {
  const suggestionText = documentSuggestion?.trim() ?? ''
  const showDocumentSuggestion = suggestionText !== ''
  const resolvedPlaceholder =
    placeholder ?? (hint && !value.trim() ? hint : undefined)

  return (
    <section className={`${borderedSectionClass} ${className}`.trim()}>
      <h3 className="m-0 text-[24px] leading-[28px] font-semibold text-ce-blue">{title}</h3>
      {hint && !value.trim() ? (
        <p className="m-0 mt-[8px] text-[14px] leading-[18px] text-ce-beige">{hint}</p>
      ) : null}
      {showDocumentSuggestion ? (
        <p className="m-0 mt-[8px] text-[14px] leading-[18px] text-ce-beige">
          {formatDocumentSuggestionLabel(suggestionText)}
        </p>
      ) : null}
      <textarea
        id={id}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        placeholder={resolvedPlaceholder}
        style={{ minHeight }}
        aria-label={title}
        className={`mt-[8px] w-full resize-y border-0 bg-transparent p-0 text-[14px] leading-[18px] text-ce-black outline-none placeholder:text-ce-beige focus:ring-0`}
      />
    </section>
  )
}

type StrategySectionProps = {
  id?: string
  title: string
  hint?: string
  /** Beige = invoerhint/placeholder-stijl; black = leestekst onder kop (default beige) */
  hintTone?: 'beige' | 'black'
  /** Verberg hint zodra sectie-inhoud is ingevuld */
  filled?: boolean
  children: ReactNode
  className?: string
}

/** Strategy — sectie met header + hint, inhoud eronder (pulldown, editors, …). */
export function StrategySection({
  id,
  title,
  hint,
  hintTone = 'beige',
  filled = false,
  children,
  className = '',
}: StrategySectionProps) {
  const hintClass =
    hintTone === 'black'
      ? 'm-0 mt-[8px] text-[14px] leading-[18px] text-ce-black'
      : 'm-0 mt-[8px] text-[14px] leading-[18px] text-ce-beige'

  return (
    <section id={id} className={`${borderedSectionClass} ${className}`.trim()}>
      <h3 className="m-0 text-[24px] leading-[28px] font-semibold text-ce-blue">{title}</h3>
      {hint && !filled ? <p className={hintClass}>{hint}</p> : null}
      <div className="mt-[18px]">{children}</div>
    </section>
  )
}

/** @deprecated Targets/design-system — nieuwe Strategy-pagina gebruikt StrategyField */
export function FieldStrategy({
  label,
  value,
  onChange,
  id,
  minHeight = 100,
  placeholder,
  className = '',
  layout = 'inline',
}: FieldStrategyProps) {
  const shellClass = `rounded-[20px] bg-ce-pink px-[30px] py-[16px] focus-within:ring-[1.5px] focus-within:ring-ce-blue ${className}`

  if (layout === 'stacked') {
    return (
      <div className={shellClass}>
        <label
          htmlFor={id}
          className="mb-[18px] block text-[21px] leading-[18px] font-semibold text-ce-blue"
        >
          {label} _
        </label>
        <textarea
          id={id}
          value={value}
          onChange={(e) => onChange(e.target.value)}
          placeholder={placeholder}
          style={{ minHeight }}
          aria-label={label}
          className="w-full resize-y border-0 bg-transparent p-0 text-[18px] leading-[21px] text-ce-black outline-none placeholder:text-ce-beige"
        />
      </div>
    )
  }

  return (
    <div className={shellClass}>
      <div className="flex flex-wrap items-start gap-x-[6px]">
        <label
          htmlFor={id}
          className="shrink-0 pt-[1px] text-[21px] leading-[21px] font-semibold text-ce-blue"
        >
          {label} _
        </label>
        <textarea
          id={id}
          value={value}
          onChange={(e) => onChange(e.target.value)}
          placeholder={placeholder}
          style={{ minHeight }}
          aria-label={label}
          className="min-w-[min(100%,12rem)] flex-1 resize-y border-0 bg-transparent p-0 text-[18px] leading-[21px] text-ce-black outline-none placeholder:text-ce-beige"
        />
      </div>
    </div>
  )
}

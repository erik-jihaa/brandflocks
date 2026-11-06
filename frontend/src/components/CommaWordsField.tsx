import { useLayoutEffect, useRef } from 'react'

export function CommaWordsField({
  id = 'comma-words',
  value,
  onChange,
  disabled = false,
  placeholder = "bijv. biologisch, small batch, vers gebrand",
}: {
  id?: string
  value: string
  onChange: (value: string) => void
  disabled?: boolean
  placeholder?: string
}) {
  const ref = useRef<HTMLTextAreaElement>(null)

  useLayoutEffect(() => {
    const el = ref.current
    if (!el) return
    el.style.height = '0px'
    el.style.height = `${Math.max(72, el.scrollHeight)}px`
  }, [value])

  return (
    <textarea
      ref={ref}
      id={id}
      value={value}
      onChange={(e) => onChange(e.target.value)}
      disabled={disabled}
      placeholder={placeholder}
      aria-label="Woorden"
      className="w-full resize-none overflow-hidden border-0 bg-transparent p-0 text-[14px] leading-[18px] text-ce-black outline-none placeholder:text-ce-beige focus:ring-0 disabled:opacity-50"
    />
  )
}

export function feitRowsWithCommaWords(
  input: string,
  rows: { id: string; text: string }[],
): { id: string; text: string }[] {
  const tokens = input
    .split(/[,;]+/)
    .map((s) => s.trim())
    .filter(Boolean)
  if (tokens.length === 0) return rows

  const seen = new Set(rows.map((r) => r.text.trim().toLowerCase()).filter(Boolean))
  const next = rows.filter((r) => r.text.trim() !== '')
  for (let i = 0; i < tokens.length; i++) {
    const text = tokens[i]
    const key = text.toLowerCase()
    if (seen.has(key)) continue
    seen.add(key)
    next.push({ id: `new-word-${Date.now()}-${i}`, text })
  }
  return next.length > 0 ? next : [{ id: `new-${Date.now()}`, text: '' }]
}

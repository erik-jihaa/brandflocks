type PlanningMetricFieldProps = {
  labelLines: string[]
  value: string
  placeholder: string
  onChange: (value: string) => void
  id?: string
}

export function PlanningMetricField({
  labelLines,
  value,
  placeholder,
  onChange,
  id,
}: PlanningMetricFieldProps) {
  const ariaLabel = labelLines.join(' ')

  return (
    <div className="flex min-w-0 items-center gap-[10px]">
      <span
        className="w-[76px] shrink-0 text-[14px] leading-[18px] font-semibold text-ce-black"
        aria-hidden
      >
        {labelLines.map((line) => (
          <span key={line} className="block">
            {line}
          </span>
        ))}
      </span>
      <input
        id={id}
        type="text"
        inputMode="numeric"
        value={value}
        placeholder={placeholder}
        onChange={(e) => onChange(e.target.value.replace(/\D/g, ''))}
        aria-label={ariaLabel}
        className="h-[40px] min-w-0 flex-1 rounded-[20px] border-0 bg-ce-pink px-[16px] text-center text-[14px] leading-[14px] text-ce-black outline-none placeholder:text-ce-beige focus:ring-[1.5px] focus:ring-ce-blue"
      />
    </div>
  )
}

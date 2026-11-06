type PercentSliderProps = {
  label: string
  value: number
  onChange: (value: number) => void
  id?: string
  /** Bovengrens (bijv. resterend % tot 100). Default 100. */
  max?: number
}

/** Aandeel % — range slider met CE-styling */
export function PercentSlider({ label, value, onChange, id, max = 100 }: PercentSliderProps) {
  const maxValue = Math.max(0, Math.min(100, max))
  const clampedValue = Math.min(Math.max(0, value), maxValue)

  return (
    <div className="px-[30px]">
      <div className="mb-[10px] flex flex-wrap items-baseline justify-between gap-[8px]">
        <label htmlFor={id} className="text-[14px] leading-[18px] font-semibold text-ce-black">
          {label}
        </label>
        <span className="text-[14px] leading-[18px] font-semibold text-ce-blue">{clampedValue}%</span>
      </div>
      <input
        id={id}
        type="range"
        min={0}
        max={maxValue}
        step={1}
        value={clampedValue}
        onChange={(e) => onChange(Number(e.target.value))}
        aria-valuenow={clampedValue}
        aria-valuemin={0}
        aria-valuemax={maxValue}
        className="h-[6px] w-full cursor-pointer appearance-none rounded-full bg-ce-beige/40 accent-ce-blue [&::-webkit-slider-thumb]:h-[18px] [&::-webkit-slider-thumb]:w-[18px] [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-ce-blue"
      />
    </div>
  )
}

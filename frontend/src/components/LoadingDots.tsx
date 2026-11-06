type LoadingDotsProps = {
  label?: string
  className?: string
}

/** Drie animerende puntjes — inline in knoppen of naast label. */
export function LoadingDotsInline({ className = '' }: { className?: string }) {
  return (
    <span className={`inline-flex items-center gap-[4px] ${className}`} aria-hidden="true">
      {[0, 1, 2].map((index) => (
        <span
          key={index}
          className="inline-block h-[5px] w-[5px] rounded-full bg-current animate-ce-loading-dot"
          style={{ animationDelay: `${index * 0.18}s` }}
        />
      ))}
    </span>
  )
}

/** Drie animerende puntjes — fastlane / research / article wachtstatus. */
export function LoadingDots({ label, className = 'text-ce-beige' }: LoadingDotsProps) {
  return (
    <p className={`m-0 inline-flex items-center gap-[8px] text-[14px] leading-[18px] ${className}`}>
      {label && <span>{label}</span>}
      <LoadingDotsInline />
      <span className="sr-only">{label ?? 'Laden'}</span>
    </p>
  )
}

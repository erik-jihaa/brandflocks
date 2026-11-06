import { useEffect, useRef, useState } from 'react'
import { pulldownBoxClass } from '../../design/identity.constants'
import { ArrowIcon } from '../ArrowIcon'

export type PlatformActionOption = {
  id: string
  label: string
  description: string
}

type PlatformActionsPulldownProps = {
  options: PlatformActionOption[]
  onSelect: (id: string) => void
  disabled?: boolean
  placeholder?: string
  className?: string
}

/** Platform tenant — actiepulldown met beige onderregel per item (Artboard 20). */
export function PlatformActionsPulldown({
  options,
  onSelect,
  disabled = false,
  placeholder = 'Acties',
  className = '',
}: PlatformActionsPulldownProps) {
  const [open, setOpen] = useState(false)
  const rootRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (!open) return

    function onDocClick(e: MouseEvent) {
      if (!rootRef.current?.contains(e.target as Node)) {
        setOpen(false)
      }
    }

    document.addEventListener('mousedown', onDocClick)
    return () => document.removeEventListener('mousedown', onDocClick)
  }, [open])

  function pick(id: string) {
    setOpen(false)
    onSelect(id)
  }

  const triggerClass = disabled
    ? 'cursor-not-allowed text-ce-beige'
    : 'cursor-pointer text-ce-black'

  return (
    <div ref={rootRef} className={`relative ${className}`}>
      {!open ? (
        <button
          type="button"
          disabled={disabled}
          onClick={() => {
            if (!disabled) setOpen(true)
          }}
          className={`flex h-[40px] w-full items-center justify-between px-[30px] text-left text-[14px] leading-[14px] font-semibold ${triggerClass} ${pulldownBoxClass}`}
        >
          <span>{placeholder}</span>
          <ArrowIcon direction="down" className="h-[11px] w-[11px] shrink-0" />
        </button>
      ) : (
        <div className={pulldownBoxClass}>
          <button
            type="button"
            onClick={() => setOpen(false)}
            className="flex h-[40px] w-full cursor-pointer items-center justify-between border-0 bg-transparent px-[30px] text-left text-[14px] leading-[14px] font-semibold text-ce-black"
          >
            <span>{placeholder}</span>
            <ArrowIcon direction="up" className="h-[11px] w-[11px] shrink-0" />
          </button>
          <ul className="m-0 list-none px-[30px] pb-[14px]">
            {options.map((option) => (
              <li key={option.id}>
                <button
                  type="button"
                  onClick={() => pick(option.id)}
                  className="w-full cursor-pointer border-0 bg-transparent p-0 py-[8px] text-left"
                >
                  <span className="block text-[14px] leading-[18px] font-semibold text-ce-black">
                    {option.label}
                  </span>
                  <span className="mt-[2px] block text-[14px] leading-[18px] font-normal text-ce-beige">
                    {option.description}
                  </span>
                </button>
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  )
}

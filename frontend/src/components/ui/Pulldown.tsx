import { useEffect, useRef, useState } from 'react'
import { pulldownBoxClass } from '../../design/identity.constants'
import { ArrowIcon } from '../ArrowIcon'

type PulldownProps = {
  options: string[]
  value: string
  onChange: (value: string) => void
  placeholder?: string
  className?: string
}

/** Artboard 20 — transparent bg, border 1px beige, radius 20, 14/14 semibold black */
export function Pulldown({
  options,
  value,
  onChange,
  placeholder,
  className = '',
}: PulldownProps) {
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

  const boxClass = pulldownBoxClass

  const display = value || placeholder || ''
  const isPlaceholder = !value && !!placeholder

  return (
    <div ref={rootRef} className={`relative ${className}`}>
      {!open ? (
        <button
          type="button"
          onClick={() => setOpen(true)}
          className={`flex h-[40px] cursor-pointer items-center justify-between px-[30px] text-left text-[14px] leading-[14px] font-semibold text-ce-black ${boxClass}`}
        >
          <span className={isPlaceholder ? 'text-ce-black' : undefined}>{display}</span>
          <ArrowIcon direction="down" className="h-[11px] w-[11px] shrink-0" />
        </button>
      ) : (
        <div className={boxClass}>
          <button
            type="button"
            onClick={() => setOpen(false)}
            className="flex h-[40px] w-full cursor-pointer items-center justify-between border-0 bg-transparent px-[30px] text-left text-[14px] leading-[14px] font-semibold text-ce-black"
          >
            <span>{display}</span>
            <ArrowIcon direction="up" className="h-[11px] w-[11px] shrink-0" />
          </button>
          <ul className="m-0 list-none px-[30px] pb-[14px]">
            {options.map((opt) => (
              <li key={opt}>
                <button
                  type="button"
                  onClick={() => {
                    onChange(opt)
                    setOpen(false)
                  }}
                  className={`w-full cursor-pointer border-0 bg-transparent p-0 py-[5px] text-left text-[14px] leading-[14px] font-semibold text-ce-black md:hover:text-ce-blue md:hover:underline md:decoration-[1.5px] md:underline-offset-[3px] ${
                    opt === value
                      ? 'underline decoration-[1.5px] underline-offset-[3px]'
                      : ''
                  }`}
                >
                  {opt}
                </button>
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  )
}

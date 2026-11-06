import { useEffect, useRef, useState } from 'react'
import { pulldownBoxClassNarrow } from '../design/identity.constants'
import { ArrowIcon } from './ArrowIcon'
import { SOURCE_TABS } from '../utils/sourceTabs'
import type { SourceTab } from '../types/sources'

type SourceTabsProps = {
  onAdd: (tab: SourceTab) => void
}

/** sources 1Type+.png — Add Sources pulldown (dicht/open), zelfde vormgeving als Pulldown. */
export function SourceTabs({ onAdd }: SourceTabsProps) {
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

  const boxClass = pulldownBoxClassNarrow

  return (
    <div ref={rootRef} className="relative shrink-0">
      {!open ? (
        <button
          type="button"
          onClick={() => setOpen(true)}
          className={`flex h-[40px] cursor-pointer items-center justify-between px-[30px] text-left text-[14px] leading-[14px] font-semibold text-ce-black ${boxClass}`}
        >
          <span>Add Sources</span>
          <ArrowIcon direction="down" className="h-[11px] w-[11px] shrink-0" />
        </button>
      ) : (
        <div className={boxClass}>
          <button
            type="button"
            onClick={() => setOpen(false)}
            className="flex h-[40px] w-full cursor-pointer items-center justify-between border-0 bg-transparent px-[30px] text-left text-[14px] leading-[14px] font-semibold text-ce-black"
          >
            <span>Add Sources</span>
            <ArrowIcon direction="up" className="h-[11px] w-[11px] shrink-0" />
          </button>
          <ul className="m-0 list-none px-[30px] pb-[14px]">
            {SOURCE_TABS.map((tab) => (
              <li key={tab}>
                <button
                  type="button"
                  onClick={() => {
                    onAdd(tab)
                    setOpen(false)
                  }}
                  className="w-full cursor-pointer border-0 bg-transparent p-0 py-[5px] text-left text-[14px] leading-[14px] font-semibold text-ce-black"
                >
                  {tab}
                </button>
              </li>
            ))}
          </ul>
        </div>
      )}
    </div>
  )
}

export type { SourceTab }

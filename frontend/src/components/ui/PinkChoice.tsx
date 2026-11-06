import type { ReactNode } from 'react'
import { RadioIndicator } from './RadioOption'

export type PinkChoiceActionLabel = 'gebruik dit artikel' | 'gebruik deze tekst' | 'voeg toe aan bronnen'

export type PinkChoiceAction = {
  value: string
  label: PinkChoiceActionLabel | string
}

type PinkChoiceOptionProps = {
  name: string
  value: string
  checked: boolean
  /** Acties die aan staan (multi-select, checkbox-gedrag met radio-look) */
  selectedActions?: string[]
  onChange?: (value: string) => void
  /** Toggle één actie; meerdere acties kunnen tegelijk aan */
  onToggleAction?: (value: string, action: string, next: boolean) => void
  /** Meerdere acties onder één voorstel (bijv. gebruik + toevoegen) */
  onSelect?: (value: string, action: string) => void
  actions?: PinkChoiceAction[]
  /** Enkele radio-label — bronartikel vs. passage/citaat (als `actions` ontbreekt) */
  actionLabel?: PinkChoiceActionLabel
  /** true = meerdere acties tegelijk (default als `actions` > 1) */
  multiAction?: boolean
  children: ReactNode
}

/**
 * Keuze-item in één roze kader: copy zwart, radio/checkbox onderaan.
 * Geen wit kader / schaduw erin — Artboard: altijd 1 kader, geen kader-in-kader.
 */
export function PinkChoiceOption({
  name,
  value,
  checked,
  selectedActions = [],
  onChange,
  onToggleAction,
  onSelect,
  actions,
  actionLabel = 'gebruik dit artikel',
  multiAction,
  children,
}: PinkChoiceOptionProps) {
  const actionList =
    actions && actions.length > 0 ? actions : [{ value: 'default', label: actionLabel }]
  const allowMulti = multiAction ?? actionList.length > 1

  return (
    <div className="flex min-w-0 flex-col gap-[8px]">
      <span className="min-w-0 break-words text-[14px] leading-[18px] text-ce-black">{children}</span>
      <div className="flex flex-col gap-[6px]">
        {actionList.map((action) => {
          const actionChecked = allowMulti
            ? checked && selectedActions.includes(action.value)
            : checked &&
              (selectedActions[0] ?? 'default') === action.value
          return (
            <label
              key={action.value}
              className="flex cursor-pointer items-center gap-[8px] text-[14px] leading-[18px] text-ce-black"
            >
              <input
                type={allowMulti ? 'checkbox' : 'radio'}
                name={allowMulti ? `${name}-${value}-${action.value}` : name}
                value={`${value}::${action.value}`}
                checked={actionChecked}
                onChange={() => {
                  if (allowMulti && onToggleAction) {
                    onToggleAction(value, action.value, !actionChecked)
                    return
                  }
                  if (onSelect) {
                    onSelect(value, action.value)
                    return
                  }
                  onChange?.(value)
                }}
                className="ce-visually-hidden"
              />
              <RadioIndicator checked={actionChecked} />
              <span>{action.label}</span>
            </label>
          )
        })}
      </div>
    </div>
  )
}

type PinkChoiceFrameProps = {
  children: ReactNode
  className?: string
  /** Korte intro boven de keuzes (zwart — leestekst) */
  intro?: string
}

/**
 * Enig kader voor een keuze-set: roze fill, zwarte copy.
 * Items erbinnen via `PinkChoiceOption` (radio onderaan, geen extra inspringing).
 */
export function PinkChoiceFrame({ children, className = '', intro }: PinkChoiceFrameProps) {
  return (
    <div className={`min-w-0 rounded-[12px] bg-ce-pink px-[12px] py-[10px] ${className}`.trim()}>
      {intro ? <p className="m-0 mb-[10px] text-[13px] leading-[17px] text-ce-black">{intro}</p> : null}
      <div className="flex min-w-0 flex-col gap-[14px]">{children}</div>
    </div>
  )
}

import { useEffect, useState, type ReactNode } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { getFieldHelp } from '../../config/fieldHelp'
import { designStackClass } from '../../design/identity.constants'

/** Verticale ruimte tussen invulvelden: max één witregel. */
export const FORM_FIELD_STACK_CLASS = designStackClass

export type FieldHintTone = 'beige' | 'red' | 'green' | 'black'

type FieldHintProps = {
  children?: ReactNode
  helpKey?: string
  position?: 'above' | 'below'
  tone?: FieldHintTone
}

function toneClass(tone: FieldHintTone): string {
  if (tone === 'red') return 'text-ce-red'
  if (tone === 'green') return 'text-ce-green-text'
  if (tone === 'black') return 'text-ce-black'
  return 'text-ce-beige'
}

function FieldHintHelpPanel({
  helpKey,
  tone,
}: {
  helpKey: string
  tone: FieldHintTone
}) {
  const location = useLocation()
  const help = getFieldHelp(helpKey)
  const [stepIndex, setStepIndex] = useState(0)
  const steps = help?.steps ?? []
  const hasSteps = steps.length > 0

  useEffect(() => {
    setStepIndex(0)
  }, [helpKey])

  if (!help) return null

  const supportHref = `/support?ask=${encodeURIComponent(helpKey)}&from=${encodeURIComponent(location.pathname)}`

  return (
    <div className={`m-0 mt-[9px] text-[13px] leading-[18px] ${toneClass(tone)}`}>
      <p className="m-0">{help.what}</p>

      {help.looksLike ? (
        <p className="m-0 mt-[8px] text-ce-beige">
          Ziet er zo uit:{' '}
          <span className="font-mono text-[12px] text-ce-black">{help.looksLike}</span>
        </p>
      ) : null}

      {help.where ? <p className="m-0 mt-[8px]">{help.where}</p> : null}

      {help.reassure ? <p className="m-0 mt-[8px]">{help.reassure}</p> : null}

      {hasSteps ? (
        <div className="mt-[10px]">
          <p className="m-0 font-semibold text-ce-black" aria-live="polite">
            Stap {stepIndex + 1} van {steps.length}
          </p>
          <p className="m-0 mt-[6px]">{steps[stepIndex]}</p>
          <div className="mt-[8px] flex flex-wrap items-center gap-[12px]">
            {stepIndex > 0 ? (
              <button
                type="button"
                onClick={() => setStepIndex((i) => Math.max(0, i - 1))}
                className="cursor-pointer border-0 bg-transparent p-0 text-[13px] font-semibold text-ce-blue underline"
              >
                Vorige
              </button>
            ) : null}
            {stepIndex < steps.length - 1 ? (
              <button
                type="button"
                onClick={() => setStepIndex((i) => Math.min(steps.length - 1, i + 1))}
                className="cursor-pointer border-0 bg-transparent p-0 text-[13px] font-semibold text-ce-blue underline"
              >
                Volgende
              </button>
            ) : null}
          </div>
        </div>
      ) : null}

      <p className="m-0 mt-[10px]">
        Kom je er niet uit?{' '}
        <Link to={supportHref} className="font-semibold text-ce-green-text underline">
          Help me verder
        </Link>
      </p>
    </div>
  )
}

/** Uitklapbare toelichting: beige i-bolletje (60%), tekst met 30px L/R en 18px marge t.o.v. het veld. */
export function FieldHint({ children, helpKey, position = 'below', tone = 'beige' }: FieldHintProps) {
  const [open, setOpen] = useState(false)
  const spacing = position === 'above' ? 'mb-[18px]' : 'mt-[18px]'
  const registryHelp = helpKey ? getFieldHelp(helpKey) : undefined
  const hasContent = Boolean(children) || Boolean(registryHelp)

  if (!hasContent) return null

  return (
    <div className={`px-[30px] ${spacing}`}>
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        aria-expanded={open}
        aria-label={open ? 'Toelichting verbergen' : 'Toelichting tonen'}
        className="flex h-[11px] w-[11px] shrink-0 cursor-pointer items-center justify-center rounded-full border-0 bg-ce-beige p-0 text-[7px] font-bold leading-none text-ce-black hover:opacity-80 focus:outline-none focus-visible:ring-[1.5px] focus-visible:ring-ce-blue focus-visible:ring-offset-2"
      >
        i
      </button>
      {open && helpKey && registryHelp ? (
        <FieldHintHelpPanel helpKey={helpKey} tone={tone} />
      ) : null}
      {open && !helpKey && children ? (
        <p className={`m-0 mt-[9px] text-[13px] leading-[18px] ${toneClass(tone)}`}>{children}</p>
      ) : null}
    </div>
  )
}

import { useEffect, useState } from 'react'
import { borderedSectionClass, borderedSectionStackClass, designStackClass } from '../design/identity.constants'
import { LIST_ROW } from '../layout/grid.constants'
import { PillButton, pillActionsRowClass } from './PillButton'
import { StrategyField } from './ui/Field'
import { RadioIndicator } from './ui/RadioOption'
import { fetchCalibrationPair, type CalibrationArchitecture } from '../api/hbaV2'
import { usableHbaExample } from '../utils/hbaCalibrationExamples'

type IdentityCalibrationStepProps = {
  goedeVoorbeelden: [string, string, string]
  zwakkeVoorbeelden: [string, string, string]
  architecture?: CalibrationArchitecture
  onChange: (good: [string, string, string], weak: [string, string, string]) => void
}

export function IdentityCalibrationStep({
  goedeVoorbeelden,
  zwakkeVoorbeelden,
  architecture,
  onChange,
}: IdentityCalibrationStepProps) {
  const usableGood = goedeVoorbeelden.map(usableHbaExample) as [string, string, string]
  const usableWeak = zwakkeVoorbeelden.map(usableHbaExample) as [string, string, string]
  const completed = [0, 1, 2].filter((index) => usableGood[index] && usableWeak[index]).length
  const round = Math.min(completed, 2)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [optionA, setOptionA] = useState('')
  const [optionB, setOptionB] = useState('')
  const [customMode, setCustomMode] = useState(false)
  const [customGood, setCustomGood] = useState('')
  const [customWeak, setCustomWeak] = useState('')
  const [selectedChoice, setSelectedChoice] = useState<'a' | 'b' | null>(null)

  useEffect(() => {
    setSelectedChoice(null)
  }, [round, optionA, optionB])

  useEffect(() => {
    if (completed >= 3 || customMode) return
    setLoading(true)
    setError(null)
    void fetchCalibrationPair(round, architecture)
      .then((res) => {
        if (!res.ok) {
          setError(res.error ?? 'Laden mislukt')
          return
        }
        const strong = usableHbaExample(res.optionA ?? '')
        const weak = usableHbaExample(res.optionB ?? '')
        if (!strong || !weak) {
          setError('De voorbeelden klonken nog niet als jullie merk. Probeer opnieuw, of typ ze zelf.')
          return
        }
        setOptionA(strong)
        setOptionB(weak)
      })
      .finally(() => setLoading(false))
  }, [round, completed, customMode, architecture])

  function pick(good: string, weak: string, choice: 'a' | 'b') {
    setSelectedChoice(choice)
    const nextGood = [...goedeVoorbeelden] as [string, string, string]
    const nextWeak = [...zwakkeVoorbeelden] as [string, string, string]
    nextGood[round] = good
    nextWeak[round] = weak
    onChange(nextGood, nextWeak)
    setCustomMode(false)
    setCustomGood('')
    setCustomWeak('')
  }

  if (completed >= 3) {
    return (
      <div className={designStackClass}>
        <p className="m-0 text-[14px] leading-[18px] text-ce-black">Drie paren gekozen. Je kunt ze hieronder nog aanpassen.</p>
        <div className={borderedSectionStackClass}>
          {[0, 1, 2].flatMap((index) => [
            <StrategyField
              key={`good-${index}`}
              id={`good-${index}`}
              title={`Sterk voorbeeld ${index + 1}`}
              value={goedeVoorbeelden[index] ?? ''}
              onChange={(v) => {
                const nextGood = [...goedeVoorbeelden] as [string, string, string]
                nextGood[index] = v
                onChange(nextGood, zwakkeVoorbeelden)
              }}
              minHeight={60}
            />,
            <StrategyField
              key={`weak-${index}`}
              id={`weak-${index}`}
              title={`Zwak voorbeeld ${index + 1}`}
              value={zwakkeVoorbeelden[index] ?? ''}
              onChange={(v) => {
                const nextWeak = [...zwakkeVoorbeelden] as [string, string, string]
                nextWeak[index] = v
                onChange(goedeVoorbeelden, nextWeak)
              }}
              minHeight={60}
            />,
          ])}
        </div>
      </div>
    )
  }

  if (customMode) {
    return (
      <div className="grid gap-[12px]">
        <p className="m-0 text-[14px] leading-[18px] text-ce-black">
          Typ zelf een sterk en een zwak voorbeeld (ronde {round + 1} van 3).
        </p>
        <StrategyField id="custom-good" title="Klinkt als jullie" value={customGood} onChange={setCustomGood} minHeight={70} />
        <StrategyField id="custom-weak" title="Zit er net naast" value={customWeak} onChange={setCustomWeak} minHeight={70} />
        <div className={pillActionsRowClass}>
          <PillButton
            type="button"
            disabled={!customGood.trim() || !customWeak.trim()}
            onClick={() => {
              pick(customGood.trim(), customWeak.trim(), 'a')
            }}
          >
            Opslaan
          </PillButton>
          <PillButton type="button" variant="second" onClick={() => setCustomMode(false)}>
            Terug naar keuze
          </PillButton>
        </div>
      </div>
    )
  }

  return (
    <div className={designStackClass}>
      <p className="m-0 text-[14px] leading-[18px] text-ce-black">
        Ronde {round + 1} van 3 — welke zin klinkt het meest als jullie?
      </p>
      {loading ? <p className="m-0 text-[14px] text-ce-beige">Voorbeelden laden…</p> : null}
      {error ? <p className="m-0 text-[14px] text-ce-red">{error}</p> : null}
      {!loading && optionA && optionB ? (
        <div className={borderedSectionStackClass}>
          {(
            [
              { choice: 'a' as const, text: optionA, good: optionA, weak: optionB },
              { choice: 'b' as const, text: optionB, good: optionB, weak: optionA },
            ] as const
          ).map((row) => (
            <section key={row.choice} className={borderedSectionClass}>
              <button
                type="button"
                className={`${LIST_ROW.rowClass} w-full cursor-pointer items-start gap-[16px] border-0 bg-transparent p-0 text-left`}
                onClick={() => pick(row.good, row.weak, row.choice)}
              >
                <span className="mt-[4px] shrink-0">
                  <RadioIndicator checked={selectedChoice === row.choice} />
                </span>
                <span className="min-w-0 flex-1 text-[14px] leading-[18px] text-ce-black">{row.text}</span>
                <span className={`${LIST_ROW.actionsClass} pt-[2px] text-[14px] font-semibold text-ce-blue`}>
                  Selecteer deze zin
                </span>
              </button>
            </section>
          ))}
        </div>
      ) : null}
      <button
        type="button"
        className="self-start border-0 bg-transparent p-0 text-[14px] font-semibold text-ce-blue underline"
        onClick={() => setCustomMode(true)}
      >
        Geen van beide — ik typ het zelf
      </button>
    </div>
  )
}

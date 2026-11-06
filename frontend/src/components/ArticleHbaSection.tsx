import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { MetaSection } from './MetaSection'
import { LoadingDots } from './LoadingDots'
import { PillButton } from './PillButton'
import { Pulldown } from './ui/Pulldown'
import { PageNavLink } from './ui/PageNavLink'
import { HBA_CONTENT_ROLES, hbaContentrolLabel, hbaNarrativeFieldLabel } from '../constants/hba'
import type { BrandNarrativeCompleteness } from '../types/onboarding'
import type { HbaScoreDto } from '../types/api'
import {
  architectureIntegrityFromRisk,
  buildHbaFinding,
  formatHbaScoreValue,
  hbaArchitectureFlagsForDisplay,
  hbaFlagSentence,
  hbaNeutralMerkAdvies,
  hbaScoreValueHint,
  hbaHoofdoordeelKlantlabel,
  hbaHoofdoordeelKlantzin,
  type HbaScoreDisplayMode,
} from '../utils/hbaScoreDisplay'

type ArticleHbaSectionProps = {
  hbaScore: HbaScoreDto | null | undefined
  scoreDisplayMode?: HbaScoreDisplayMode
  contentrolOverride: string | null
  narrativeCompleteness: BrandNarrativeCompleteness | null | undefined
  readonly?: boolean
  onContentrolOverrideChange: (value: string | null) => void
  onRescore: () => Promise<void>
  narrativeScoreBlockers?: string[] | null
  scoreAutoWaiting?: boolean
  rescoring?: boolean
  positioningArchetype?: string | null
  /** A6: toon merkdoel i.p.v. Jung-archetype. */
  positioningBrandGoal?: string | null
  /** Staging-preview: één klantzin i.p.v. flags (query ?hbaKlant=1). */
  clientHoofdoordeelPreview?: boolean
  /** A8: geen HBA-contentrol tonen aan klant. */
  hideHbaContentrol?: boolean
}

function scoreBarColor(value: number): string {
  if (value <= 4) return 'bg-ce-red'
  if (value < 7) return 'bg-ce-beige'
  return 'bg-ce-green'
}

function ScoreBar({
  label,
  value,
  displayMode = 'band',
}: {
  label: string
  value: number | null
  displayMode?: HbaScoreDisplayMode
}) {
  if (value === null || Number.isNaN(value)) {
    return (
      <div>
        <p className="m-0 text-[14px] font-semibold leading-[18px] text-ce-black">{label}</p>
        <p className="m-0 pt-[4px] text-[14px] leading-[18px] text-ce-beige">—</p>
      </div>
    )
  }

  const pct = Math.max(0, Math.min(100, (value / 10) * 100))
  const barColor = scoreBarColor(value)
  const displayValue = formatHbaScoreValue(value, displayMode)

  return (
    <div>
      <div className="flex items-baseline justify-between gap-[8px]">
        <p className="m-0 text-[14px] font-semibold leading-[18px] text-ce-black">{label}</p>
        <p className="m-0 text-[14px] font-semibold leading-[18px] text-ce-black">
          {displayValue}
          {displayMode === 'decimal' ? (
            <span className="font-normal text-ce-beige">/10</span>
          ) : null}
        </p>
      </div>
      <div className="mt-[6px] h-[8px] w-full overflow-hidden rounded-full bg-ce-pink">
        <div className={`h-full rounded-full ${barColor}`} style={{ width: `${pct}%` }} />
      </div>
    </div>
  )
}

export function ArticleHbaSection({
  hbaScore,
  scoreDisplayMode = 'band',
  contentrolOverride,
  narrativeCompleteness,
  readonly = false,
  onContentrolOverrideChange,
  onRescore,
  narrativeScoreBlockers = null,
  scoreAutoWaiting = false,
  rescoring = false,
  positioningArchetype: _positioningArchetype = null,
  positioningBrandGoal = null,
  clientHoofdoordeelPreview = false,
  hideHbaContentrol = false,
}: ArticleHbaSectionProps) {
  const [open, setOpen] = useState(true)
  const [copied, setCopied] = useState(false)
  const [extraFlagsOpen, setExtraFlagsOpen] = useState(false)

  const incomplete = narrativeCompleteness && !narrativeCompleteness.complete
  const missing = narrativeCompleteness?.missing ?? []
  const staleIncompleteScore =
    Boolean(narrativeCompleteness?.complete) && hbaScore?.status === 'narrative_incomplete'
  const showScore = hbaScore && !staleIncompleteScore
  const scoreBlockers = (narrativeScoreBlockers ?? []).filter((line) => line.trim() !== '')
  const blockedByNarrativeQuality = scoreBlockers.length > 0

  const effectiveContentrol = contentrolOverride ?? hbaScore?.contentrol ?? null
  const contentrolText = hbaContentrolLabel(effectiveContentrol)
  const motivation = hbaScore?.contentrolMotivation?.trim() ?? ''

  const integrity = architectureIntegrityFromRisk(
    hbaScore?.architectureRisk,
    hbaScore?.architectureIntegrity,
  )
  const hoofdoordeel = hbaScore?.hoofdoordeel ?? null
  const rankedFlags = hbaArchitectureFlagsForDisplay(hoofdoordeel, hbaScore?.architectureRiskFlags)
  const neutralMerkAdvies = hbaNeutralMerkAdvies(hoofdoordeel, hbaScore?.architectureRiskFlags)
  const finding = buildHbaFinding({
    hoofdoordeel,
    diagnose: hbaScore?.diagnose,
    flags: hbaScore?.architectureRiskFlags,
    brandContribution: hbaScore?.brandContribution,
    brandConsistency: hbaScore?.brandConsistency,
    architectureIntegrity: integrity,
  })
  const visibleFlags = rankedFlags.slice(0, 2)
  const extraFlags = rankedFlags.slice(2)
  const findingIsFlag = finding.text === (rankedFlags[0] ? hbaFlagSentence(rankedFlags[0]) : '')
  const listedFlags = findingIsFlag ? visibleFlags.slice(1) : visibleFlags

  const contentrolAutoLabel = useMemo(
    () => `Automatisch (${hbaContentrolLabel(hbaScore?.contentrol) || '—'})`,
    [hbaScore?.contentrol],
  )
  const contentrolPulldownOptions = useMemo(
    () => [contentrolAutoLabel, ...HBA_CONTENT_ROLES.map((role) => role.label)],
    [contentrolAutoLabel],
  )
  const contentrolPulldownValue = contentrolOverride
    ? hbaContentrolLabel(contentrolOverride) || contentrolOverride
    : contentrolAutoLabel

  function handleContentrolPulldownChange(label: string) {
    if (label === contentrolAutoLabel) {
      onContentrolOverrideChange(null)
      return
    }
    const role = HBA_CONTENT_ROLES.find((item) => item.label === label)
    onContentrolOverrideChange(role?.value ?? null)
  }

  async function copyAdvice() {
    const text = hbaScore?.verbeteradvies?.trim()
    if (!text) return
    try {
      await navigator.clipboard.writeText(text)
      setCopied(true)
      window.setTimeout(() => setCopied(false), 2000)
    } catch {
      // ignore clipboard errors
    }
  }

  const modeHint = hbaScoreValueHint(scoreDisplayMode)
  const klantlabel =
    hbaScore?.hoofdoordeelKlantlabel?.trim() ||
    hbaHoofdoordeelKlantlabel(hbaScore?.hoofdoordeel)
  const klantzin =
    hbaScore?.hoofdoordeelKlantzin?.trim() ||
    hbaHoofdoordeelKlantzin(hbaScore?.hoofdoordeel)
  const showKlantPreview = Boolean(clientHoofdoordeelPreview && klantzin && showScore)

  const brandGoalText = (positioningBrandGoal ?? '').trim()

  return (
    <MetaSection label="Merkarchitectuur" open={open} onToggle={() => setOpen((v) => !v)}>
      {brandGoalText && !hideHbaContentrol ? (
        <p className="m-0 mb-[8px] text-[14px] leading-[18px] text-ce-beige">
          Je merkdoel:{' '}
          <span className="font-semibold text-ce-black">{brandGoalText}</span>
        </p>
      ) : null}
      {incomplete ? (
        <div className="mt-[8px] rounded-[12px] bg-ce-pink px-[12px] py-[10px]">
          <p className="m-0 text-[14px] font-semibold leading-[18px] text-ce-red">
            Merkarchitectuur nog niet compleet — scoring uitgesteld.
          </p>
          {missing.length > 0 ? (
            <p className="m-0 pt-[6px] text-[14px] leading-[18px] text-ce-black">
              Ontbreekt: {missing.map(hbaNarrativeFieldLabel).join(', ')}
            </p>
          ) : null}
          <p className="m-0 pt-[8px] text-[14px] leading-[18px]">
            <Link to="/brandarchitecture#grenzen-taal" className="font-semibold text-ce-blue underline">
              Vul Merkarchitectuur aan
            </Link>
          </p>
        </div>
      ) : blockedByNarrativeQuality || staleIncompleteScore ? (
        <div className="mt-[8px] rounded-[12px] bg-ce-pink px-[12px] py-[10px]">
          <p className="m-0 text-[14px] font-semibold leading-[18px] text-ce-red">
            {blockedByNarrativeQuality
              ? 'HBA kan deze post nog niet scoren.'
              : 'Deze post is nog niet opnieuw beoordeeld.'}
          </p>
          {blockedByNarrativeQuality ? (
            <>
              <p className="m-0 pt-[6px] text-[14px] leading-[18px] text-ce-black">
                Scherp deze punten in Merkarchitectuur aan (spanning, hoe je het waarmaakt, bewijs — geen
                sjabloontekst of kopie van waar je tegen vecht):
              </p>
              <ul className="m-0 mt-[8px] list-disc space-y-[6px] pl-[18px] text-[14px] leading-[18px] text-ce-black">
                {scoreBlockers.map((line) => (
                  <li key={line}>{line}</li>
                ))}
              </ul>
            </>
          ) : (
            <p className="m-0 pt-[6px] text-[14px] leading-[18px] text-ce-black">
              Je merkarchitectuur is inmiddels compleet; de vorige meting liep nog op een eerdere versie.
            </p>
          )}
          <p className="m-0 pt-[8px] text-[14px] leading-[18px]">
            <Link to="/brandarchitecture" className="font-semibold text-ce-blue underline">
              Open Merkarchitectuur
            </Link>
            {blockedByNarrativeQuality ? ' — pas aan en klik Opslaan, daarna hier opnieuw beoordelen.' : null}
          </p>
          {!readonly ? (
            <div className="mt-[12px]">
              <PillButton variant="main" disabled={rescoring} loading={rescoring} onClick={() => void onRescore()}>
                Beoordeel post opnieuw
              </PillButton>
            </div>
          ) : null}
        </div>
      ) : !showScore ? (
        <div className="mt-[8px]">
          <p className="m-0 text-[14px] leading-[18px] text-ce-black">
            De merkbeoordeling voor deze post ontbreekt nog.
            {scoreAutoWaiting
              ? ' Die verschijnt meestal vanzelf zodra je artikel klaar is.'
              : ' Start de beoordeling handmatig als die niet vanzelf komt.'}
          </p>
          {scoreAutoWaiting ? (
            <div className="pt-[10px]">
              <LoadingDots label="Beoordeling volgt" className="text-ce-black" />
            </div>
          ) : null}
          {!readonly && !scoreAutoWaiting ? (
            <div className="mt-[12px]">
              <PillButton variant="main" disabled={rescoring} loading={rescoring} onClick={() => void onRescore()}>
                Beoordeel post
              </PillButton>
            </div>
          ) : null}
        </div>
      ) : showKlantPreview ? (
        <div className="mt-[8px] flex flex-col gap-[16px]">
          <p className="m-0 rounded-[12px] bg-ce-pink px-[12px] py-[10px] text-[14px] font-semibold leading-[18px] text-ce-black">
            {klantlabel ? `${klantlabel}. ` : ''}
            {klantzin}
          </p>
          {neutralMerkAdvies ? (
            <p className="m-0 rounded-[12px] border border-ce-black/10 px-[12px] py-[10px] text-[14px] leading-[18px] text-ce-black">
              {neutralMerkAdvies}
            </p>
          ) : null}
          {hbaScore.verbeteradvies?.trim() &&
          hbaScore.hoofdoordeel &&
          !['neutraal', 'op_merk'].includes(hbaScore.hoofdoordeel) ? (
            <div className="rounded-[12px] border border-ce-black/10 px-[12px] py-[10px]">
              <p className="m-0 text-[14px] font-semibold leading-[18px] text-ce-black">Tip</p>
              <p className="m-0 pt-[6px] whitespace-pre-line text-[14px] leading-[18px] text-ce-black">
                {hbaScore.verbeteradvies}
              </p>
            </div>
          ) : null}
          <p className="m-0 text-[12px] leading-[16px] text-ce-beige">
            Preview klantweergave (staging, ?hbaKlant=1) — scores en flags blijven verborgen.
          </p>
        </div>
      ) : (
        <div className="mt-[8px] flex flex-col gap-[16px]">
          <div>
            <p className="m-0 text-[14px] font-semibold leading-[18px] text-ce-black">{finding.text}</p>
            {neutralMerkAdvies ? (
              <p className="m-0 pt-[8px] text-[14px] leading-[18px] text-ce-beige">{neutralMerkAdvies}</p>
            ) : null}
            {finding.cause ? (
              <p className="m-0 pt-[6px] text-[14px] leading-[18px]">
                <PageNavLink to={finding.cause.href}>{finding.cause.label}</PageNavLink>
              </p>
            ) : null}
            {listedFlags.length > 0 || extraFlags.length > 0 ? (
              <ul className="m-0 mt-[8px] list-none space-y-[6px] p-0">
                {listedFlags.map((flag) => (
                  <li key={flag} className="text-[14px] leading-[18px] text-ce-black">
                    {hbaFlagSentence(flag)}
                  </li>
                ))}
                {extraFlagsOpen
                  ? extraFlags.map((flag) => (
                      <li key={flag} className="text-[14px] leading-[18px] text-ce-black">
                        {hbaFlagSentence(flag)}
                      </li>
                    ))
                  : null}
              </ul>
            ) : null}
            {extraFlags.length > 0 ? (
              <button
                type="button"
                className="mt-[6px] border-0 bg-transparent p-0 text-[14px] font-semibold text-ce-blue underline"
                onClick={() => setExtraFlagsOpen((v) => !v)}
              >
                {extraFlagsOpen ? 'Verberg extra signalen' : `Nog ${extraFlags.length} signaal${extraFlags.length === 1 ? '' : 'en'}`}
              </button>
            ) : null}
          </div>

          {!hideHbaContentrol ? (
            <div>
              <p className="m-0 text-[14px] leading-[18px] text-ce-black">
                <span className="font-semibold">Beoordeeld als:</span>{' '}
                {contentrolText || '—'}
                {motivation ? ` — ${motivation}` : ''}
              </p>
              {!readonly && (
                <div className="mt-[8px]">
                  <span className="mb-[4px] block text-[12px] leading-[16px] text-ce-beige">
                    Wijzig rol (override)
                  </span>
                  <Pulldown
                    options={contentrolPulldownOptions}
                    value={contentrolPulldownValue}
                    onChange={handleContentrolPulldownChange}
                  />
                </div>
              )}
            </div>
          ) : null}

          <details className="group border-t border-ce-black/10 pt-[12px]">
            <summary className="cursor-pointer list-none text-[14px] font-semibold leading-[18px] text-ce-black [&::-webkit-details-marker]:hidden">
              Opbouw
            </summary>
            <div className="mt-[12px] grid gap-[12px]">
              <ScoreBar
                label="Brand Contribution"
                value={hbaScore.brandContribution ?? null}
                displayMode={scoreDisplayMode}
              />
              <ScoreBar
                label="Brand Consistency"
                value={hbaScore.brandConsistency ?? null}
                displayMode={scoreDisplayMode}
              />
              <ScoreBar
                label="Architectuurbestendigheid"
                value={integrity}
                displayMode={scoreDisplayMode}
              />
              {modeHint ? (
                <p className="m-0 text-[12px] leading-[16px] text-ce-beige">{modeHint}</p>
              ) : null}
              {hbaScore.verbeteradvies?.trim() ? (
                <div className="rounded-[12px] bg-ce-pink px-[12px] py-[10px]">
                  <div className="flex items-start justify-between gap-[8px]">
                    <p className="m-0 text-[14px] font-semibold leading-[18px] text-ce-black">Verbeteradvies</p>
                    <button
                      type="button"
                      className="shrink-0 border-0 bg-transparent p-0 text-[12px] font-semibold text-ce-blue underline"
                      onClick={() => void copyAdvice()}
                    >
                      {copied ? 'Gekopieerd' : 'Kopieer'}
                    </button>
                  </div>
                  <p className="m-0 pt-[6px] whitespace-pre-line text-[14px] leading-[18px] text-ce-black">
                    {hbaScore.verbeteradvies}
                  </p>
                </div>
              ) : null}
            </div>
          </details>

          {!readonly && (
            <PillButton variant="second" disabled={rescoring} loading={rescoring} onClick={() => void onRescore()}>
              Beoordeel opnieuw
            </PillButton>
          )}
        </div>
      )}
    </MetaSection>
  )
}

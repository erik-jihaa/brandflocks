import { borderedSectionClass } from '../design/identity.constants'
import { LoadingDots } from './LoadingDots'
import { PillButton } from './PillButton'
import { CheckboxOption, RadioOption } from './ui/RadioOption'
import { verdictPillClass } from './ArticleHbaHintSection'
import type { HbaArchitectureCheckResult } from '../api/hbaV2'

const FRAME_CLASS = `${borderedSectionClass} bg-transparent font-figtree font-normal`
const TITLE_CLASS = 'm-0 mb-[8px] text-[14px] leading-[18px] font-semibold text-ce-black'

const DIMENSION_LABELS: Record<string, string> = {
  krachtTriangle: 'Samenhang persoonlijkheid, doel en waar je tegen vecht',
  relevantieTegenkracht: 'Relevantie van waar je tegen vecht',
  relevantieRol: 'Relevantie persoonlijkheid',
  scherpteEnUniciteit: 'Scherpte en uniciteit',
}

export const ARCHITECTURE_CHECK_STEP: Record<string, number> = {
  krachtTriangle: 5,
  relevantieTegenkracht: 5,
  relevantieRol: 5,
  scherpteEnUniciteit: 2,
  regelsOordeel: 4,
}

function ordeelLabel(oordeel: string): string {
  if (oordeel === 'sterk') return 'Sterk'
  if (oordeel === 'voldoende') return 'Voldoende'
  if (oordeel === 'aanscherpen') return 'Aanscherpen'
  return oordeel
}

type DimensionBlockProps = {
  title: string
  dimensionKey: string
  oordeel: string
  toelichting: string
  verbetering: string
  onAanscherpen?: (step: number) => void
}

function DimensionBlock({
  title,
  dimensionKey,
  oordeel,
  toelichting,
  verbetering,
  onAanscherpen,
}: DimensionBlockProps) {
  const step = ARCHITECTURE_CHECK_STEP[dimensionKey]
  const showAanscherpen = oordeel === 'aanscherpen' && verbetering.trim() !== '' && step !== undefined

  return (
    <section className={FRAME_CLASS}>
      <div className="flex flex-wrap items-center gap-[8px]">
        <p className={TITLE_CLASS}>{title}</p>
        {oordeel ? (
          <span
            className={`inline-flex rounded-full px-[10px] py-[2px] text-[12px] leading-[16px] ${verdictPillClass(
              oordeel === 'sterk' ? 'versterkt' : oordeel === 'aanscherpen' ? 'verwatert' : 'neutraal',
            )}`}
          >
            {ordeelLabel(oordeel)}
          </span>
        ) : null}
      </div>
      {toelichting ? (
        <p className="m-0 text-[14px] leading-[18px] text-ce-black">{toelichting}</p>
      ) : null}
      {showAanscherpen ? (
        <>
          <p className="m-0 mt-[8px] text-[14px] leading-[18px] text-ce-beige">{verbetering}</p>
          {onAanscherpen ? (
            <div className="mt-[12px]">
              <PillButton type="button" variant="second" onClick={() => onAanscherpen(step)}>
                Aanscherpen
              </PillButton>
            </div>
          ) : null}
        </>
      ) : null}
    </section>
  )
}

export type HbaVoorbeeldSuggestionPick = {
  type: 'goed' | 'zwak'
  tekst: string
}

type HbaArchitectureCheckPanelProps = {
  check: HbaArchitectureCheckResult | null
  loading?: boolean
  error?: string | null
  onRetry?: () => void
  onAanscherpen?: (step: number) => void
  /** Stap Bevestigen: kies suggesties; pas toe bij “Vastleggen en beginnen”. */
  selectedToneSuggestion?: string | null
  onSelectToneSuggestion?: (suggestion: string | null) => void
  selectedVoorbeeldPicks?: HbaVoorbeeldSuggestionPick[]
  onToggleVoorbeeldPick?: (pick: HbaVoorbeeldSuggestionPick, selected: boolean) => void
  onStrategist?: () => void
  /** Overzichtspagina: geen interne kop en geen selectie-UI voor suggesties. */
  showSummaryHeading?: boolean
  selectionMode?: boolean
}

export function HbaArchitectureCheckPanel({
  check,
  loading = false,
  error = null,
  onRetry,
  onAanscherpen,
  selectedToneSuggestion = null,
  onSelectToneSuggestion,
  selectedVoorbeeldPicks = [],
  onToggleVoorbeeldPick,
  onStrategist,
  showSummaryHeading = true,
  selectionMode = true,
}: HbaArchitectureCheckPanelProps) {
  const toneSelectionEnabled = Boolean(onSelectToneSuggestion) && selectionMode
  const voorbeeldSelectionEnabled = Boolean(onToggleVoorbeeldPick) && selectionMode

  function voorbeeldSelected(pick: HbaVoorbeeldSuggestionPick): boolean {
    return selectedVoorbeeldPicks.some(
      (item) => item.type === pick.type && item.tekst === pick.tekst,
    )
  }
  if (loading) {
    return (
      <section className={FRAME_CLASS}>
        <LoadingDots label="We toetsen je merkarchitectuur…" className="text-ce-black" />
      </section>
    )
  }

  if (error && !check) {
    return (
      <section className={FRAME_CLASS}>
        {showSummaryHeading ? <p className={TITLE_CLASS}>Kwaliteitscheck</p> : null}
        <p className="m-0 text-[14px] leading-[18px] text-ce-black">{error}</p>
        {onRetry ? (
          <div className="mt-[12px]">
            <PillButton type="button" variant="second" onClick={onRetry}>
              Opnieuw
            </PillButton>
          </div>
        ) : null}
      </section>
    )
  }

  if (!check) {
    return null
  }

  const showPremiumNotice =
    check.totaaloordeel === 'meetlat_te_zwak' ||
    check.premiumLaagAangeraden ||
    check.dimensies.scherpteEnUniciteit.oordeel === 'aanscherpen'

  const meetlatActief =
    check.meetlatStatus === 'actief' ||
    (check.meetlatStatus == null && check.guardrailActive === true)

  return (
    <div className="grid w-full basis-full gap-[12px]">
      {showSummaryHeading ? (
        <p className="m-0 text-[14px] leading-[18px] font-semibold text-ce-black">Kwaliteitscheck</p>
      ) : null}

      {check.meetlatStatus != null ? (
        <p className="m-0 text-[14px] leading-[18px] text-ce-black">
          {meetlatActief
            ? 'Meetlat actief — content meten kan.'
            : 'Meetlat nog geblokkeerd — je ziet wel het oordeel en het advies; pas velden aan en draai opnieuw.'}
        </p>
      ) : null}

      {check.signalen?.tegenkrachtOntbreekt && check.signaalAdvies?.tegenkrachtOntbreekt ? (
        <section className={FRAME_CLASS}>
          <p className={TITLE_CLASS}>Tegenkracht ontbreekt</p>
          <p className="m-0 text-[14px] leading-[18px] text-ce-black">
            {check.signaalAdvies.tegenkrachtOntbreekt}
          </p>
        </section>
      ) : null}

      {check.signalen?.regelOntbreekt && check.signaalAdvies?.regelOntbreekt ? (
        <section className={FRAME_CLASS}>
          <p className={TITLE_CLASS}>Regel ontbreekt</p>
          <p className="m-0 text-[14px] leading-[18px] text-ce-black">{check.signaalAdvies.regelOntbreekt}</p>
        </section>
      ) : null}

      {check.identityGate && !check.identityGate.pass ? (
        <section className={FRAME_CLASS}>
          <p className={TITLE_CLASS}>Eerst het denkniveau</p>
          {check.identityGate.goal.level === 'wrong_level' ? (
            <p className="m-0 text-[14px] leading-[18px] text-ce-black">
              Doel: {check.identityGate.goal.feedback}
            </p>
          ) : null}
          {check.identityGate.foe.level === 'wrong_level' ? (
            <p className="m-0 mt-[8px] text-[14px] leading-[18px] text-ce-black">
              Tegenkracht: {check.identityGate.foe.feedback}
            </p>
          ) : null}
          {check.returnAction ? (
            <p className="m-0 mt-[8px] text-[14px] leading-[18px] text-ce-beige">{check.returnAction}</p>
          ) : null}
        </section>
      ) : null}

      {check.identityGate?.pass && check.identityGate.goal.level === 'identity_weak' ? (
        <p className="m-0 text-[14px] leading-[18px] text-ce-black">{check.identityGate.goal.feedback}</p>
      ) : null}
      {check.identityGate?.pass && check.identityGate.foe.level === 'identity_weak' ? (
        <p className="m-0 text-[14px] leading-[18px] text-ce-black">{check.identityGate.foe.feedback}</p>
      ) : null}

      {check.decision && check.meetlatStatus == null ? (
        <p className="m-0 text-[14px] leading-[18px] text-ce-black">
          {check.decision === 'PASS'
            ? `Meetlat actief${check.totalScore != null ? ` — ${check.totalScore}/18` : ''}`
            : check.decision === 'REVIEW'
              ? `Nog aanscherpen${check.totalScore != null ? ` — ${check.totalScore}/18` : ''}`
              : `Nog niet als meetlat actief${check.totalScore != null ? ` — ${check.totalScore}/18` : ''}`}
        </p>
      ) : null}
      {check.decision && check.meetlatStatus != null && check.totalScore != null ? (
        <p className="m-0 text-[14px] leading-[18px] text-ce-beige">
          Architectuurscore (intern): {check.totalScore}/18
        </p>
      ) : null}
      {check.hardFailReason ? (
        <p className="m-0 text-[14px] leading-[18px] text-ce-beige">{check.hardFailReason}</p>
      ) : null}
      {check.marktcontextOnvoldoende ? (
        <p className="m-0 text-[14px] leading-[18px] text-ce-beige">
          Marktcontext onvoldoende — uniciteit wordt niet hard beoordeeld.
        </p>
      ) : null}

      {showPremiumNotice ? (
        <p className="m-0 text-[14px] leading-[18px] text-ce-beige">
          Deze positionering is nog vrij algemeen. De tool kan wel meten, maar de metingen worden
          minder betekenisvol. Wil je een merkstrateeg laten meekijken?
          {onStrategist ? (
            <>
              {' '}
              <button
                type="button"
                className="border-0 bg-transparent p-0 font-semibold text-ce-blue underline"
                onClick={onStrategist}
              >
                Vraag een strateeg
              </button>
            </>
          ) : null}
        </p>
      ) : null}

      {(Object.keys(DIMENSION_LABELS) as Array<keyof typeof DIMENSION_LABELS>).map((key) => {
        const dim = check.dimensies[key as keyof typeof check.dimensies]
        if (!dim?.oordeel && !dim?.toelichting) return null
        return (
          <DimensionBlock
            key={key}
            title={DIMENSION_LABELS[key]}
            dimensionKey={key}
            oordeel={dim.oordeel}
            toelichting={dim.toelichting}
            verbetering={dim.verbetering}
            onAanscherpen={onAanscherpen}
          />
        )
      })}

      {check.regelsOordeel.oordeel || check.regelsOordeel.toelichting ? (
        <DimensionBlock
          title="Je regels"
          dimensionKey="regelsOordeel"
          oordeel={check.regelsOordeel.oordeel}
          toelichting={check.regelsOordeel.toelichting}
          verbetering={check.regelsOordeel.verbetering}
          onAanscherpen={onAanscherpen}
        />
      ) : null}

      {check.toonSuggesties.length > 0 && selectionMode ? (
        <section className={FRAME_CLASS}>
          <p className={TITLE_CLASS}>Suggesties voor je toon</p>
          {toneSelectionEnabled ? (
            <p className="m-0 mb-[8px] text-[13px] leading-[17px] text-ce-beige">
              Kies hoogstens één suggestie — die nemen we mee bij Vastleggen en beginnen.
            </p>
          ) : null}
          <ul className="m-0 list-none space-y-[10px] p-0">
            {toneSelectionEnabled ? (
              <li>
                <RadioOption
                  name="hba-confirm-tone"
                  value="__none__"
                  label="Geen suggestie overnemen"
                  checked={selectedToneSuggestion === null}
                  onChange={() => onSelectToneSuggestion?.(null)}
                />
              </li>
            ) : null}
            {check.toonSuggesties.map((suggestion) => (
              <li key={suggestion} className="text-[14px] leading-[18px] text-ce-black">
                {toneSelectionEnabled ? (
                  <RadioOption
                    name="hba-confirm-tone"
                    value={suggestion}
                    label={suggestion}
                    checked={selectedToneSuggestion === suggestion}
                    onChange={() => onSelectToneSuggestion?.(suggestion)}
                  />
                ) : (
                  suggestion
                )}
              </li>
            ))}
          </ul>
        </section>
      ) : null}

      {check.voorbeeldSuggesties.length > 0 && selectionMode ? (
        <section className={FRAME_CLASS}>
          <p className={TITLE_CLASS}>Suggesties voor voorbeelden</p>
          {voorbeeldSelectionEnabled ? (
            <p className="m-0 mb-[8px] text-[13px] leading-[17px] text-ce-beige">
              Vink aan wat je wilt overnemen — pas toe bij Vastleggen en beginnen.
            </p>
          ) : null}
          <ul className="m-0 list-none space-y-[12px] p-0">
            {check.voorbeeldSuggesties.map((item, index) => {
              const pick: HbaVoorbeeldSuggestionPick = {
                type: item.type === 'zwak' ? 'zwak' : 'goed',
                tekst: item.tekst,
              }
              return (
                <li key={`${item.type}-${index}`} className="text-[14px] leading-[18px] text-ce-black">
                  {voorbeeldSelectionEnabled ? (
                    <CheckboxOption
                      align="start"
                      checked={voorbeeldSelected(pick)}
                      onChange={(selected) => onToggleVoorbeeldPick?.(pick, selected)}
                    >
                      <span>
                        <span className="font-semibold text-ce-blue">
                          {item.type === 'zwak' ? 'Zwak voorbeeld' : 'Goed voorbeeld'}:
                        </span>{' '}
                        {item.tekst}
                      </span>
                    </CheckboxOption>
                  ) : (
                    <>
                      <span className="font-semibold text-ce-blue">
                        {item.type === 'zwak' ? 'Zwak voorbeeld' : 'Goed voorbeeld'}:
                      </span>{' '}
                      {item.tekst}
                    </>
                  )}
                </li>
              )
            })}
          </ul>
        </section>
      ) : null}
    </div>
  )
}

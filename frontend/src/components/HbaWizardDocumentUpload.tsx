import { useRef, useState } from 'react'
import { pillActionsRowInnerClass } from '../design/identity.constants'
import { PillButton } from './PillButton'
import { StrategySection } from './ui/Field'
import { RadioOption } from './ui/RadioOption'

const DOCUMENT_ACCEPT =
  '.pdf,.docx,.pptx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.openxmlformats-officedocument.presentationml.presentation'

const FORMAT_HINT = 'PDF, Word (.docx) of PowerPoint (.pptx), max. 20 MB.'

export type HbaWizardDocumentUploadOptions = {
  clearPreviousAnswers: boolean
}

type HbaWizardDocumentUploadProps = {
  busy: boolean
  error: string | null
  currentFilename?: string | null
  /** Toon keuze om ingevulde wizard-antwoorden te wissen bij een nieuw document. */
  hasExistingAnswers?: boolean
  onUpload: (file: File, options: HbaWizardDocumentUploadOptions) => void
  onSkip?: () => void
  /** Wizard stap 0 vs. onderhoud in overzicht — andere hinttekst. */
  mode?: 'wizard' | 'maintenance'
}

export function HbaWizardDocumentUpload({
  busy,
  error,
  currentFilename,
  hasExistingAnswers = false,
  onUpload,
  onSkip,
  mode = 'wizard',
}: HbaWizardDocumentUploadProps) {
  const inputRef = useRef<HTMLInputElement>(null)
  const [selectedName, setSelectedName] = useState<string | null>(null)
  const [replaceMode, setReplaceMode] = useState(false)
  const [clearPreviousAnswers, setClearPreviousAnswers] = useState(false)
  const hasUpload = Boolean(currentFilename?.trim())
  const displayName = selectedName ?? (hasUpload && !replaceMode ? currentFilename : null)
  const showAnswerChoice = hasExistingAnswers && (replaceMode || !hasUpload)

  function handleFileChange(file: File | undefined) {
    if (!file) return
    setSelectedName(file.name)
    setReplaceMode(false)
    onUpload(file, { clearPreviousAnswers: showAnswerChoice && clearPreviousAnswers })
    setClearPreviousAnswers(false)
  }

  const hint =
    hasUpload && !replaceMode
      ? `We gebruiken dit document voor suggesties per stap. Vervang het document als je strategie is gewijzigd. ${FORMAT_HINT}`
      : mode === 'maintenance'
        ? `Upload een strategiedocument, pitch deck of positioneringsdocument — we gebruiken het voor suggesties en merkcontroles. ${FORMAT_HINT}`
        : `Heb je een strategiedocument, pitch deck of positioneringsdocument? Upload hem — we gebruiken hem om per stap een suggestie te doen. Overslaan kan ook, dan vul je alles zelf in. ${FORMAT_HINT}`

  return (
    <StrategySection title="Strategiedocument (optioneel)" hint={hint}>
      {hasUpload && !replaceMode ? (
        <p className="m-0 mb-[8px] text-[14px] leading-[18px] text-ce-black">
          Geüpload: {currentFilename}
        </p>
      ) : null}
      {error ? (
        <p className="m-0 mb-[8px] text-[14px] leading-[18px] text-ce-red" role="alert">
          {error}
        </p>
      ) : null}
      {displayName && busy ? (
        <p className="m-0 mb-[8px] text-[14px] leading-[18px] text-ce-beige">
          {displayName} — suggesties ophalen…
        </p>
      ) : null}
      {hasUpload && replaceMode ? (
        <p className="m-0 mb-[8px] text-[14px] leading-[18px] text-ce-beige">
          Huidig bestand: {currentFilename}
        </p>
      ) : null}
      {showAnswerChoice ? (
        <div className="mb-[12px] grid gap-[8px]">
          <RadioOption
            name="hba-wizard-doc-answers"
            value="keep"
            label="Behoud mijn ingevulde antwoorden — alleen de suggesties uit het document vernieuwen"
            checked={!clearPreviousAnswers}
            onChange={() => setClearPreviousAnswers(false)}
          />
          <RadioOption
            name="hba-wizard-doc-answers"
            value="clear"
            label="Wis mijn antwoorden — ik begin opnieuw met suggesties uit het nieuwe document"
            checked={clearPreviousAnswers}
            onChange={() => setClearPreviousAnswers(true)}
          />
        </div>
      ) : null}
      <div className={pillActionsRowInnerClass}>
        <input
          ref={inputRef}
          type="file"
          accept={DOCUMENT_ACCEPT}
          className="ce-visually-hidden"
          disabled={busy}
          onChange={(e) => {
            const file = e.target.files?.[0]
            handleFileChange(file)
            e.target.value = ''
          }}
        />
        {hasUpload && !replaceMode ? (
          <PillButton
            type="button"
            variant="second"
            disabled={busy}
            onClick={() => setReplaceMode(true)}
          >
            Ander document uploaden
          </PillButton>
        ) : (
          <>
            <PillButton
              type="button"
              variant={selectedName ? 'second' : 'main'}
              disabled={busy}
              loading={busy}
              onClick={() => inputRef.current?.click()}
            >
              {hasUpload ? 'Kies nieuw bestand' : 'Bladeren'}
            </PillButton>
            {hasUpload && replaceMode ? (
              <PillButton
                type="button"
                variant="second"
                disabled={busy}
                onClick={() => {
                  setReplaceMode(false)
                  setSelectedName(null)
                  setClearPreviousAnswers(false)
                }}
              >
                Annuleren
              </PillButton>
            ) : onSkip ? (
              <PillButton type="button" variant="second" disabled={busy} onClick={onSkip}>
                Overslaan
              </PillButton>
            ) : null}
          </>
        )}
      </div>
    </StrategySection>
  )
}

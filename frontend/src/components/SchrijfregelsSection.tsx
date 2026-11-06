import { StrategyField, StrategySection } from './ui/Field'
import { borderedSectionStackClass } from '../design/identity.constants'
import { ToneStylesEditor } from './ToneStylesEditor'
import { EditorStylesEditor } from './EditorStylesEditor'
import type { ToneStyleEntry } from '../utils/toneStyles'
import type { EditorStyleEntry } from '../utils/editorStyles'

type SchrijfregelsSectionProps = {
  writingGuide: string
  onWritingGuideChange: (value: string) => void
  toneEntries: ToneStyleEntry[]
  onToneEntriesChange: (entries: ToneStyleEntry[]) => void
  editorEntries: EditorStyleEntry[]
  onEditorEntriesChange: (entries: EditorStyleEntry[]) => void
  disabled?: boolean
}

/**
 * Schrijfwijzer, tones en auteurs.
 * Deze sectie hoort nooit achter de HBA-vlag te verdwijnen: CE-03 heeft haar nodig.
 */
export function SchrijfregelsSection({
  writingGuide,
  onWritingGuideChange,
  toneEntries,
  onToneEntriesChange,
  editorEntries,
  onEditorEntriesChange,
  disabled = false,
}: SchrijfregelsSectionProps) {
  const toneStylesFilled = toneEntries.some(
    (entry) => entry.name.trim() !== '' || entry.description.trim() !== '',
  )
  const editorStylesFilled = editorEntries.some(
    (entry) => entry.name.trim() !== '' || entry.description.trim() !== '',
  )

  return (
    <div className={borderedSectionStackClass}>
      <StrategyField
        id="strategy-writingGuide"
        title="Schrijfwijzer"
        hint="Algemene spelregels voor alle content: stijl, taboes en aanpak. Jullie toon staat op Merkarchitectuur."
        value={writingGuide}
        onChange={onWritingGuideChange}
        minHeight={140}
      />

      <StrategySection
        title="Schrijfstijl per doelgroep"
        hint="Maak per doelgroep een schrijfstijl (Consumenten, Persoonlijk, Zakelijk). De namen verschijnen bij Artikel → Toon en bij het verwerken van aantekeningen."
        filled={toneStylesFilled}
      >
        <ToneStylesEditor entries={toneEntries} onChange={onToneEntriesChange} disabled={disabled} />
      </StrategySection>

      <StrategySection
        title="Auteurs"
        hint="Definieer auteurs met elk een unieke invalshoek op het onderwerp. De namen verschijnen bij Thema's, in Artikel → Auteur, en sturen de schrijver."
        filled={editorStylesFilled}
      >
        <EditorStylesEditor
          entries={editorEntries}
          onChange={onEditorEntriesChange}
          disabled={disabled}
        />
      </StrategySection>
    </div>
  )
}

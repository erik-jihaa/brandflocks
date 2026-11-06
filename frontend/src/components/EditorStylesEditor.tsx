import { FieldSingle } from './ui/Field'
import { PillButton, designItemStackClass } from './PillButton'
import type { EditorStyleEntry } from '../utils/editorStyles'

type EditorStylesEditorProps = {
  entries: EditorStyleEntry[]
  onChange: (entries: EditorStyleEntry[]) => void
  disabled?: boolean
}

export function EditorStylesEditor({ entries, onChange, disabled = false }: EditorStylesEditorProps) {
  function updateEntry(id: string, patch: Partial<EditorStyleEntry>) {
    onChange(entries.map((entry) => (entry.id === id ? { ...entry, ...patch } : entry)))
  }

  function addEntry() {
    onChange([
      ...entries,
      { id: crypto.randomUUID(), name: '', description: '' },
    ])
  }

  function removeEntry(id: string) {
    if (entries.length <= 1) return
    onChange(entries.filter((entry) => entry.id !== id))
  }

  return (
    <div className={designItemStackClass}>
      {entries.map((entry, index) => (
        <div
          key={entry.id}
          className={
            index < entries.length - 1 ? 'border-b border-ce-beige pb-[18px]' : ''
          }
        >
          <div className="flex flex-col gap-[18px]">
            <FieldSingle
              id={`editor-name-${entry.id}`}
              value={entry.name}
              placeholder="Naam auteur"
              aria-label="Naam auteur"
              onChange={(name) => updateEntry(entry.id, { name })}
            />
            <textarea
              id={`editor-desc-${entry.id}`}
              value={entry.description}
              onChange={(e) => updateEntry(entry.id, { description: e.target.value })}
              placeholder="Unieke angle: hoe benadert deze redacteur het onderwerp?"
              aria-label={`Auteur ${index + 1}`}
              style={{ minHeight: 72 }}
              className="w-full resize-y border-0 bg-transparent p-0 text-[14px] leading-[18px] text-ce-black outline-none placeholder:text-ce-beige focus:ring-0"
            />
            <PillButton
              variant="second"
              className="self-start px-[12px] py-[4px] text-[12px]"
              disabled={disabled || entries.length <= 1}
              onClick={() => removeEntry(entry.id)}
            >
              Verwijderen
            </PillButton>
          </div>
        </div>
      ))}

      <PillButton variant="second" className="self-start" disabled={disabled} onClick={addEntry}>
        Auteur toevoegen
      </PillButton>
    </div>
  )
}

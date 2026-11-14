import { PillButton, designItemStackClass } from './PillButton'
import { CheckboxOption } from './ui/RadioOption'

export type CampagneBankRow = {
  id: string
  text: string
}

type CampagneBankEditorProps = {
  kind: 'vraag' | 'feit'
  rows: CampagneBankRow[]
  onChange: (rows: CampagneBankRow[]) => void
  onRemoveRow?: (id: string) => void
  disabled?: boolean
  /** Campagne-wizard: kies max. 3 ankers. SEO/GEO: alles bewerken zonder keuze. */
  selectable?: boolean
  selectedIds?: string[]
  maxSelected?: number
  onToggleSelect?: (id: string) => void
}

const PLACEHOLDER: Record<CampagneBankEditorProps['kind'], string> = {
  vraag: 'Formuleer hier een vraag',
  feit: 'Formuleer hier een feit',
}

const ADD_LABEL: Record<CampagneBankEditorProps['kind'], string> = {
  vraag: '+ Vraag toevoegen',
  feit: '+ eigen feit toevoegen',
}

/** Campagne stap 3 — bank bewerken (Vindbaarheid-stijl) + keuze via design system radio-indicator. */
export function CampagneBankEditor({
  kind,
  rows,
  onChange,
  onRemoveRow,
  disabled = false,
  selectable = true,
  selectedIds = [],
  maxSelected = 3,
  onToggleSelect,
}: CampagneBankEditorProps) {
  const filledRows = rows.filter((r) => r.text.trim() !== '')
  const atMaxRows = filledRows.length >= 10
  const trailingEmpty = rows.length > 0 && rows[rows.length - 1].text.trim() === ''
  const fieldPrefix = `campagne-bank-${kind}`

  function updateRow(rowId: string, text: string) {
    onChange(rows.map((r) => (r.id === rowId ? { ...r, text } : r)))
  }

  function addRow() {
    if (disabled || atMaxRows) return
    if (trailingEmpty) {
      const last = rows[rows.length - 1]
      document.getElementById(`${fieldPrefix}-${last.id}`)?.focus()
      return
    }
    onChange([...rows, { id: `new-${Date.now()}`, text: '' }])
  }

  function removeRow(row: CampagneBankRow) {
    onRemoveRow?.(row.id)
    const next = rows.filter((r) => r.id !== row.id)
    onChange(next.length > 0 ? next : [{ id: `new-${Date.now()}`, text: '' }])
    if (selectable && selectedIds.includes(row.id)) {
      onToggleSelect?.(row.id)
    }
  }

  const displayRows = rows.length > 0 ? rows : [{ id: `new-${Date.now()}`, text: '' }]

  return (
    <div className={designItemStackClass}>
      {displayRows.map((row, index) => {
        const selected = selectable && selectedIds.includes(row.id)
        const canSelect =
          selectable &&
          row.text.trim() !== '' &&
          (selected || selectedIds.length < maxSelected)
        return (
          <div
            key={row.id}
            className={`relative ${index < displayRows.length - 1 ? 'border-b border-ce-beige pb-[18px]' : ''}`}
          >
            <textarea
              id={`${fieldPrefix}-${row.id}`}
              value={row.text}
              onChange={(e) => updateRow(row.id, e.target.value)}
              placeholder={PLACEHOLDER[kind]}
              aria-label={displayRows.length > 1 ? `${kind} ${index + 1}` : kind}
              style={{ minHeight: 72 }}
              disabled={disabled}
              className="w-full resize-y border-0 bg-transparent p-0 pr-[28px] text-[14px] leading-[18px] text-ce-black outline-none placeholder:text-ce-beige focus:ring-0 disabled:opacity-50"
            />
            {(displayRows.length > 1 || row.text.trim() !== '') && (
              <button
                type="button"
                onClick={() => removeRow(row)}
                disabled={disabled}
                aria-label="Verwijder"
                className="absolute right-0 top-0 cursor-pointer border-0 bg-transparent text-[14px] leading-[18px] text-ce-beige hover:text-ce-red disabled:cursor-not-allowed disabled:opacity-50"
              >
                ✕
              </button>
            )}
            {selectable && row.text.trim() !== '' ? (
              <div className="mt-[12px]">
                <CheckboxOption
                  checked={selected}
                  disabled={disabled || !canSelect}
                  onChange={() => onToggleSelect?.(row.id)}
                  label={selected ? 'Gekozen voor campagne' : 'Kies voor campagne (max. 3)'}
                />
              </div>
            ) : null}
          </div>
        )
      })}

      {!atMaxRows && (
        <PillButton variant="second" className="self-start" disabled={disabled} onClick={addRow}>
          {ADD_LABEL[kind]}
        </PillButton>
      )}
    </div>
  )
}

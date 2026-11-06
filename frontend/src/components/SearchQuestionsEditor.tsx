import { PillButton, designItemStackClass } from './PillButton'
import {
  CE_SEARCH_QUESTIONS_MAX,
  type LlmQuestionRow,
  normalizeLlmQuestions,
  type SearchQuestionKind,
} from '../utils/llmQuestions'

type SearchQuestionsEditorProps = {
  kind: SearchQuestionKind
  rows: LlmQuestionRow[]
  onChange: (rows: LlmQuestionRow[]) => void
  disabled?: boolean
}

const KIND_LABEL: Record<SearchQuestionKind, string> = {
  geo: 'GEO-vraag',
  seo: 'SEO-vraag',
}

/** Search — GEO- of SEO-vragen (controlled; opslaan via Search Save). */
export function SearchQuestionsEditor({
  kind,
  rows,
  onChange,
  disabled = false,
}: SearchQuestionsEditorProps) {
  const filledCount = normalizeLlmQuestions(rows).length
  const atMax = filledCount >= CE_SEARCH_QUESTIONS_MAX
  const trailingEmpty = rows.length > 0 && rows[rows.length - 1].question.trim() === ''
  const fieldPrefix = `${kind}-question`
  const ariaLabel = KIND_LABEL[kind]

  function updateRow(rowId: string, question: string) {
    onChange(rows.map((r) => (r.id === rowId ? { ...r, question } : r)))
  }

  function addQuestion() {
    if (disabled || atMax) return

    if (trailingEmpty) {
      const last = rows[rows.length - 1]
      document.getElementById(`${fieldPrefix}-${last.id}`)?.focus()
      return
    }

    onChange([...rows, { id: `new-${Date.now()}`, question: '' }])
  }

  function removeQuestion(row: LlmQuestionRow) {
    const next = rows.filter((r) => r.id !== row.id)
    onChange(next.length > 0 ? next : [{ id: 'draft-new', question: '' }])
  }

  return (
    <div className={designItemStackClass}>
      {rows.map((row, index) => (
        <div
          key={row.id}
          className={`relative ${index < rows.length - 1 ? 'border-b border-ce-beige pb-[18px]' : ''}`}
        >
          <textarea
            id={`${fieldPrefix}-${row.id}`}
            value={row.question}
            onChange={(e) => updateRow(row.id, e.target.value)}
            placeholder={`Formuleer hier vraag ${index + 1}`}
            aria-label={rows.length > 1 ? `${ariaLabel} ${index + 1}` : ariaLabel}
            style={{ minHeight: 72 }}
            className="w-full resize-y border-0 bg-transparent p-0 pr-[28px] text-[14px] leading-[18px] text-ce-black outline-none placeholder:text-ce-beige focus:ring-0"
          />
          {(rows.length > 1 || row.question.trim() !== '') && (
            <button
              type="button"
              onClick={() => removeQuestion(row)}
              disabled={disabled}
              aria-label="Verwijder vraag"
              className="absolute right-0 top-0 cursor-pointer border-0 bg-transparent text-[14px] leading-[18px] text-ce-beige hover:text-ce-red disabled:cursor-not-allowed disabled:opacity-50"
            >
              ✕
            </button>
          )}
        </div>
      ))}

      {!atMax && (
        <PillButton
          variant="second"
          className="self-start"
          disabled={disabled}
          onClick={addQuestion}
        >
          + Vraag toevoegen
        </PillButton>
      )}
    </div>
  )
}

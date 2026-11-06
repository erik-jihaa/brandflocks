import { useCallback, useEffect, useMemo, useState } from 'react'
import { GridShell } from '../layout/GridShell'
import { StrategySection } from '../components/ui/Field'
import { borderedSectionStackClass } from '../design/identity.constants'
import { PillButton, pillActionsRowClass, designStackClass } from '../components/PillButton'
import { SaveNotice } from '../components/ui/SaveNotice'
import { useSaveNotice } from '../hooks/useSaveNotice'
import { SearchQuestionsEditor } from '../components/SearchQuestionsEditor'
import { CampagneBankEditor, type CampagneBankRow } from '../components/CampagneBankEditor'
import { CommaWordsField, feitRowsWithCommaWords } from '../components/CommaWordsField'
import { fetchLlmQuestions } from '../api/llmQuestions'
import { fetchCampagneBank, saveCampagneBank } from '../api/campagnePreview'
import { isApiConfigured } from '../api/client'
import { GRID2_REVIEW_MAIN_WIDTH } from '../layout/grid.constants'
import {
  CE_SEARCH_QUESTIONS_MAX,
  displayLlmRowsFromSaved,
  llmRowsEqual,
  llmRowsFromDto,
  normalizeLlmQuestions,
  persistLlmQuestionRows,
  type LlmQuestionRow,
  type SearchQuestionKind,
} from '../utils/llmQuestions'

function emptyBankRow(): CampagneBankRow[] {
  return [{ id: `new-${Date.now()}`, text: '' }]
}

function emptyDraftRows(): LlmQuestionRow[] {
  return [{ id: `new-${Date.now()}`, question: '' }]
}

function bankRowsFromSaved(items: { id: string; text: string }[]): CampagneBankRow[] {
  const filled = items.filter((r) => r.text.trim() !== '')
  return filled.length > 0 ? filled : emptyBankRow()
}

function bankRowsEqual(a: CampagneBankRow[], b: CampagneBankRow[]): boolean {
  const norm = (rows: CampagneBankRow[]) =>
    rows
      .map((r) => r.text.trim())
      .filter(Boolean)
      .join('\n')
  return norm(a) === norm(b)
}

/** SEO/GEO — merkbank (vragen, woorden, feiten) + GEO/SEO-vragen voor Rapport. */
export function SearchPage() {
  const [savedVraagRows, setSavedVraagRows] = useState<CampagneBankRow[]>([])
  const [draftVraagRows, setDraftVraagRows] = useState<CampagneBankRow[]>(emptyBankRow())
  const [savedFeitRows, setSavedFeitRows] = useState<CampagneBankRow[]>([])
  const [draftFeitRows, setDraftFeitRows] = useState<CampagneBankRow[]>(emptyBankRow())
  const [commaWords, setCommaWords] = useState('')
  const [savedCommaWords, setSavedCommaWords] = useState('')
  const [removedVraagIds, setRemovedVraagIds] = useState<string[]>([])
  const [removedFeitIds, setRemovedFeitIds] = useState<string[]>([])

  const [savedGeoRows, setSavedGeoRows] = useState<LlmQuestionRow[]>([])
  const [draftGeoRows, setDraftGeoRows] = useState<LlmQuestionRow[]>(emptyDraftRows())
  const [savedSeoRows, setSavedSeoRows] = useState<LlmQuestionRow[]>([])
  const [draftSeoRows, setDraftSeoRows] = useState<LlmQuestionRow[]>(emptyDraftRows())

  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [note, setNote] = useState<string | null>(null)
  const {
    message: saveMessage,
    kind: saveNoticeKind,
    fading: saveNoticeFading,
    showSaving: showSaveSaving,
    showSuccess: showSaveSuccess,
    showError: showSaveError,
    clear: clearSaveNotice,
  } = useSaveNotice('SEO/GEO opgeslagen')

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    if (!isApiConfigured()) {
      setSavedVraagRows([])
      setDraftVraagRows(emptyBankRow())
      setSavedFeitRows([])
      setDraftFeitRows(emptyBankRow())
      setSavedGeoRows([])
      setDraftGeoRows(emptyDraftRows())
      setSavedSeoRows([])
      setDraftSeoRows(emptyDraftRows())
      setNote('API niet geconfigureerd')
      setLoading(false)
      return
    }
    try {
      const [bank, llm] = await Promise.all([fetchCampagneBank(), fetchLlmQuestions()])
      const vraagRows = (bank.vragen ?? []).map((q) => ({ id: q.id, text: q.vraag }))
      const feitRows = (bank.feiten ?? []).map((f) => ({ id: f.id, text: f.formulering }))
      setSavedVraagRows(vraagRows)
      setDraftVraagRows(bankRowsFromSaved(vraagRows))
      setSavedFeitRows(feitRows)
      setDraftFeitRows(bankRowsFromSaved(feitRows))
      setCommaWords('')
      setSavedCommaWords('')
      setRemovedVraagIds([])
      setRemovedFeitIds([])

      const geoRows = llmRowsFromDto(llm.geoQuestions ?? [])
      const seoRows = llmRowsFromDto(llm.seoQuestions ?? [])
      setSavedGeoRows(geoRows)
      setDraftGeoRows(displayLlmRowsFromSaved(geoRows))
      setSavedSeoRows(seoRows)
      setDraftSeoRows(displayLlmRowsFromSaved(seoRows))
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Laden mislukt')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  const dirty = useMemo(() => {
    const bankDirty =
      !bankRowsEqual(draftVraagRows, savedVraagRows) ||
      !bankRowsEqual(draftFeitRows, savedFeitRows) ||
      commaWords.trim() !== savedCommaWords.trim()
    const llmDirty = !llmRowsEqual(draftGeoRows, savedGeoRows) || !llmRowsEqual(draftSeoRows, savedSeoRows)
    return bankDirty || llmDirty
  }, [
    draftVraagRows,
    savedVraagRows,
    draftFeitRows,
    savedFeitRows,
    commaWords,
    savedCommaWords,
    draftGeoRows,
    savedGeoRows,
    draftSeoRows,
    savedSeoRows,
  ])

  const vraagFilled = draftVraagRows.some((r) => r.text.trim() !== '')
  const feitFilled = draftFeitRows.some((r) => r.text.trim() !== '') || commaWords.trim() !== ''
  const geoFilled = draftGeoRows.some((r) => r.question.trim() !== '')
  const seoFilled = draftSeoRows.some((r) => r.question.trim() !== '')

  useEffect(() => {
    if (dirty) clearSaveNotice()
  }, [dirty, clearSaveNotice])

  function trackRemoved(kind: 'vraag' | 'feit', id: string) {
    if (id.startsWith('new-') || id.startsWith('draft-') || id === 'draft-new') return
    if (kind === 'vraag') {
      setRemovedVraagIds((prev) => (prev.includes(id) ? prev : [...prev, id]))
    } else {
      setRemovedFeitIds((prev) => (prev.includes(id) ? prev : [...prev, id]))
    }
  }

  function undo() {
    setDraftVraagRows(bankRowsFromSaved(savedVraagRows))
    setDraftFeitRows(bankRowsFromSaved(savedFeitRows))
    setCommaWords(savedCommaWords)
    setDraftGeoRows(displayLlmRowsFromSaved(savedGeoRows))
    setDraftSeoRows(displayLlmRowsFromSaved(savedSeoRows))
    setRemovedVraagIds([])
    setRemovedFeitIds([])
  }

  async function saveLlmKind(
    kind: SearchQuestionKind,
    saved: LlmQuestionRow[],
    draft: LlmQuestionRow[],
  ): Promise<LlmQuestionRow[]> {
    const toSave = normalizeLlmQuestions(draft)
    if (toSave.length > CE_SEARCH_QUESTIONS_MAX) {
      throw new Error(`Maximaal ${CE_SEARCH_QUESTIONS_MAX} ${kind === 'seo' ? 'SEO' : 'GEO'}-vragen.`)
    }
    return persistLlmQuestionRows(saved, draft, kind)
  }

  async function save() {
    setSaving(true)
    setError(null)
    showSaveSaving()
    try {
      const feitSource = feitRowsWithCommaWords(commaWords, draftFeitRows)
      const vragenPayload = draftVraagRows
        .filter((row) => row.text.trim() !== '')
        .map((row) => ({
          id: row.id,
          vraag: row.text.trim(),
          clientRowId: row.id,
        }))
      const feitenPayload = feitSource
        .filter((row) => row.text.trim() !== '')
        .map((row) => ({
          id: row.id,
          formulering: row.text.trim(),
          clientRowId: row.id,
        }))

      const [bankSaved, geoSaved, seoSaved] = await Promise.all([
        saveCampagneBank({
          vragen: vragenPayload,
          feiten: feitenPayload,
          removedVraagIds,
          removedFeitIds,
        }),
        saveLlmKind('geo', savedGeoRows, draftGeoRows),
        saveLlmKind('seo', savedSeoRows, draftSeoRows),
      ])

      const vraagRows = (bankSaved.vragen ?? []).map((q) => ({ id: q.id, text: q.vraag }))
      const feitRows = (bankSaved.feiten ?? []).map((f) => ({ id: f.id, text: f.formulering }))
      setSavedVraagRows(vraagRows)
      setDraftVraagRows(bankRowsFromSaved(vraagRows))
      setSavedFeitRows(feitRows)
      setDraftFeitRows(bankRowsFromSaved(feitRows))
      setCommaWords('')
      setSavedCommaWords('')
      setRemovedVraagIds([])
      setRemovedFeitIds([])

      setSavedGeoRows(geoSaved)
      setDraftGeoRows(displayLlmRowsFromSaved(geoSaved))
      setSavedSeoRows(seoSaved)
      setDraftSeoRows(displayLlmRowsFromSaved(seoSaved))

      showSaveSuccess()
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Opslaan mislukt'
      setError(message)
      showSaveError(message)
    } finally {
      setSaving(false)
    }
  }

  if (loading) {
    return <GridShell pageTitle="SEO/GEO" activeNav="search" main={<p>Laden…</p>} />
  }

  return (
    <GridShell
      pageTitle="SEO/GEO"
      activeNav="search"
      main={
        <div className={designStackClass} style={{ maxWidth: GRID2_REVIEW_MAIN_WIDTH }}>
          {note && <p className="m-0 text-[14px] leading-[18px] text-ce-beige">{note}</p>}
          {error && <p className="m-0 text-[14px] leading-[18px] text-ce-red">{error}</p>}

          <div className={borderedSectionStackClass}>
            <StrategySection
              title="Vragen"
              hint="Merkvragen voor campagnes en content. Bewerk of voeg toe — ze staan in je merkbank."
              hintTone="black"
              filled={vraagFilled || geoFilled || seoFilled}
            >
              <CampagneBankEditor
                kind="vraag"
                rows={draftVraagRows}
                selectable={false}
                onChange={setDraftVraagRows}
                onRemoveRow={(id) => trackRemoved('vraag', id)}
                disabled={saving}
              />
              <p className="m-0 mt-[18px] text-[14px] leading-[18px] font-semibold text-ce-black">GEO (AI)</p>
              <p className="m-0 mt-[6px] text-[14px] leading-[18px] text-ce-black">
                Vragen die je doelgroep aan ChatGPT, Perplexity of Claude stelt. Rapport meet of je merk in
                AI-antwoorden verschijnt.
              </p>
              <div className="mt-[12px]">
                <SearchQuestionsEditor
                  kind="geo"
                  rows={draftGeoRows}
                  onChange={setDraftGeoRows}
                  disabled={saving}
                />
              </div>
              <p className="m-0 mt-[18px] text-[14px] leading-[18px] font-semibold text-ce-black">SEO (Google)</p>
              <p className="m-0 mt-[6px] text-[14px] leading-[18px] text-ce-black">
                Zoekvragen in Google. Rapport meet vindbaarheid in de eerste 50 resultaten.
              </p>
              <div className="mt-[12px]">
                <SearchQuestionsEditor
                  kind="seo"
                  rows={draftSeoRows}
                  onChange={setDraftSeoRows}
                  disabled={saving}
                />
              </div>
            </StrategySection>

            <StrategySection
              title="Woorden"
              hint="Woorden of korte zinnen voor je feitenbank. Gescheiden door komma's — bij Opslaan worden ze feiten."
              hintTone="black"
              filled={commaWords.trim() !== ''}
            >
              <CommaWordsField id="search-comma-words" value={commaWords} onChange={setCommaWords} disabled={saving} />
            </StrategySection>

            <StrategySection
              title="Feiten"
              hint="Feiten over je merk. Bewerk of voeg toe — ze staan in je merkbank en kunnen mee in campagnes."
              hintTone="black"
              filled={feitFilled}
            >
              <CampagneBankEditor
                kind="feit"
                rows={draftFeitRows}
                selectable={false}
                onChange={setDraftFeitRows}
                onRemoveRow={(id) => trackRemoved('feit', id)}
                disabled={saving}
              />
            </StrategySection>
          </div>

          <div>
            <div className={pillActionsRowClass}>
              <PillButton variant="main" onClick={() => void save()} disabled={saving || !dirty} loading={saving}>
                Opslaan
              </PillButton>
              <PillButton variant="second" onClick={undo} disabled={saving || !dirty}>
                Ongedaan maken
              </PillButton>
            </div>
            <SaveNotice message={saveMessage} kind={saveNoticeKind} fading={saveNoticeFading} />
          </div>
        </div>
      }
    />
  )
}

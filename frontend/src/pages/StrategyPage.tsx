import { Link } from 'react-router-dom'
import { useCallback, useEffect, useMemo, useState } from 'react'
import { GridShell } from '../layout/GridShell'
import { PillButton, pillActionsRowClass, designStackClass } from '../components/PillButton'
import { SaveNotice } from '../components/ui/SaveNotice'
import { useSaveNotice } from '../hooks/useSaveNotice'
import { SchrijfregelsSection } from '../components/SchrijfregelsSection'
import { fetchStrategy, patchStrategy } from '../api/onboarding'
import { isApiConfigured } from '../api/client'
import type { StrategyDto } from '../types/onboarding'
import { GRID2_REVIEW_MAIN_WIDTH } from '../layout/grid.constants'
import {
  entriesToToneStyles,
  toneStyleEntriesEqual,
  toneStylesToEntries,
  type ToneStyleEntry,
} from '../utils/toneStyles'
import {
  editorStyleEntriesEqual,
  editorStylesToEntries,
  entriesToEditorStyles,
  type EditorStyleEntry,
} from '../utils/editorStyles'

const EMPTY: StrategyDto = {
  clientId: '',
  companyName: '',
  archetype: '',
  adversary: '',
  brandstory: '',
  brandGoalCategory: '',
  writingGuide: '',
  toneStyles: {},
  editorStyles: {},
}

const PAGE_TITLE = 'Merktaal'

/** Schrijfwijzer, tone-styles en auteurs — merkidentiteit staat op Merkarchitectuur. */
export function StrategyPage() {
  const [saved, setSaved] = useState<StrategyDto>(EMPTY)
  const [draft, setDraft] = useState<StrategyDto>(EMPTY)
  const [toneEntries, setToneEntries] = useState<ToneStyleEntry[]>([])
  const [savedToneEntries, setSavedToneEntries] = useState<ToneStyleEntry[]>([])
  const [editorEntries, setEditorEntries] = useState<EditorStyleEntry[]>([])
  const [savedEditorEntries, setSavedEditorEntries] = useState<EditorStyleEntry[]>([])
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
  } = useSaveNotice('Schrijfregels opgeslagen')

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    if (!isApiConfigured()) {
      const entries = toneStylesToEntries(EMPTY.toneStyles)
      setSaved(EMPTY)
      setDraft(EMPTY)
      setToneEntries(entries)
      setSavedToneEntries(entries)
      const editorRows = editorStylesToEntries(EMPTY.editorStyles)
      setEditorEntries(editorRows)
      setSavedEditorEntries(editorRows)
      setNote('API niet geconfigureerd')
      setLoading(false)
      return
    }
    try {
      const data = await fetchStrategy()
      if (data.error) setNote(data.error)
      const s = data.strategy ?? EMPTY
      const toneStyles = { ...EMPTY.toneStyles, ...s.toneStyles }
      const strategy = { ...EMPTY, ...s, toneStyles }
      const entries = toneStylesToEntries(toneStyles)
      setSaved(strategy)
      setDraft(strategy)
      setToneEntries(entries)
      setSavedToneEntries(entries)
      const editorStyles = { ...EMPTY.editorStyles, ...s.editorStyles }
      const editorRows = editorStylesToEntries(editorStyles)
      setEditorEntries(editorRows)
      setSavedEditorEntries(editorRows)
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
    if (draft.writingGuide !== saved.writingGuide) {
      return true
    }
    return !toneStyleEntriesEqual(toneEntries, savedToneEntries)
      || !editorStyleEntriesEqual(editorEntries, savedEditorEntries)
  }, [draft, saved, toneEntries, savedToneEntries, editorEntries, savedEditorEntries])

  useEffect(() => {
    if (dirty) clearSaveNotice()
  }, [dirty, clearSaveNotice])

  function undo() {
    setDraft(saved)
    setToneEntries(savedToneEntries)
    setEditorEntries(savedEditorEntries)
  }

  async function save() {
    const toneDirty = !toneStyleEntriesEqual(toneEntries, savedToneEntries)
    const editorDirty = !editorStyleEntriesEqual(editorEntries, savedEditorEntries)
    let toneStyles = entriesToToneStyles(toneEntries)
    let editorStyles = entriesToEditorStyles(editorEntries)

    if (toneDirty) {
      const names = Object.keys(toneStyles)
      if (names.length === 0) {
        setError('Voeg minstens één schrijfstijl met naam toe.')
        return
      }
      const duplicates = names.filter((name, index) => names.indexOf(name) !== index)
      if (duplicates.length > 0) {
        setError(`Dubbele tone-namen: ${[...new Set(duplicates)].join(', ')}`)
        return
      }
    } else {
      toneStyles = { ...EMPTY.toneStyles, ...saved.toneStyles }
    }

    if (editorDirty) {
      const names = Object.keys(editorStyles)
      const duplicates = names.filter((name, index) => names.indexOf(name) !== index)
      if (duplicates.length > 0) {
        setError(`Dubbele auteurnamen: ${[...new Set(duplicates)].join(', ')}`)
        return
      }
    } else {
      editorStyles = { ...EMPTY.editorStyles, ...saved.editorStyles }
    }

    setSaving(true)
    setError(null)
    showSaveSaving()
    try {
      // Alleen velden die deze secties bezitten. De merkarchitectuur slaat zichzelf op.
      const result = await patchStrategy({
        writingGuide: draft.writingGuide,
        toneStyles,
        editorStyles,
      })
      const strategy = { ...draft, toneStyles, editorStyles, ...(result.strategy ?? {}) }
      const entries = toneStylesToEntries(strategy.toneStyles)
      const editorRows = editorStylesToEntries(strategy.editorStyles)
      setSaved(strategy)
      setDraft(strategy)
      setToneEntries(entries)
      setSavedToneEntries(entries)
      setEditorEntries(editorRows)
      setSavedEditorEntries(editorRows)
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
    return <GridShell pageTitle={PAGE_TITLE} activeNav="strategy" mainLayout="grid2" main={<p>Laden…</p>} />
  }

  return (
    <GridShell
      pageTitle={PAGE_TITLE}
      activeNav="strategy"
      mainLayout="grid2"
      main={
        <div className={designStackClass} style={{ maxWidth: GRID2_REVIEW_MAIN_WIDTH }}>
          {note && <p className="m-0 text-[14px] leading-[18px] text-ce-beige">{note}</p>}
          {error && <p className="m-0 text-[14px] leading-[18px] text-ce-red">{error}</p>}

          <p className="m-0 border-b border-ce-black/10 pb-[18px] text-[14px] leading-[18px] text-ce-black">
            Je merkidentiteit staat op{' '}
            <Link to="/brandarchitecture" className="font-semibold text-ce-blue underline">
              Merkarchitectuur
            </Link>
            . Hieronder leg je vast hóé er geschreven wordt.
          </p>

          <SchrijfregelsSection
            writingGuide={draft.writingGuide}
            onWritingGuideChange={(v) => setDraft((d) => ({ ...d, writingGuide: v }))}
            toneEntries={toneEntries}
            onToneEntriesChange={setToneEntries}
            editorEntries={editorEntries}
            onEditorEntriesChange={setEditorEntries}
            disabled={saving}
          />

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

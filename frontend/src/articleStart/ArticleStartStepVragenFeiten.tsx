import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { borderedSectionClass, borderedSectionStackClass, pageNavLinkClass } from '../design/identity.constants'
import { CheckboxOption } from '../components/ui/RadioOption'
import { StrategySection } from '../components/ui/Field'
import { CampagneBankEditor, type CampagneBankRow } from '../components/CampagneBankEditor'
import { PillButton } from '../components/PillButton'
import { fetchCampagneBank, saveCampagneBank } from '../api/campagnePreview'
import { fetchLlmQuestions } from '../api/llmQuestions'
import { isApiConfigured } from '../api/client'
import type { ArticleStartDraft } from './useArticleStartDraft'
import { ARTICLE_START_MERK_KEYS, ARTICLE_START_MERK_LABELS } from './articleStartMerk'

const SECTION = borderedSectionClass

function emptyFeitRows(): CampagneBankRow[] {
  return [{ id: `new-${Date.now()}`, text: '' }]
}

function remapSelectedIds(ids: string[], idMap: Record<string, string> | undefined): string[] {
  if (!idMap) return ids
  return ids.map((id) => idMap[id] ?? id)
}

type Props = {
  draft: ArticleStartDraft
  setDraft: (patch: Partial<ArticleStartDraft>) => void
  registerPersist: (fn: (() => Promise<boolean>) | null) => void
  onError: (message: string | null) => void
}

/** Klikmodel `p-artikelstart-vragenfeiten` — merkonderdelen, feiten, SEO/GEO, hashtags. */
export function ArticleStartStepVragenFeiten({ draft, setDraft, registerPersist, onError }: Props) {
  const [draftVraagRows, setDraftVraagRows] = useState<CampagneBankRow[]>([])
  const [draftFeitRows, setDraftFeitRows] = useState<CampagneBankRow[]>(emptyFeitRows())
  const [removedFeitIds, setRemovedFeitIds] = useState<string[]>([])
  const [seoPreview, setSeoPreview] = useState('')
  const [geoPreview, setGeoPreview] = useState('')
  const [hashtagInput, setHashtagInput] = useState('')
  const [showHashtagField, setShowHashtagField] = useState(false)

  useEffect(() => {
    if (!isApiConfigured()) return
    let cancelled = false
    void (async () => {
      try {
        const [bank, llm] = await Promise.all([fetchCampagneBank(), fetchLlmQuestions()])
        if (cancelled) return
        const vragen = (bank.vragen ?? []).filter((q) => q.vraag.trim() !== '')
        const feiten = (bank.feiten ?? []).filter((f) => f.formulering.trim() !== '')
        setDraftVraagRows(vragen.map((q) => ({ id: q.id, text: q.vraag })))
        setDraftFeitRows(
          feiten.length > 0 ? feiten.map((f) => ({ id: f.id, text: f.formulering })) : emptyFeitRows(),
        )
        const seo = (llm.seoQuestions ?? []).map((q) => q.question?.trim() ?? '').find(Boolean) ?? ''
        const geo = (llm.geoQuestions ?? []).map((q) => q.question?.trim() ?? '').find(Boolean) ?? ''
        setSeoPreview(seo)
        setGeoPreview(geo)
      } catch {
        if (!cancelled) onError('Merkbank of SEO/GEO kon niet geladen worden.')
      }
    })()
    return () => {
      cancelled = true
    }
  }, [onError])

  function toggleMerk(key: string) {
    const has = draft.merkOnderdelen.includes(key)
    if (has) {
      if (draft.merkOnderdelen.length <= 1) return
      setDraft({ merkOnderdelen: draft.merkOnderdelen.filter((k) => k !== key) })
      return
    }
    setDraft({ merkOnderdelen: [...draft.merkOnderdelen, key] })
  }

  function toggleFeit(id: string) {
    const has = draft.feitIds.includes(id)
    if (has) {
      setDraft({ feitIds: draft.feitIds.filter((x) => x !== id) })
      return
    }
    setDraft({ feitIds: [...draft.feitIds, id] })
  }

  function trackRemovedFeit(id: string) {
    if (id.startsWith('new-') || id.startsWith('draft-')) return
    setRemovedFeitIds((prev) => (prev.includes(id) ? prev : [...prev, id]))
  }

  function addHashtag() {
    const raw = hashtagInput.trim().replace(/^#/, '')
    if (!raw) return
    const tag = `#${raw}`
    if (draft.hashtags.includes(tag)) {
      setHashtagInput('')
      return
    }
    setDraft({ hashtags: [...draft.hashtags, tag] })
    setHashtagInput('')
    setShowHashtagField(false)
  }

  function removeHashtag(tag: string) {
    setDraft({ hashtags: draft.hashtags.filter((h) => h !== tag) })
  }

  const persistBank = useCallback(async (): Promise<boolean> => {
    onError(null)
    if (!isApiConfigured()) return true
    try {
      const vragenPayload = draftVraagRows.map((row) => ({
        id: row.id,
        vraag: row.text.trim(),
        clientRowId: row.id,
      }))
      const feitenPayload = draftFeitRows
        .filter((row) => row.text.trim() !== '')
        .map((row) => ({
          id: row.id,
          formulering: row.text.trim(),
          clientRowId: row.id,
        }))
      const saved = await saveCampagneBank({
        vragen: vragenPayload,
        feiten: feitenPayload,
        removedVraagIds: [],
        removedFeitIds,
      })
      const savedFeiten = (saved.feiten ?? []).filter((f) => f.formulering.trim() !== '')
      setDraftFeitRows(
        savedFeiten.length > 0
          ? savedFeiten.map((f) => ({ id: f.id, text: f.formulering }))
          : emptyFeitRows(),
      )
      const feitIds = remapSelectedIds(draft.feitIds, saved.idMap).filter((id) =>
        savedFeiten.some((f) => f.id === id),
      )
      setDraft({ feitIds })
      setRemovedFeitIds([])
      return true
    } catch (err) {
      onError(err instanceof Error ? err.message : 'Merkbank opslaan mislukt')
      return false
    }
  }, [draft.feitIds, draftFeitRows, draftVraagRows, onError, setDraft])

  useEffect(() => {
    registerPersist(persistBank)
    return () => registerPersist(null)
  }, [persistBank, registerPersist])

  return (
    <div className={borderedSectionStackClass}>
      <section className={SECTION}>
        <h2 className="m-0 text-[18px] leading-[22px] font-semibold text-ce-black">Merkonderdelen</h2>
        <div className="mt-[12px] flex flex-col gap-[10px]">
          {ARTICLE_START_MERK_KEYS.map((key) => (
            <CheckboxOption
              key={key}
              align="start"
              checked={draft.merkOnderdelen.includes(key)}
              onChange={() => toggleMerk(key)}
            >
              <span className="block font-semibold">{ARTICLE_START_MERK_LABELS[key]}</span>
            </CheckboxOption>
          ))}
        </div>
      </section>

      <StrategySection
        title="Feiten"
        hint="Harde feiten en cijfers die in deze artikelen gebruikt mogen worden. Vink aan wat je wilt meenemen, of voeg een eigen feit toe."
        hintTone="black"
        filled={draft.feitIds.length > 0}
      >
        <CampagneBankEditor
          kind="feit"
          rows={draftFeitRows}
          selectedIds={draft.feitIds}
          maxSelected={20}
          onChange={setDraftFeitRows}
          onToggleSelect={toggleFeit}
          onRemoveRow={trackRemovedFeit}
        />
      </StrategySection>

      <section className={SECTION}>
        <h2 className="m-0 text-[18px] leading-[22px] font-semibold text-ce-black">SEO- en GEO-vragen</h2>
        {seoPreview || geoPreview ? (
          <div className="mt-[10px] text-[14px] leading-[21px] text-ce-black">
            {seoPreview ? (
              <>
                <p className="m-0 text-[13px] font-semibold text-ce-black">SEO</p>
                <p className="m-0 mt-[4px] text-ce-black">{seoPreview}</p>
              </>
            ) : null}
            {geoPreview ? (
              <>
                <p className="m-0 mt-[14px] text-[13px] font-semibold text-ce-black">GEO</p>
                <p className="m-0 mt-[4px] text-ce-black">{geoPreview}</p>
              </>
            ) : null}
          </div>
        ) : (
          <p className="m-0 mt-[10px] text-[14px] leading-[21px] text-ce-beige">Nog geen vragen ingesteld.</p>
        )}
        <p className="m-0 mt-[10px] text-[14px] leading-[21px] text-ce-beige">
          Nog geen vragen? Je kunt ze toevoegen bij{' '}
          <Link to="/search" className={pageNavLinkClass}>
            SEO/GEO
          </Link>
          , of deze stap overslaan.
        </p>
      </section>

      <section className={SECTION}>
        <h2 className="m-0 text-[18px] leading-[22px] font-semibold text-ce-black">Hashtags</h2>
        <p className="m-0 mt-[8px] text-[14px] leading-[21px] text-ce-beige">
          Gaan standaard mee met elk artikel uit deze reeks — je kunt ze per artikel nog aanpassen.
        </p>
        <div className="mt-[10px] flex flex-wrap items-center gap-[8px] text-[12px] text-ce-beige">
          {draft.hashtags.map((tag) => (
            <span key={tag} className="inline-flex items-center gap-[4px]">
              {tag}
              <button
                type="button"
                className="cursor-pointer border-0 bg-transparent p-0 text-ce-beige"
                aria-label={`Verwijder ${tag}`}
                onClick={() => removeHashtag(tag)}
              >
                ×
              </button>
            </span>
          ))}
          {!showHashtagField ? (
            <PillButton variant="second" className="mb-0 h-[32px] px-[16px] text-[12px]" onClick={() => setShowHashtagField(true)}>
              + hashtag toevoegen
            </PillButton>
          ) : null}
        </div>
        {showHashtagField ? (
          <div className="mt-[10px] flex flex-wrap items-center gap-[8px]">
            <input
              type="text"
              value={hashtagInput}
              onChange={(e) => setHashtagInput(e.target.value)}
              placeholder="eigenhashtag"
              className="w-[180px] border border-ce-beige bg-ce-offwhite px-[10px] py-[8px] text-[14px] text-ce-black outline-none"
              onKeyDown={(e) => {
                if (e.key === 'Enter') {
                  e.preventDefault()
                  addHashtag()
                }
              }}
            />
            <PillButton variant="second" className="mb-0" onClick={addHashtag}>
              Toevoegen
            </PillButton>
          </div>
        ) : null}
      </section>
    </div>
  )
}
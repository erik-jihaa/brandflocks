import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { PillButton, pillLineGapBeforeClass } from './PillButton'
import { LoadingDots } from './LoadingDots'
import { fetchSourceInspiration } from '../api/sources'
import { ApiError, isApiConfigured } from '../api/client'
import { GRID2_REVIEW_MAIN_WIDTH } from '../layout/grid.constants'
import { sourceArticleUrlLinkClass } from '../design/identity.constants'
import { sourceTabToSlug } from '../utils/sourceTabs'
import { inspirationInvalshoekLabel } from '../utils/inspirationInvalshoekLabels'
import {
  loadSourceInspiration,
  saveSourceInspiration,
} from '../utils/sourceInspirationStorage'
import type { SourceInspirationSuggestion } from '../types/sources'

type SourceInspirationPanelProps = {
  disabled?: boolean
}

function suggestionDisplayTitle(suggestion: SourceInspirationSuggestion): string {
  const title = suggestion.title.trim()
  const stelling = suggestion.stelling?.trim() ?? ''
  if (title !== '' && stelling !== '') {
    return `${title} — ${stelling}`
  }
  return title
}

function suggestionDescription(suggestion: SourceInspirationSuggestion): string {
  const wat = suggestion.wat?.trim() ?? ''
  const verrassend = suggestion.verrassendOmdat?.trim() ?? ''
  if (wat !== '' || verrassend !== '') {
    return suggestion.description.trim()
  }
  return suggestion.description.trim()
}

function buildAddPath(suggestion: SourceInspirationSuggestion): string {
  const slug = sourceTabToSlug(suggestion.category)
  const params = new URLSearchParams()
  const title = suggestionDisplayTitle(suggestion)
  const description = suggestionDescription(suggestion)
  if (title) params.set('title', title)
  if (description) params.set('description', description)
  if (suggestion.category === 'Linkedin' && suggestion.url) {
    params.set('linkedinProfile', suggestion.url)
  } else if (suggestion.url) {
    params.set('url', suggestion.url)
  }
  const query = params.toString()
  return `/sources/new/${slug}${query ? `?${query}` : ''}`
}

function scopeLabel(value: number): string {
  if (value <= 20) return 'Average'
  if (value <= 55) return 'Balanced'
  if (value <= 80) return 'Surprise'
  return 'Max surprise'
}

function readStoredState() {
  return loadSourceInspiration()
}

export function SourceInspirationPanel({ disabled = false }: SourceInspirationPanelProps) {
  const navigate = useNavigate()
  const stored = readStoredState()
  const [open, setOpen] = useState(Boolean(stored?.open && (stored.suggestions?.length ?? 0) > 0))
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [suggestions, setSuggestions] = useState<SourceInspirationSuggestion[]>(
    stored?.suggestions ?? [],
  )
  const [searchScope, setSearchScope] = useState(stored?.searchScope ?? 65)
  const [droppedProbeFailed, setDroppedProbeFailed] = useState(0)

  useEffect(() => {
    if (!open && suggestions.length === 0) return
    saveSourceInspiration({ open, suggestions, searchScope })
  }, [open, suggestions, searchScope])

  async function handleGenerate(scope = searchScope) {
    if (!isApiConfigured()) {
      setError('Log in om broninspiratie te gebruiken.')
      setOpen(true)
      return
    }

    setOpen(true)
    setLoading(true)
    setError(null)
    setSuggestions([])
    setDroppedProbeFailed(0)

    try {
      const data = await fetchSourceInspiration(scope)
      const nextSuggestions = data.suggestions ?? []
      setSuggestions(nextSuggestions)
      setDroppedProbeFailed(data.droppedProbeFailed ?? 0)
      if (typeof data.searchScope === 'number') {
        setSearchScope(data.searchScope)
      }
      saveSourceInspiration({
        open: true,
        suggestions: nextSuggestions,
        searchScope: typeof data.searchScope === 'number' ? data.searchScope : scope,
      })
    } catch (err) {
      setError(
        err instanceof ApiError
          ? err.message
          : err instanceof Error
            ? err.message
            : 'Broninspiratie mislukt',
      )
    } finally {
      setLoading(false)
    }
  }

  function handleAdd(suggestion: SourceInspirationSuggestion) {
    saveSourceInspiration({ open: true, suggestions, searchScope })
    navigate(buildAddPath(suggestion))
  }

  function handleToggle() {
    if (loading) return

    if (open) {
      setOpen(false)
      saveSourceInspiration({ open: false, suggestions, searchScope })
      return
    }

    if (suggestions.length > 0) {
      setOpen(true)
      saveSourceInspiration({ open: true, suggestions, searchScope })
      return
    }

    void handleGenerate()
  }

  const showPanel = open && (loading || error !== null || suggestions.length > 0)
  const buttonLabel = loading
    ? 'Bronnen bedenken…'
    : open
      ? 'Close inspiration'
      : 'Source inspiration'

  return (
    <>
      <PillButton
        variant="second"
        className="shrink-0"
        disabled={disabled || loading}
        onClick={handleToggle}
      >
        {buttonLabel}
      </PillButton>

      {showPanel ? (
        <div className="mt-[16px] w-full basis-full" style={{ maxWidth: GRID2_REVIEW_MAIN_WIDTH }}>
          <div className="mb-[12px]">
            <div className="mb-[8px]">
              <span className="text-[14px] font-semibold leading-[18px] text-ce-black">Search scope</span>
            </div>
            <div className="flex items-center gap-[12px]">
              <span className="shrink-0 text-[12px] leading-[16px] text-ce-beige">Average</span>
              <input
                type="range"
                min={0}
                max={100}
                step={5}
                value={searchScope}
                disabled={loading}
                onChange={(event) => setSearchScope(Number(event.target.value))}
                aria-label="Search scope"
                aria-valuetext={scopeLabel(searchScope)}
                className="h-[6px] min-w-0 flex-1 cursor-pointer appearance-none rounded-full bg-ce-beige/40 accent-ce-blue disabled:cursor-not-allowed disabled:opacity-60 [&::-webkit-slider-thumb]:h-[18px] [&::-webkit-slider-thumb]:w-[18px] [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-ce-blue"
              />
              <span className="shrink-0 text-[12px] leading-[16px] text-ce-beige">Surprise</span>
            </div>
            {!loading && suggestions.length > 0 ? (
              <div className="mt-[10px]">
                <PillButton variant="second" onClick={() => void handleGenerate(searchScope)}>
                  Opnieuw met deze scope
                </PillButton>
              </div>
            ) : null}
          </div>

          <section>
            {loading ? (
              <LoadingDots label="We stellen 5 bronnen voor" className="text-ce-beige" />
            ) : error ? (
              <p className="m-0 text-[14px] leading-[18px] text-ce-red">{error}</p>
            ) : suggestions.length === 0 ? (
              <p className="m-0 text-[14px] leading-[18px] text-ce-beige">Geen voorstellen ontvangen.</p>
            ) : (
              <>
                <ul className="m-0 list-none divide-y divide-ce-beige p-0">
                  {suggestions.map((suggestion, index) => {
                    const invalshoekText = inspirationInvalshoekLabel(suggestion.invalshoek)
                    const wat = suggestion.wat?.trim() ?? ''
                    const verrassend = suggestion.verrassendOmdat?.trim() ?? ''
                    const legacyBody = suggestion.description.trim()
                    const showWat = wat !== '' ? wat : legacyBody
                    const showVerrassend =
                      verrassend !== '' ||
                      (legacyBody !== '' && wat === '' && legacyBody.includes('Verrassend omdat:'))
                    const verrassendText =
                      verrassend !== ''
                        ? verrassend
                        : legacyBody.replace(/^[\s\S]*Verrassend omdat:\s*/i, '').trim()

                    return (
                      <li
                        key={`${suggestion.category}-${suggestion.title}-${index}`}
                        className="py-[16px] first:pt-0"
                      >
                        <p className="m-0 mb-[8px] text-[12px] leading-[16px] text-ce-beige">
                          {invalshoekText
                            ? `${suggestion.category} - ${invalshoekText}`
                            : suggestion.category}
                        </p>
                        <p className="m-0 mb-[8px] text-[14px] font-semibold leading-[18px] text-ce-blue">
                          {suggestionDisplayTitle(suggestion)}
                        </p>
                        {showWat ? (
                          <p className="m-0 mb-[8px] text-[14px] leading-[18px] text-ce-black">{showWat}</p>
                        ) : null}
                        {showVerrassend && verrassendText ? (
                          <p className="m-0 mb-[8px] text-[14px] leading-[18px] text-ce-black">
                            <span className="font-semibold text-ce-black">Verrassend omdat: </span>
                            {verrassendText}
                          </p>
                        ) : null}
                        <div className="flex flex-col items-start">
                          {suggestion.url ? (
                            <a
                              href={suggestion.url}
                              target="_blank"
                              rel="noopener noreferrer"
                              className={`block ${sourceArticleUrlLinkClass}`}
                            >
                              {suggestion.url} →
                            </a>
                          ) : null}
                          <PillButton
                            variant="second"
                            className={suggestion.url ? pillLineGapBeforeClass : undefined}
                            onClick={() => handleAdd(suggestion)}
                          >
                            Toevoegen
                          </PillButton>
                        </div>
                      </li>
                    )
                  })}
                </ul>
                {droppedProbeFailed > 0 ? (
                  <p className="m-0 mt-[12px] text-[14px] leading-[18px] text-ce-beige">
                    {droppedProbeFailed} suggestie{droppedProbeFailed === 1 ? '' : 's'} viel
                    {droppedProbeFailed === 1 ? '' : 'en'} af omdat de bron niet bereikbaar was.
                  </p>
                ) : null}
              </>
            )}
          </section>
        </div>
      ) : null}
    </>
  )
}

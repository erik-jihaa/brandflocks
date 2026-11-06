import { useCallback, useEffect, useMemo, useState } from 'react'
import { PercentSlider } from './ui/PercentSlider'
import { FieldSingle } from './ui/Field'
import { Pulldown } from './ui/Pulldown'
import { PillButton, pillActionsRowClass, designStackClass, designItemStackClass } from './PillButton'
import { ArrowIcon } from './ArrowIcon'
import { SaveNotice } from './ui/SaveNotice'
import { useSaveNotice, type SaveNoticeKind } from '../hooks/useSaveNotice'
import { useToneOptions } from '../hooks/useToneOptions'
import { fetchObjectives, saveObjectives } from '../api/objectives'
import { fetchSourceFolders } from '../api/sources'
import { isApiConfigured } from '../api/client'
import { DEFAULT_META_TONE, defaultToneFrom } from '../constants/metaOptions'
import { objectiveDisplayLabel } from '../utils/objectives'
import type { ObjectiveDraft } from '../types/objectives'
import type { SourceFolderDto } from '../types/sources'

const MAX_OBJECTIVES = 6

function emptyObjective(index: number, defaultWritingStyle: string): ObjectiveDraft {
  return {
    id: `new-${Date.now()}-${index}`,
    label: '',
    description: '',
    percentage: 0,
    sortOrder: index,
    writingStyle: defaultWritingStyle,
    sourceFolderId: '',
  }
}

function draftFromDto(
  items: Array<{
    id: string
    label: string
    description: string
    percentage: number
    sortOrder: number
    writingStyle?: string
    sourceFolderId?: string | null
  }>,
  defaultWritingStyle: string,
): ObjectiveDraft[] {
  return items.map((item) => ({
    id: item.id,
    label: item.label,
    description: item.description,
    percentage: item.percentage,
    sortOrder: item.sortOrder,
    writingStyle: item.writingStyle?.trim() || defaultWritingStyle,
    sourceFolderId: item.sourceFolderId?.trim() ?? '',
  }))
}

function labelFromDescription(description: string, index: number): string {
  const line = description.trim().split('\n')[0]?.trim() ?? ''
  if (line.length >= 3) {
    return line.slice(0, 255)
  }
  return `Doelstelling ${index + 1}`
}

function remainingPercentage(objectives: ObjectiveDraft[], excludeId: string | null): number {
  const otherTotal = objectives
    .filter((item) => item.id !== excludeId)
    .reduce((sum, item) => sum + (Number(item.percentage) || 0), 0)
  return Math.max(0, 100 - otherTotal)
}

type ObjectiveEditorProps = {
  draft: ObjectiveDraft
  objectiveNumber: number
  toneOptions: string[]
  folderOptions: SourceFolderDto[]
  maxPercentage: number
  showPercentages: boolean
  onChange: (patch: Partial<ObjectiveDraft>) => void
  onSave: () => void
  onDelete: () => void
  saving: boolean
  error: string | null
  saveNoticeMessage?: string | null
  saveNoticeKind?: SaveNoticeKind
  saveNoticeFading?: boolean
}

function ObjectiveEditor({
  draft,
  objectiveNumber,
  toneOptions,
  folderOptions,
  maxPercentage,
  showPercentages,
  onChange,
  onSave,
  onDelete,
  saving,
  error,
  saveNoticeMessage,
  saveNoticeKind,
  saveNoticeFading,
}: ObjectiveEditorProps) {
  const options = toneOptions
  const selectedStyle = options.includes(draft.writingStyle)
    ? draft.writingStyle
    : defaultToneFrom(options)
  const folderLabels = ['Geen map', ...folderOptions.map((folder) => folder.name)]
  const selectedFolder =
    folderOptions.find((folder) => folder.id === draft.sourceFolderId)?.name ?? 'Geen map'

  useEffect(() => {
    if (showPercentages && draft.percentage > maxPercentage) {
      onChange({ percentage: maxPercentage })
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- clamp only when max or value changes
  }, [draft.percentage, maxPercentage, showPercentages])

  return (
    <div className={`mt-[18px] ${designStackClass}`}>
      {showPercentages ? (
        <PercentSlider
          id={`objective-percent-${draft.id}`}
          label="Aandeel % in totale media output"
          value={draft.percentage}
          max={maxPercentage}
          onChange={(percentage) => onChange({ percentage })}
        />
      ) : null}

      <FieldSingle
        placeholder="Titel (bv. Promotie MailFlow)"
        value={draft.label}
        onChange={(label) => onChange({ label })}
        aria-label={`Titel doel ${objectiveNumber}`}
      />

      <div>
        <h3 className="m-0 text-[24px] leading-[28px] font-semibold text-ce-blue">
          Objective {objectiveNumber}
        </h3>
        <textarea
          id={`objective-description-${draft.id}`}
          value={draft.description}
          onChange={(e) => onChange({ description: e.target.value })}
          placeholder="Omschrijf hier je doelstelling"
          style={{ minHeight: 100 }}
          aria-label={`Objective ${objectiveNumber}`}
          className={`mt-[12px] w-full resize-y border-0 bg-transparent p-0 text-[14px] leading-[18px] outline-none placeholder:text-ce-beige focus:ring-0 ${
            draft.description.trim() ? 'text-ce-black' : 'text-ce-beige'
          }`}
        />
      </div>

      <Pulldown
        placeholder="Schrijfstijl"
        value={selectedStyle}
        options={options}
        onChange={(label) => onChange({ writingStyle: label })}
      />

      {folderOptions.length > 0 ? (
        <Pulldown
          placeholder="Sources-map voor research"
          value={selectedFolder}
          options={folderLabels}
          onChange={(label) => {
            if (label === 'Geen map') {
              onChange({ sourceFolderId: '' })
              return
            }
            const folder = folderOptions.find((item) => item.name === label)
            onChange({ sourceFolderId: folder?.id ?? '' })
          }}
        />
      ) : null}

      {error && <p className="m-0 text-[14px] leading-[18px] text-ce-red">{error}</p>}

      <div className={pillActionsRowClass}>
        <PillButton variant="main" onClick={onSave} disabled={saving} loading={saving}>
                Save
        </PillButton>
        <PillButton variant="second" className="self-start" onClick={onDelete} disabled={saving}>
          Verwijderen
        </PillButton>
      </div>
      <SaveNotice
        message={saveNoticeMessage ?? null}
        kind={saveNoticeKind}
        fading={saveNoticeFading}
      />
    </div>
  )
}

type CollapsedObjectiveProps = {
  item: ObjectiveDraft
  showPercentages: boolean
  onOpen: () => void
}

function CollapsedObjective({ item, showPercentages, onOpen }: CollapsedObjectiveProps) {
  return (
    <button
      type="button"
      onClick={onOpen}
      className="mt-[12px] flex w-full cursor-pointer items-center justify-between text-left text-[14px] leading-[18px] font-semibold text-ce-black"
    >
      <span className="truncate pr-[12px]">
        {objectiveDisplayLabel(item) || 'Doelstelling'}
        {showPercentages ? ` — ${item.percentage}%` : ''}
      </span>
      <ArrowIcon direction="down" className="h-[11px] w-[11px] shrink-0" />
    </button>
  )
}

export type ObjectivesSectionProps = {
  /**
   * Met HBA staat de verdeling nog steeds in de database — ze wordt alleen niet
   * als slider gevraagd. Bestaande percentages blijven bij het opslaan intact.
   */
  showPercentages: boolean
  heading?: string
  hint?: string
}

/** Objectives — één formulier, twee weergaven (met of zonder percentage-sliders). */
export function ObjectivesSection({ showPercentages, heading, hint }: ObjectivesSectionProps) {
  const { toneOptions } = useToneOptions()
  const defaultWritingStyle = defaultToneFrom(toneOptions)
  const [objectives, setObjectives] = useState<ObjectiveDraft[]>([])
  const [sourceFolders, setSourceFolders] = useState<SourceFolderDto[]>([])
  const [expandedKey, setExpandedKey] = useState<string | null>(null)
  const [draft, setDraft] = useState<ObjectiveDraft | null>(null)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const {
    message: objectiveMessage,
    kind: objectiveKind,
    fading: objectiveFading,
    showSaving: showObjectiveSaving,
    showSuccess: showObjectiveSuccess,
    showError: showObjectiveError,
  } = useSaveNotice('Objective opgeslagen')
  const [error, setError] = useState<string | null>(null)
  const [entryError, setEntryError] = useState<string | null>(null)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    if (!isApiConfigured()) {
      setLoading(false)
      return
    }
    try {
      const [objectivesData, foldersData] = await Promise.all([
        fetchObjectives(),
        fetchSourceFolders().catch(() => ({ folders: [] as SourceFolderDto[] })),
      ])
      setObjectives(draftFromDto(objectivesData.objectives ?? [], DEFAULT_META_TONE))
      setSourceFolders(foldersData.folders ?? [])
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Laden mislukt')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  const percentageTotal = useMemo(
    () => objectives.reduce((sum, item) => sum + (Number(item.percentage) || 0), 0),
    [objectives],
  )

  const showTotal = showPercentages && objectives.length > 0 && percentageTotal > 0
  const percentageValid = !showTotal || percentageTotal === 100

  const editorMaxPercentage = useMemo(() => {
    if (expandedKey === null) return 100
    const excludeId = expandedKey === 'new' ? null : expandedKey
    return remainingPercentage(objectives, excludeId)
  }, [expandedKey, objectives])

  function closeEditor() {
    setExpandedKey(null)
    setDraft(null)
    setEntryError(null)
  }

  function openNewObjective() {
    if (objectives.length >= MAX_OBJECTIVES || expandedKey !== null) return
    const next = emptyObjective(objectives.length, defaultWritingStyle)
    // Zonder sliders krijgt een nieuwe doelstelling het vrije restant; bestaande
    // percentages worden nooit herverdeeld.
    if (!showPercentages) {
      next.percentage = remainingPercentage(objectives, null)
    }
    setExpandedKey('new')
    setDraft(next)
    setEntryError(null)
  }

  function openObjective(item: ObjectiveDraft) {
    if (expandedKey !== null) return
    setExpandedKey(item.id)
    setDraft({ ...item })
    setEntryError(null)
  }

  async function saveEntry() {
    if (!draft) return

    const description = draft.description.trim()
    if (description === '') {
      setEntryError('Vul een doelstelling in.')
      return
    }

    const index =
      expandedKey === 'new'
        ? objectives.length
        : objectives.findIndex((item) => item.id === expandedKey)
    const entry: ObjectiveDraft = {
      ...draft,
      label: draft.label.trim() || labelFromDescription(description, Math.max(0, index)),
      description,
      percentage: Math.max(0, Math.min(100, Number(draft.percentage) || 0)),
      sortOrder: Math.max(0, index),
    }

    const nextList =
      expandedKey === 'new'
        ? [...objectives, entry]
        : objectives.map((item) => (item.id === expandedKey ? entry : item))

    const total = nextList.reduce((sum, item) => sum + item.percentage, 0)
    if (total > 100) {
      setEntryError('De percentages mogen in totaal niet boven 100% uitkomen.')
      return
    }

    setSaving(true)
    setEntryError(null)
    setError(null)
    showObjectiveSaving()
    try {
      const result = await saveObjectives(nextList)
      setObjectives(draftFromDto(result.objectives ?? [], defaultWritingStyle))
      showObjectiveSuccess()
      closeEditor()
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Opslaan mislukt'
      setEntryError(message)
      showObjectiveError(message)
    } finally {
      setSaving(false)
    }
  }

  async function deleteEntry() {
    if (!draft || expandedKey === null) return

    if (expandedKey === 'new') {
      closeEditor()
      return
    }

    const nextList = objectives.filter((item) => item.id !== expandedKey)

    setSaving(true)
    setEntryError(null)
    setError(null)
    showObjectiveSaving()
    try {
      const result = await saveObjectives(nextList)
      setObjectives(draftFromDto(result.objectives ?? [], defaultWritingStyle))
      showObjectiveSuccess()
      closeEditor()
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Verwijderen mislukt'
      setEntryError(message)
      showObjectiveError(message)
    } finally {
      setSaving(false)
    }
  }

  if (loading) {
    return <p className="m-0 text-[14px] leading-[18px] text-ce-beige">Laden…</p>
  }

  return (
    <div>
      {heading ? (
        <h3 className="m-0 text-[24px] leading-[28px] font-semibold text-ce-blue">{heading}</h3>
      ) : null}
      {hint ? (
        <p className="m-0 mt-[8px] text-[14px] leading-[18px] text-ce-beige">{hint}</p>
      ) : null}

      <div className={designItemStackClass}>
      {objectives.map((item, index) => (
        <div key={item.id} className="border-b border-ce-beige pb-[18px]">
          {expandedKey === item.id && draft ? (
            <ObjectiveEditor
              draft={draft}
              objectiveNumber={index + 1}
              toneOptions={toneOptions}
              folderOptions={sourceFolders}
              maxPercentage={editorMaxPercentage}
              showPercentages={showPercentages}
              onChange={(patch) => setDraft((prev) => (prev ? { ...prev, ...patch } : prev))}
              onSave={() => void saveEntry()}
              onDelete={() => void deleteEntry()}
              saving={saving}
              error={entryError}
              saveNoticeMessage={objectiveMessage}
              saveNoticeKind={objectiveKind}
              saveNoticeFading={objectiveFading}
            />
          ) : (
            <CollapsedObjective
              item={item}
              showPercentages={showPercentages}
              onOpen={() => openObjective(item)}
            />
          )}
        </div>
      ))}

      {expandedKey === 'new' && draft ? (
        <div className="border-b border-ce-beige pb-[18px]">
          <ObjectiveEditor
            draft={draft}
            objectiveNumber={objectives.length + 1}
            toneOptions={toneOptions}
            folderOptions={sourceFolders}
            maxPercentage={editorMaxPercentage}
            showPercentages={showPercentages}
            onChange={(patch) => setDraft((prev) => (prev ? { ...prev, ...patch } : prev))}
            onSave={() => void saveEntry()}
            onDelete={() => void deleteEntry()}
            saving={saving}
            error={entryError}
            saveNoticeMessage={objectiveMessage}
            saveNoticeKind={objectiveKind}
            saveNoticeFading={objectiveFading}
          />
        </div>
      ) : null}
      </div>

      {expandedKey === null ? (
        <div className="mt-[18px]">
          <PillButton
            variant="second"
            className="self-start"
            onClick={openNewObjective}
            disabled={objectives.length >= MAX_OBJECTIVES}
          >
            + Doel toevoegen
          </PillButton>
        </div>
      ) : null}

      {showTotal ? (
        <p
          className={`mt-[18px] mb-0 text-[14px] leading-[18px] ${
            percentageValid ? 'text-ce-green-text' : 'text-ce-red'
          }`}
        >
          Totaal: {percentageTotal}%{percentageValid ? '' : ' — moet 100% zijn'}
        </p>
      ) : null}

      {error && <p className="mt-[16px] mb-0 text-[14px] text-ce-red">{error}</p>}
    </div>
  )
}

import { useEffect, useState } from 'react'
import { LIST_ROW, LIST_SECTION } from '../layout/grid.constants'
import { ArrowIcon } from './ArrowIcon'
import { ActionLink, ActionSep } from './ui/ActionLink'
import { DeleteConfirmAction } from './ui/DeleteConfirmAction'
import { FieldSingle } from './ui/Field'
import { Pulldown } from './ui/Pulldown'

type ObjectiveOption = {
  id: string
  label: string
}

type SourceFolderHeaderProps = {
  label: string
  sourceCount: number
  objectiveLabel?: string | null
  collapsed: boolean
  onToggle: () => void
  manageable?: boolean
  busy?: boolean
  objectiveOptions?: ObjectiveOption[]
  selectedObjectiveId?: string | null
  onRename?: (name: string, objectiveId: string | null) => void | Promise<void>
  onDelete?: () => void | Promise<void>
}

export function SourceFolderHeader({
  label,
  sourceCount,
  objectiveLabel,
  collapsed,
  onToggle,
  manageable = false,
  busy = false,
  objectiveOptions,
  selectedObjectiveId = null,
  onRename,
  onDelete,
}: SourceFolderHeaderProps) {
  const [renaming, setRenaming] = useState(false)
  const [renameDraft, setRenameDraft] = useState(label)
  const [objectiveDraftId, setObjectiveDraftId] = useState(selectedObjectiveId ?? '')
  const [deleteReset, setDeleteReset] = useState(0)
  const [deleteConfirming, setDeleteConfirming] = useState(false)

  useEffect(() => {
    if (!renaming) {
      setRenameDraft(label)
      setObjectiveDraftId(selectedObjectiveId ?? '')
    }
  }, [label, renaming, selectedObjectiveId])

  async function commitRename() {
    const next = renameDraft.trim()
    if (!onRename || next === '') {
      setRenaming(false)
      setRenameDraft(label)
      setObjectiveDraftId(selectedObjectiveId ?? '')
      return
    }
    const objectiveChanged = (objectiveDraftId || null) !== (selectedObjectiveId || null)
    if (next === label && !objectiveChanged) {
      setRenaming(false)
      setRenameDraft(label)
      setObjectiveDraftId(selectedObjectiveId ?? '')
      return
    }
    await onRename(next, objectiveDraftId || null)
    setRenaming(false)
  }

  function handleDeleteClick() {
    if (!onDelete || busy) return
    void onDelete()
  }

  const countLabel =
    sourceCount === 0
      ? 'leeg'
      : `${sourceCount} source${sourceCount === 1 ? '' : 's'}`

  const objectiveLabels = ['Geen doel', ...(objectiveOptions?.map((item) => item.label) ?? [])]
  const selectedObjectiveLabel =
    objectiveOptions?.find((item) => item.id === objectiveDraftId)?.label ?? 'Geen doel'

  return (
    <div className={`${LIST_SECTION.sourcesItemClass} mb-[8px]`}>
      <div className={`${LIST_ROW.rowClass} items-center text-ce-black`}>
        <button
          type="button"
          onClick={onToggle}
          className="flex min-w-0 flex-1 cursor-pointer items-center gap-[8px] border-0 bg-transparent p-0 text-left text-[21px] font-semibold leading-[24px] text-ce-black"
          aria-expanded={!collapsed}
        >
          <ArrowIcon direction={collapsed ? 'right' : 'down'} className="h-[11px] w-[11px]" />
          <span className="min-w-0">
            <span className="block truncate">{label}</span>
            {objectiveLabel ? (
              <span className="mt-[2px] block truncate text-[14px] font-normal leading-[18px] text-ce-beige">
                Doel: {objectiveLabel}
              </span>
            ) : null}
          </span>
          <span className="shrink-0 text-[14px] font-normal leading-[18px] text-ce-beige">({countLabel})</span>
        </button>

        {manageable && !renaming && (
          <div className={LIST_ROW.actionsClass}>
            <ActionLink
              variant="statusRow"
              disabled={busy}
              onClick={() => {
                setDeleteReset((n) => n + 1)
                setRenaming(true)
              }}
            >
              Rename
            </ActionLink>
            <ActionSep />
            <DeleteConfirmAction
              onConfirm={handleDeleteClick}
              disabled={busy}
              busy={busy}
              resetSignal={deleteReset}
              onConfirmingChange={setDeleteConfirming}
            />
          </div>
        )}
      </div>

      {manageable && renaming ? (
        <div className="mt-[8px] flex flex-col gap-[12px] px-[30px]">
          <div className="flex flex-wrap items-end gap-[12px]">
            <div className="min-w-[220px] flex-1">
              <FieldSingle
                placeholder="Mapnaam"
                value={renameDraft}
                onChange={setRenameDraft}
                aria-label="Mapnaam"
              />
            </div>
            {objectiveOptions && objectiveOptions.length > 0 ? (
              <div className="min-w-[220px] flex-1">
                <Pulldown
                  placeholder="Doel"
                  value={selectedObjectiveLabel}
                  options={objectiveLabels}
                  onChange={(optionLabel) => {
                    if (optionLabel === 'Geen doel') {
                      setObjectiveDraftId('')
                      return
                    }
                    const match = objectiveOptions.find((item) => item.label === optionLabel)
                    setObjectiveDraftId(match?.id ?? '')
                  }}
                />
              </div>
            ) : null}
          </div>
          <div className="flex flex-wrap items-center gap-[12px]">
            <ActionLink
              variant="statusRow"
              disabled={busy || !renameDraft.trim()}
              onClick={() => void commitRename()}
            >
              Opslaan
            </ActionLink>
            <ActionSep />
            <ActionLink
              variant="statusRow"
              disabled={busy}
              onClick={() => {
                setRenaming(false)
                setRenameDraft(label)
                setObjectiveDraftId(selectedObjectiveId ?? '')
              }}
            >
              Annuleer
            </ActionLink>
          </div>
        </div>
      ) : null}

      {deleteConfirming && sourceCount > 0 ? (
        <p className="m-0 mt-[4px] px-[30px] text-[14px] leading-[18px] text-ce-beige">
          Bronnen in deze map komen onder Overig.
        </p>
      ) : null}
    </div>
  )
}

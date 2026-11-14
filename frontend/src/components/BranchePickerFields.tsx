import { Pulldown } from './ui/Pulldown'
import { FieldSingle } from './ui/Field'
import type { BrancheCatalogEntry, BrancheOption } from '../api/hbaV2'
import {
  brancheFreeInputValue,
  branchePulldownOptions,
  branchePulldownValue,
  findCatalogEntry,
  resolveBrancheFromLabel,
} from '../utils/branchePicker'

type Props = {
  brancheCode: string
  brancheInput: string
  branches: BrancheOption[]
  catalog: BrancheCatalogEntry[]
  onChange: (next: { brancheCode: string; brancheInput: string }) => void
  onClearError?: () => void
  freeFieldId: string
  resolving?: boolean
  resolveError?: string | null
  showDerivedHint?: boolean
  showArchetypeLine?: boolean
}

export function BranchePickerFields({
  brancheCode,
  brancheInput,
  branches,
  catalog,
  onChange,
  onClearError,
  freeFieldId,
  resolving = false,
  resolveError = null,
  showDerivedHint = false,
  showArchetypeLine = true,
}: Props) {
  const selectedBranch = branches.find((branch) => branch.code === brancheCode) ?? null
  const catalogEntry = findCatalogEntry(brancheCode, brancheInput, catalog)
  const pulldownOptions =
    catalog.length > 0 ? branchePulldownOptions(catalog) : branches.map((branch) => branch.label)

  const applyLabel = (label: string) => {
    onClearError?.()
    onChange(resolveBrancheFromLabel(label, catalog, branches))
  }

  const archetype = selectedBranch?.marktArchetype?.trim() ?? ''
  const bronLabel =
    selectedBranch?.bron === 'afgeleid'
      ? '(automatisch afgeleid)'
      : selectedBranch?.bron === 'gecureerd'
        ? '(gecureerd)'
        : catalogEntry?.isGecureerd
          ? '(gecureerd)'
          : null

  return (
    <div className="grid gap-[18px]">
      <Pulldown
        placeholder="— kies uit lijst (102 branches) —"
        value={branchePulldownValue(brancheCode, brancheInput, catalog, branches)}
        options={pulldownOptions}
        onChange={applyLabel}
      />
      <FieldSingle
        id={freeFieldId}
        value={brancheFreeInputValue(brancheCode, brancheInput, catalog, branches)}
        placeholder="Of typ je eigen branche"
        onChange={(value) => {
          onClearError?.()
          const resolved = resolveBrancheFromLabel(value, catalog, branches)
          onChange(resolved)
        }}
      />
      {resolving ? (
        <p className="m-0 text-[14px] leading-[18px] text-ce-black">Branche afleiden…</p>
      ) : null}
      {resolveError ? (
        <p className="m-0 text-[14px] leading-[18px] text-ce-red" role="alert">
          {resolveError}
        </p>
      ) : null}
      {showArchetypeLine && archetype ? (
        <p className="m-0 text-[14px] leading-[18px] text-ce-black">
          Markt-archetype: {archetype}
          {bronLabel ? <span className="text-ce-beige"> {bronLabel}</span> : null}
        </p>
      ) : showArchetypeLine && brancheInput.trim() ? (
        <p className="m-0 text-[14px] leading-[18px] text-ce-beige">
          Markt-archetype: nog niet vastgelegd voor deze branche.
        </p>
      ) : null}
      {showDerivedHint && selectedBranch?.bron === 'afgeleid' ? (
        <p className="m-0 text-[14px] leading-[18px] text-ce-beige">
          Deze branche is automatisch afgeleid; een strateeg kan de definitie nog aanscherpen.
        </p>
      ) : null}
    </div>
  )
}
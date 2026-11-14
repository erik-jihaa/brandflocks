import type { BrancheCatalogEntry, BrancheOption } from '../api/hbaV2'

export function branchePulldownOptions(catalog: BrancheCatalogEntry[]): string[] {
  if (catalog.length === 0) {
    return []
  }
  return [...catalog].sort((a, b) => a.listNr - b.listNr).map((row) => row.brancheLabel)
}

export function resolveBrancheFromLabel(
  label: string,
  catalog: BrancheCatalogEntry[],
  branches: BrancheOption[],
): { brancheInput: string; brancheCode: string } {
  const trimmed = label.trim()
  if (trimmed === '') {
    return { brancheInput: '', brancheCode: '' }
  }

  const catalogHit = catalog.find((row) => row.brancheLabel === trimmed)
  if (catalogHit?.brancheCode) {
    const lib = branches.find((branch) => branch.code === catalogHit.brancheCode)
    return {
      brancheInput: lib?.label ?? trimmed,
      brancheCode: catalogHit.brancheCode,
    }
  }

  const libHit =
    branches.find((branch) => branch.label === trimmed) ??
    branches.find((branch) => branch.label.toLowerCase() === trimmed.toLowerCase())

  if (libHit) {
    return { brancheInput: libHit.label, brancheCode: libHit.code }
  }

  if (catalogHit) {
    return { brancheInput: trimmed, brancheCode: '' }
  }

  return { brancheInput: trimmed, brancheCode: '' }
}

export function branchePulldownValue(
  brancheCode: string,
  brancheInput: string,
  catalog: BrancheCatalogEntry[],
  branches: BrancheOption[],
): string {
  if (brancheCode) {
    const lib = branches.find((branch) => branch.code === brancheCode)
    if (lib) {
      return lib.label
    }
    const cat = catalog.find((row) => row.brancheCode === brancheCode)
    if (cat) {
      return cat.brancheLabel
    }
  }
  const input = brancheInput.trim()
  if (catalog.some((row) => row.brancheLabel === input)) {
    return input
  }
  return ''
}

export function brancheFreeInputValue(
  brancheCode: string,
  brancheInput: string,
  catalog: BrancheCatalogEntry[],
  branches: BrancheOption[],
): string {
  const hasLibrary = branches.some((branch) => branch.code === brancheCode)
  const hasCatalog = catalog.some((row) => row.brancheLabel === brancheInput.trim())
  if (hasLibrary || (hasCatalog && branchePulldownValue(brancheCode, brancheInput, catalog, branches) !== '')) {
    return ''
  }
  return brancheInput
}

export function findCatalogEntry(
  brancheCode: string,
  brancheInput: string,
  catalog: BrancheCatalogEntry[],
): BrancheCatalogEntry | null {
  if (brancheCode) {
    const byCode = catalog.find((row) => row.brancheCode === brancheCode)
    if (byCode) {
      return byCode
    }
  }
  const input = brancheInput.trim()
  if (input === '') {
    return null
  }
  return catalog.find((row) => row.brancheLabel === input) ?? null
}
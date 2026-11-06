import { FieldSingle } from './ui/Field'
import { Pulldown } from './ui/Pulldown'
import { PillButton, pillActionsRowClass } from './PillButton'
import {
  ARCHIVE_SEARCH_TYPE_OPTIONS,
  archiveSearchTypeFromLabel,
  archiveSearchTypeLabel,
  type ArchiveSearchType,
} from '../utils/archiveSearch'

type ArchiveSearchPanelProps = {
  searchType: ArchiveSearchType | null
  draftQuery: string
  onSearchTypeChange: (type: ArchiveSearchType | null) => void
  onDraftQueryChange: (query: string) => void
  onSearch: () => void
  onBack: () => void
}

/** briefing/archive searchType+.png — pulldown, Title or date, Search + Back */
export function ArchiveSearchPanel({
  searchType,
  draftQuery,
  onSearchTypeChange,
  onDraftQueryChange,
  onSearch,
  onBack,
}: ArchiveSearchPanelProps) {
  const canSearch = searchType !== null

  return (
    <div className="max-w-[740px]">
      <div className="space-y-[12px]">
        <Pulldown
          options={[...ARCHIVE_SEARCH_TYPE_OPTIONS]}
          value={archiveSearchTypeLabel(searchType)}
          placeholder="Kies artikel of rapport"
          onChange={(label) => onSearchTypeChange(archiveSearchTypeFromLabel(label))}
        />

        <FieldSingle
          id="archive-search-query"
          value={draftQuery}
          onChange={onDraftQueryChange}
          placeholder="Title or date"
          placeholderTone="black"
          aria-label="Title or date"
        />
      </div>

      <div className={pillActionsRowClass}>
        <PillButton variant="main" onClick={onSearch} disabled={!canSearch}>
          Search
        </PillButton>
        <PillButton variant="second" onClick={onBack}>
          Back
        </PillButton>
      </div>
    </div>
  )
}

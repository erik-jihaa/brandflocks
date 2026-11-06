import { diffArrays, diffWordsWithSpace } from 'diff'

/**
 * Word-stijl "wijzigingen bijhouden".
 *
 * De redactie-edit (hoofdredacteur) wordt ÉÉN keer omgezet in een lijst
 * tracked changes. Elke change heeft een eigen status (pending/accepted/
 * rejected). De artikeltekst wordt AFGELEID uit baseline + changes —
 * accepteren of verwerpen wijzigt alleen de status, nooit de diff zelf.
 * Daardoor kunnen labels niet verspringen en komt er geen tekst "uit het
 * niets" terug.
 */

export type TrackedChangeStatus = 'pending' | 'accepted' | 'rejected'

export type TrackedChange = {
  id: string
  kind: 'insert' | 'replace' | 'delete'
  /** Regels uit de originele tekst (leeg bij pure invoeging). */
  baselineLines: string[]
  /** Regels uit het voorstel van de reviewer (leeg bij pure verwijdering). */
  suggestedLines: string[]
  status: TrackedChangeStatus
}

export type TrackedSegment =
  | { type: 'context'; lines: string[] }
  | { type: 'change'; change: TrackedChange }

export type TrackedDoc = {
  editId: string
  baseline: string
  segments: TrackedSegment[]
}

export type TrackedRow = {
  key: string
  kind: 'context' | 'added' | 'removed'
  text: string
  /** Voor woord-diff binnen een gewijzigde regel. */
  baselineLine?: string
  changeId?: string
  isChangeStart?: boolean
}

function isHashtagLine(line: string): boolean {
  const trimmed = line.trim()
  if (trimmed === '') return false
  return trimmed.split(/\s+/).every((token) => token.startsWith('#'))
}

/** Hashtag-blok aan het einde weghalen — hashtags leven in een eigen veld. */
export function stripTrailingHashtagBlock(text: string): string {
  const lines = text.split('\n')
  let end = lines.length
  while (end > 0 && lines[end - 1].trim() === '') end--
  while (end > 0 && isHashtagLine(lines[end - 1])) {
    end--
    while (end > 0 && lines[end - 1].trim() === '') end--
  }
  return lines.slice(0, end).join('\n')
}

function hashString(value: string): string {
  let hash = 0
  for (let i = 0; i < value.length; i++) {
    hash = (hash * 31 + value.charCodeAt(i)) | 0
  }
  return Math.abs(hash).toString(36)
}

function changeSignature(baselineLines: string[], suggestedLines: string[]): string {
  return hashString(`${baselineLines.join('\u0000')}\u0001${suggestedLines.join('\u0000')}`)
}

function buildSegments(baselineText: string, targetText: string): TrackedSegment[] {
  const baseLines = baselineText.split('\n')
  const targetLines = targetText.split('\n')
  const parts = diffArrays(baseLines, targetLines)

  const segments: TrackedSegment[] = []
  const idCounts = new Map<string, number>()
  let pendingRemoved: string[] | null = null

  const pushChange = (baselineLines: string[], suggestedLines: string[]) => {
    const kind: TrackedChange['kind'] =
      baselineLines.length === 0 ? 'insert' : suggestedLines.length === 0 ? 'delete' : 'replace'
    const sig = changeSignature(baselineLines, suggestedLines)
    const count = idCounts.get(sig) ?? 0
    idCounts.set(sig, count + 1)
    segments.push({
      type: 'change',
      change: {
        id: `c-${sig}-${count}`,
        kind,
        baselineLines,
        suggestedLines,
        status: 'pending',
      },
    })
  }

  for (const part of parts) {
    if (part.removed) {
      pendingRemoved = [...part.value]
      continue
    }
    if (part.added) {
      pushChange(pendingRemoved ?? [], [...part.value])
      pendingRemoved = null
      continue
    }
    if (pendingRemoved) {
      pushChange(pendingRemoved, [])
      pendingRemoved = null
    }
    segments.push({ type: 'context', lines: [...part.value] })
  }
  if (pendingRemoved) {
    pushChange(pendingRemoved, [])
  }

  return segments
}

/** Bouw het tracked-changes-document uit de redactie-edit. */
export function buildTrackedDoc(
  editId: string,
  originalText: string,
  suggestedText: string,
): TrackedDoc {
  const baseline = stripTrailingHashtagBlock(originalText)
  const suggested = stripTrailingHashtagBlock(suggestedText)
  return {
    editId,
    baseline,
    segments: buildSegments(baseline, suggested),
  }
}

export function listChanges(doc: TrackedDoc): TrackedChange[] {
  return doc.segments.flatMap((seg) => (seg.type === 'change' ? [seg.change] : []))
}

export function pendingCount(doc: TrackedDoc): number {
  return listChanges(doc).filter((change) => change.status === 'pending').length
}

/** Artikeltekst afleiden: pending/accepted → voorstel, rejected → origineel. */
export function deriveContent(doc: TrackedDoc): string {
  const lines: string[] = []
  for (const seg of doc.segments) {
    if (seg.type === 'context') {
      lines.push(...seg.lines)
      continue
    }
    if (seg.change.status === 'rejected') {
      lines.push(...seg.change.baselineLines)
    } else {
      lines.push(...seg.change.suggestedLines)
    }
  }
  return lines.join('\n')
}

/** Status van één change wijzigen — verder verandert er niets aan het document. */
export function resolveChange(
  doc: TrackedDoc,
  changeId: string,
  status: TrackedChangeStatus,
): TrackedDoc {
  return {
    ...doc,
    segments: doc.segments.map((seg) =>
      seg.type === 'change' && seg.change.id === changeId
        ? { type: 'change', change: { ...seg.change, status } }
        : seg,
    ),
  }
}

export function applyStoredStatuses(
  doc: TrackedDoc,
  statuses: Record<string, TrackedChangeStatus>,
): TrackedDoc {
  return {
    ...doc,
    segments: doc.segments.map((seg) =>
      seg.type === 'change' && statuses[seg.change.id]
        ? { type: 'change', change: { ...seg.change, status: statuses[seg.change.id] } }
        : seg,
    ),
  }
}

/** Zichtbare regel in het document, met verwijzing naar zijn plek in de segmentstructuur. */
type LineRef = {
  segIndex: number
  lineIndex: number
  container: 'context' | 'suggested' | 'baseline'
}

function visibleLineRefs(doc: TrackedDoc): { refs: LineRef[]; lines: string[] } {
  const refs: LineRef[] = []
  const lines: string[] = []
  doc.segments.forEach((seg, segIndex) => {
    if (seg.type === 'context') {
      seg.lines.forEach((line, lineIndex) => {
        refs.push({ segIndex, lineIndex, container: 'context' })
        lines.push(line)
      })
      return
    }
    if (seg.change.status === 'rejected') {
      seg.change.baselineLines.forEach((line, lineIndex) => {
        refs.push({ segIndex, lineIndex, container: 'baseline' })
        lines.push(line)
      })
      return
    }
    // pending + accepted tonen het voorstel
    seg.change.suggestedLines.forEach((line, lineIndex) => {
      refs.push({ segIndex, lineIndex, container: 'suggested' })
      lines.push(line)
    })
  })
  return { refs, lines }
}

function containerKind(change: TrackedChange): TrackedChange['kind'] {
  return change.baselineLines.length === 0
    ? 'insert'
    : change.suggestedLines.length === 0
      ? 'delete'
      : 'replace'
}

/**
 * Handmatige tekstbewerking verwerken (Word-model).
 *
 * De bewerking wordt regel-voor-regel teruggelegd op de bestaande
 * segmentstructuur: bestaande tracked changes behouden hun id én status,
 * dus labels blijven staan en okay/delete blijft aan dezelfde wijziging
 * gekoppeld. Eigen edits van de redacteur in contextregels worden direct
 * in de tekst verwerkt (niet getrackt), zoals in Word tijdens beoordelen.
 */
export function updateDocFromEditedContent(doc: TrackedDoc, editedContent: string): TrackedDoc {
  const next: TrackedDoc = {
    ...doc,
    segments: doc.segments.map((seg) =>
      seg.type === 'context'
        ? { type: 'context', lines: [...seg.lines] }
        : {
            type: 'change',
            change: {
              ...seg.change,
              baselineLines: [...seg.change.baselineLines],
              suggestedLines: [...seg.change.suggestedLines],
            },
          },
    ),
  }

  const { refs, lines: oldLines } = visibleLineRefs(next)
  const newLines = stripTrailingHashtagBlock(editedContent).split('\n')
  const parts = diffArrays(oldLines, newLines)

  const updates = new Map<number, string>()
  const removedRefs = new Set<number>()
  const inserts: { afterRef: number; lines: string[] }[] = []

  let refPos = 0
  for (let i = 0; i < parts.length; i++) {
    const part = parts[i]
    if (!part.added && !part.removed) {
      refPos += part.value.length
      continue
    }
    if (part.removed) {
      const nextPart = parts[i + 1]
      const added = nextPart && nextPart.added ? nextPart.value : []
      const pairCount = Math.min(part.value.length, added.length)
      for (let k = 0; k < pairCount; k++) updates.set(refPos + k, added[k])
      for (let k = pairCount; k < part.value.length; k++) removedRefs.add(refPos + k)
      if (added.length > pairCount) {
        inserts.push({ afterRef: refPos + pairCount - 1, lines: added.slice(pairCount) })
      }
      refPos += part.value.length
      if (nextPart && nextPart.added) i++
      continue
    }
    // Pure invoeging — hoort bij de regel ervóór.
    inserts.push({ afterRef: refPos - 1, lines: [...part.value] })
  }

  // Verwerken per container zodat indexen kloppen: van achter naar voren.
  const containerOf = (ref: LineRef): string[] => {
    const seg = next.segments[ref.segIndex]
    if (seg.type === 'context') return seg.lines
    return ref.container === 'baseline' ? seg.change.baselineLines : seg.change.suggestedLines
  }

  for (const [refIndex, text] of updates) {
    const ref = refs[refIndex]
    containerOf(ref)[ref.lineIndex] = text
  }

  const insertsByRef = new Map<number, string[]>()
  for (const ins of inserts) {
    const existing = insertsByRef.get(ins.afterRef)
    if (existing) existing.push(...ins.lines)
    else insertsByRef.set(ins.afterRef, [...ins.lines])
  }

  // Invoegingen vóór de eerste regel → begin van het eerste container.
  const startInsert = insertsByRef.get(-1)
  if (startInsert) {
    if (refs.length > 0) {
      containerOf(refs[0]).unshift(...startInsert)
    } else if (next.segments.length > 0 && next.segments[0].type === 'context') {
      next.segments[0].lines.unshift(...startInsert)
    } else {
      next.segments.unshift({ type: 'context', lines: [...startInsert] })
    }
  }

  for (let refIndex = refs.length - 1; refIndex >= 0; refIndex--) {
    const ref = refs[refIndex]
    const lines = containerOf(ref)
    const toInsert = insertsByRef.get(refIndex)
    if (toInsert) lines.splice(ref.lineIndex + 1, 0, ...toInsert)
    if (removedRefs.has(refIndex)) lines.splice(ref.lineIndex, 1)
  }

  // Opschonen: lege segmenten weg, kind van changes actualiseren.
  next.segments = next.segments.filter((seg) => {
    if (seg.type === 'context') return seg.lines.length > 0
    const { change } = seg
    if (change.baselineLines.length === 0 && change.suggestedLines.length === 0) return false
    change.kind = containerKind(change)
    return true
  })

  return next
}

/** Rijen voor weergave: context zwart, voorstel gemarkeerd, verwijderd doorgestreept. */
export function trackedRows(doc: TrackedDoc): TrackedRow[] {
  const rows: TrackedRow[] = []
  let rowIndex = 0

  const push = (row: Omit<TrackedRow, 'key'>) => {
    rows.push({ ...row, key: `tr-${rowIndex++}` })
  }

  for (const seg of doc.segments) {
    if (seg.type === 'context') {
      for (const line of seg.lines) push({ kind: 'context', text: line })
      continue
    }

    const { change } = seg
    if (change.status === 'accepted') {
      for (const line of change.suggestedLines) push({ kind: 'context', text: line })
      continue
    }
    if (change.status === 'rejected') {
      for (const line of change.baselineLines) push({ kind: 'context', text: line })
      continue
    }

    // Pending — Word-weergave
    if (change.kind === 'delete') {
      change.baselineLines.forEach((line, i) => {
        push({ kind: 'removed', text: line, changeId: change.id, isChangeStart: i === 0 })
      })
      continue
    }

    change.suggestedLines.forEach((line, i) => {
      push({
        kind: 'added',
        text: line,
        baselineLine: change.baselineLines[i] ?? '',
        changeId: change.id,
        isChangeStart: i === 0,
      })
    })
    // Regels die de reviewer liet vervallen binnen een replace-hunk
    for (let i = change.suggestedLines.length; i < change.baselineLines.length; i++) {
      push({ kind: 'removed', text: change.baselineLines[i], changeId: change.id })
    }
  }

  return rows
}

const storageKey = (slotId: string, editId: string) => `ce-tracked-${slotId}-${editId}`

export function loadStoredStatuses(
  slotId: string,
  editId: string,
): Record<string, TrackedChangeStatus> {
  try {
    const raw = window.localStorage.getItem(storageKey(slotId, editId))
    return raw ? (JSON.parse(raw) as Record<string, TrackedChangeStatus>) : {}
  } catch {
    return {}
  }
}

export function storeStatuses(slotId: string, doc: TrackedDoc): void {
  try {
    const statuses = extractStatuses(doc)
    window.localStorage.setItem(storageKey(slotId, doc.editId), JSON.stringify(statuses))
  } catch {
    // storage vol/uitgeschakeld — statussen gelden dan alleen voor deze sessie
  }
}

export function extractStatuses(doc: TrackedDoc): Record<string, TrackedChangeStatus> {
  const statuses: Record<string, TrackedChangeStatus> = {}
  for (const change of listChanges(doc)) {
    if (change.status !== 'pending') statuses[change.id] = change.status
  }
  return statuses
}

export function clearStoredStatuses(slotId: string, editId: string): void {
  try {
    window.localStorage.removeItem(storageKey(slotId, editId))
  } catch {
    // ignore
  }
}

/** Korte labeltekst voor de feedbackkaart bij een change. */
export function trackedChangeComment(change: TrackedChange): string {
  if (change.kind === 'delete') {
    const removed = change.baselineLines.filter((line) => line.trim() !== '').join(' ')
    return `Verwijderd: ${removed}`.slice(0, 160)
  }

  if (change.kind === 'insert') {
    const added = change.suggestedLines.filter((line) => line.trim() !== '').join(' ')
    return (added || 'Nieuwe tekst').slice(0, 160)
  }

  const addedWords = change.suggestedLines
    .map((line, i) =>
      diffWordsWithSpace(change.baselineLines[i] ?? '', line)
        .filter((part) => part.added)
        .map((part) => part.value)
        .join('')
        .trim(),
    )
    .filter((fragment) => fragment !== '')
    .join(' ')

  const fallback = change.suggestedLines.filter((line) => line.trim() !== '').join(' ')
  return (addedWords || fallback || 'Wijziging').slice(0, 160)
}

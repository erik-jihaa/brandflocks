/** Verticale offset (px) zodat feedbackkaart uitlijnt met een fragment in een regel. */
export function measureCorrectionOffset(lineEl: HTMLElement, correction: string): number {
  const needle = correction.trim()
  if (!needle) return 0

  const haystack = lineEl.innerText.replace(/\u00a0/g, ' ')
  const idx = haystack.toLowerCase().indexOf(needle.toLowerCase())
  if (idx < 0) return 0

  const range = rangeForTextOffset(lineEl, idx, needle.length)
  if (!range) return 0

  const lineRect = lineEl.getBoundingClientRect()
  const markerRect = range.getBoundingClientRect()
  if (markerRect.height === 0) return 0

  return Math.max(0, Math.round(markerRect.top - lineRect.top))
}

function rangeForTextOffset(root: HTMLElement, start: number, length: number): Range | null {
  const range = document.createRange()
  const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT)
  let offset = 0

  while (walker.nextNode()) {
    const node = walker.currentNode as Text
    const normalized = node.data.replace(/\u00a0/g, ' ')
    const next = offset + normalized.length

    if (start < next) {
      const localStart = start - offset
      const startData = dataIndexForNormalizedIndex(node.data, localStart)
      const endData = dataIndexForNormalizedIndex(node.data, localStart + length)
      try {
        range.setStart(node, startData)
        range.setEnd(node, Math.max(startData, endData))
        return range
      } catch {
        return null
      }
    }
    offset = next
  }

  return null
}

function dataIndexForNormalizedIndex(data: string, normalizedIndex: number): number {
  let norm = 0
  for (let i = 0; i < data.length; i++) {
    if (norm >= normalizedIndex) return i
    norm += 1
  }
  return data.length
}

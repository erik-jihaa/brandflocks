/** LLM-fallback toen de architectuur niet in de system-prompt stond. */
const PLACEHOLDER_RE =
  /geen merkarchitectuur|zonder input kunnen we alleen|zonder merkinfo kan ik alleen/i

export function isHbaPlaceholderExample(value: string): boolean {
  return PLACEHOLDER_RE.test(value.trim())
}

export function usableHbaExample(value: string | null | undefined): string {
  const text = (value ?? '').trim()
  return text !== '' && !isHbaPlaceholderExample(text) ? text : ''
}

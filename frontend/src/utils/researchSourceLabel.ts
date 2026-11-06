/** Klantlabel voor bronlink op Onderzoek — geen volledige Google News-URL. */
export function researchSourceLinkLabel(title: string, articleUrl: string): string {
  const trimmedTitle = title.trim()
  const dashMatch = trimmedTitle.match(/\s[–—-]\s+(.+)$/)
  if (dashMatch?.[1]?.trim()) {
    return dashMatch[1].trim()
  }

  try {
    const host = new URL(articleUrl).hostname.replace(/^www\./i, '')
    if (host && !host.includes('google.')) {
      return host
    }
  } catch {
    // ignore
  }

  if (trimmedTitle) {
    return trimmedTitle.length > 72 ? `${trimmedTitle.slice(0, 69)}…` : trimmedTitle
  }

  return 'Bron openen'
}

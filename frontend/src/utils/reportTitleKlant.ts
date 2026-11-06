/** Verwijder interne HBA-versie uit rapporttitels in klant-UI. */
export function reportTitleForKlant(title: string): string {
  return title
    .replace(/\s*\(HBA[^)]*\)\s*/gi, ' ')
    .replace(/\s*—\s*HBA\s*v[\d.]+\s*/gi, ' ')
    .replace(/\s+HBA\s+v[\d.]+\s*$/i, '')
    .replace(/\s{2,}/g, ' ')
    .trim()
}

import type { GalleryImageDto } from '../types/gallery'

/** Weergavetitel: ingestelde titel (altText) of bestandsnaam zonder extensie. */
export function galleryImageTitle(image: GalleryImageDto): string {
  const title = image.altText.trim()
  if (title) return title

  const base = image.filename.replace(/\.[^.]+$/, '').trim()
  return base || image.filename
}

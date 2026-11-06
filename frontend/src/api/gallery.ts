import { apiFetch } from './client'
import type { GalleryImageDto } from '../types/gallery'

export function fetchGalleryImages(): Promise<{ images: GalleryImageDto[] }> {
  return apiFetch('/images')
}

export async function uploadGalleryImage(file: File): Promise<{
  ok: boolean
  image: GalleryImageDto
  images: GalleryImageDto[]
}> {
  const API_BASE = import.meta.env.VITE_API_BASE ?? '/api'
  const form = new FormData()
  form.append('image', file)
  const response = await fetch(`${API_BASE}/images`, {
    method: 'POST',
    credentials: 'include',
    body: form,
  })
  if (!response.ok) {
    let message = `Upload mislukt (${response.status})`
    try {
      const body = (await response.json()) as { error?: string }
      if (body.error) message = body.error
    } catch {
      // ignore
    }
    throw new Error(message)
  }
  return response.json() as Promise<{
    ok: boolean
    image: GalleryImageDto
    images: GalleryImageDto[]
  }>
}

export function patchGalleryImage(
  imageId: string,
  body: { altText?: string; tags?: string },
): Promise<{ ok: boolean; image: GalleryImageDto; images: GalleryImageDto[] }> {
  return apiFetch(`/images/${imageId}`, {
    method: 'PATCH',
    body: JSON.stringify(body),
  })
}

export function deleteGalleryImage(
  imageId: string,
): Promise<{ ok: boolean; images: GalleryImageDto[] }> {
  return apiFetch(`/images/${imageId}`, { method: 'DELETE' })
}

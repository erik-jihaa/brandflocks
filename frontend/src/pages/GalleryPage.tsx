import { useCallback, useEffect, useRef, useState } from 'react'
import { GridShell } from '../layout/GridShell'
import { PillButton } from '../components/PillButton'
import { GalleryImageRow } from '../components/GalleryImageRow'
import {
  deleteGalleryImage,
  fetchGalleryImages,
  patchGalleryImage,
  uploadGalleryImage,
} from '../api/gallery'
import { ApiError, isApiConfigured } from '../api/client'
import type { GalleryImageDto } from '../types/gallery'

/** Grid #1 — klant-beeldbibliotheek (cursor-publish-modes.md, methode 1) */
export function GalleryPage() {
  const inputRef = useRef<HTMLInputElement>(null)
  const [images, setImages] = useState<GalleryImageDto[]>([])
  const [loading, setLoading] = useState(true)
  const [uploading, setUploading] = useState(false)
  const [busyId, setBusyId] = useState<string | null>(null)
  const [error, setError] = useState<string | null>(null)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)

    if (!isApiConfigured()) {
      setImages([])
      setLoading(false)
      return
    }

    try {
      const data = await fetchGalleryImages()
      setImages(data.images)
    } catch (err) {
      if (err instanceof ApiError && err.status === 503) {
        setImages([])
      } else {
        setError(err instanceof Error ? err.message : 'Laden mislukt')
      }
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  async function handleFiles(files: FileList | null) {
    if (!files?.length || !isApiConfigured()) return
    setUploading(true)
    setError(null)
    try {
      let latest = images
      for (const file of [...files]) {
        const result = await uploadGalleryImage(file)
        latest = result.images
      }
      setImages(latest)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Upload mislukt')
    } finally {
      setUploading(false)
      if (inputRef.current) inputRef.current.value = ''
    }
  }

  async function handleDelete(imageId: string) {
    if (!isApiConfigured()) return
    setBusyId(imageId)
    setError(null)
    try {
      const result = await deleteGalleryImage(imageId)
      setImages(result.images)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Verwijderen mislukt')
    } finally {
      setBusyId(null)
    }
  }

  async function handleTagsSave(imageId: string, tags: string) {
    if (!isApiConfigured()) return
    setBusyId(imageId)
    setError(null)
    try {
      const result = await patchGalleryImage(imageId, { tags })
      setImages(result.images)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Tags opslaan mislukt')
    } finally {
      setBusyId(null)
    }
  }

  async function handleTitleSave(imageId: string, title: string) {
    if (!isApiConfigured()) return
    setBusyId(imageId)
    setError(null)
    try {
      const result = await patchGalleryImage(imageId, { altText: title })
      setImages(result.images)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Titel opslaan mislukt')
    } finally {
      setBusyId(null)
    }
  }

  return (
    <GridShell
      pageTitle="Beeldbank"
      activeNav="gallery"
      main={
        <>
          {error && (
            <p className="mb-[12px] text-[14px] leading-[18px] text-ce-red">{error}</p>
          )}

          <input
            ref={inputRef}
            type="file"
            accept="image/png,image/jpeg,image/webp"
            multiple
            className="sr-only"
            disabled={uploading}
            onChange={(e) => void handleFiles(e.target.files)}
          />

          <PillButton
            variant="second"
            disabled={uploading || !isApiConfigured()}
            loading={uploading}
            onClick={() => inputRef.current?.click()}
          >
            Upload images
          </PillButton>

          {loading ? (
            <p className="mt-[20px] text-[14px] leading-[18px]">Laden…</p>
          ) : images.length === 0 ? (
            <p className="mt-[20px] text-[14px] leading-[18px] text-ce-beige">
              Nog geen beelden geüpload. Voeg beelden toe voor automatische publicatie.
            </p>
          ) : (
            <div className="mt-[20px]">
              {images.map((image) => (
                <GalleryImageRow
                  key={image.id}
                  image={image}
                  busy={busyId === image.id}
                  onDelete={() => void handleDelete(image.id)}
                  onTitleSave={(title) => void handleTitleSave(image.id, title)}
                  onTagsSave={(tags) => void handleTagsSave(image.id, tags)}
                />
              ))}
            </div>
          )}
        </>
      }
    />
  )
}

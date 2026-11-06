import { useEffect, useRef, useState } from 'react'
import { fetchGalleryImages } from '../api/gallery'
import { galleryImageTitle } from '../utils/gallery'
import { resolveMediaUrl } from '../utils/mediaUrl'
import type { GalleryImageDto } from '../types/gallery'
import { DeleteConfirmAction } from './ui/DeleteConfirmAction'
import { PillButton } from './PillButton'

export type ImageRatio = 'square' | 'landscape'

export const IMAGE_SPECS: Record<ImageRatio, { label: string; width: number; height: number }> = {
  square: { label: '1080×1080', width: 270, height: 270 },
  landscape: { label: '1200×644', width: Math.round(270 * (1200 / 644)), height: 270 },
}

export function resolveImageRatio(ratio: string): ImageRatio {
  return ratio === 'landscape' ? 'landscape' : 'square'
}

type PickerStep = 'idle' | 'choose-source' | 'gallery'

type ImageUploadProps = {
  ratio: string
  previewUrl?: string | null
  uploading?: boolean
  deleting?: boolean
  onFileSelect?: (file: File) => void
  onGalleryImageSelect?: (imageId: string) => void
  onDelete?: () => void
  /** Gallery-keuze tonen (standaard aan wanneer onGalleryImageSelect gezet is) */
  galleryEnabled?: boolean
  /** Approved/published: alleen tonen, geen upload-knop */
  readOnly?: boolean
}

/** Eén upload-kader — formaat volgt Image ratio in sidebar (single article.png) */
export function ImageUpload({
  ratio,
  previewUrl,
  uploading = false,
  deleting = false,
  onFileSelect,
  onGalleryImageSelect,
  onDelete,
  galleryEnabled,
  readOnly = false,
}: ImageUploadProps) {
  const inputRef = useRef<HTMLInputElement>(null)
  const spec = IMAGE_SPECS[resolveImageRatio(ratio)]
  const canUpload = !readOnly && onFileSelect
  const showGalleryChoice =
    galleryEnabled !== false && Boolean(onGalleryImageSelect) && canUpload
  const busy = uploading || deleting

  const [step, setStep] = useState<PickerStep>('idle')
  const [galleryImages, setGalleryImages] = useState<GalleryImageDto[]>([])
  const [galleryLoading, setGalleryLoading] = useState(false)
  const [galleryError, setGalleryError] = useState<string | null>(null)

  useEffect(() => {
    if (!uploading) {
      setStep('idle')
    }
  }, [uploading])

  useEffect(() => {
    if (step !== 'gallery') return

    let cancelled = false
    setGalleryLoading(true)
    setGalleryError(null)
    void fetchGalleryImages()
      .then((data) => {
        if (!cancelled) setGalleryImages(data.images)
      })
      .catch((err: unknown) => {
        if (!cancelled) {
          setGalleryError(err instanceof Error ? err.message : 'Gallery laden mislukt')
          setGalleryImages([])
        }
      })
      .finally(() => {
        if (!cancelled) setGalleryLoading(false)
      })

    return () => {
      cancelled = true
    }
  }, [step])

  function openPicker() {
    if (busy) return
    if (showGalleryChoice) {
      setStep('choose-source')
      return
    }
    inputRef.current?.click()
  }

  function handleComputerSelect() {
    setStep('idle')
    inputRef.current?.click()
  }

  function handleGalleryPick(imageId: string) {
    onGalleryImageSelect?.(imageId)
  }

  return (
    <section className="mb-[16px]" aria-label="Afbeelding uploaden">
      <div
        className="relative flex max-h-[270px] items-center justify-center overflow-hidden border-[1.5px] border-ce-beige bg-ce-offwhite"
        style={{ width: spec.width, height: spec.height }}
      >
        {canUpload && (
          <input
            ref={inputRef}
            type="file"
            accept="image/png,image/jpeg,image/webp"
            onChange={(e) => {
              const file = e.target.files?.[0]
              if (file) onFileSelect(file)
              e.target.value = ''
            }}
            className="sr-only"
          />
        )}

        <span className="absolute bottom-[12px] left-[12px] z-[1] text-[14px] leading-[18px] text-ce-beige">
          {spec.label}
        </span>

        {step === 'choose-source' && (
          <div className="absolute inset-0 z-20 flex flex-col items-center justify-center gap-[10px] bg-ce-offwhite px-[16px]">
            <PillButton
              type="button"
              variant="main"
              disabled={busy}
              onClick={() => setStep('gallery')}
            >
              Gallery
            </PillButton>
            <PillButton
              type="button"
              variant="second"
              disabled={busy}
              onClick={handleComputerSelect}
            >
              Computer
            </PillButton>
            <button
              type="button"
              className="cursor-pointer border-0 bg-transparent p-0 text-[13px] leading-[17px] text-ce-beige"
              onClick={() => setStep('idle')}
            >
              Annuleren
            </button>
          </div>
        )}

        {step === 'gallery' && (
          <div className="absolute inset-0 z-20 flex flex-col bg-ce-offwhite">
            <div className="flex shrink-0 items-center justify-between border-b border-ce-beige px-[10px] py-[8px]">
              <button
                type="button"
                className="cursor-pointer border-0 bg-transparent p-0 text-[13px] leading-[17px] text-ce-blue"
                onClick={() => setStep('choose-source')}
              >
                ← Terug
              </button>
              <span className="text-[13px] font-semibold leading-[17px] text-ce-black">Gallery</span>
              <span className="w-[48px]" aria-hidden />
            </div>
            <div className="min-h-0 flex-1 overflow-y-auto px-[10px] py-[10px]">
              {galleryLoading ? (
                <p className="m-0 text-center text-[13px] leading-[17px] text-ce-beige">Laden…</p>
              ) : galleryError ? (
                <p className="m-0 text-center text-[13px] leading-[17px] text-ce-red">{galleryError}</p>
              ) : galleryImages.length === 0 ? (
                <p className="m-0 text-center text-[13px] leading-[17px] text-ce-beige">
                  Geen beelden in gallery. Upload eerst via Gallery in het menu.
                </p>
              ) : (
                <div className="grid grid-cols-2 gap-[8px]">
                  {galleryImages.map((image) => (
                    <button
                      key={image.id}
                      type="button"
                      disabled={busy}
                      className="flex cursor-pointer flex-col gap-[4px] border-0 bg-transparent p-0 text-left disabled:cursor-not-allowed disabled:opacity-60"
                      onClick={() => handleGalleryPick(image.id)}
                    >
                      <span className="aspect-square overflow-hidden rounded-[4px] border border-ce-beige">
                        <img
                          src={resolveMediaUrl(image.url) ?? ''}
                          alt={galleryImageTitle(image)}
                          className="h-full w-full object-cover"
                        />
                      </span>
                      <span className="truncate text-[11px] leading-[14px] font-semibold text-ce-black">
                        {galleryImageTitle(image)}
                      </span>
                    </button>
                  ))}
                </div>
              )}
            </div>
          </div>
        )}

        {previewUrl ? (
          <>
            <img
              src={previewUrl}
              alt="Geüploade afbeelding"
              className="absolute inset-0 h-full w-full object-cover"
            />
            {canUpload && step === 'idle' && (
              <PillButton
                type="button"
                variant="second"
                className="absolute bottom-[12px] left-1/2 z-10 -translate-x-1/2"
                disabled={busy}
                loading={uploading}
                onClick={openPicker}
              >
                Vervang afbeelding
              </PillButton>
            )}
          </>
        ) : canUpload && step === 'idle' ? (
          <PillButton
            type="button"
            variant="main"
            className="relative z-10"
            disabled={busy}
            loading={uploading}
            onClick={openPicker}
          >
            Afbeelding toevoegen
          </PillButton>
        ) : step === 'idle' ? (
          <p className="relative z-10 m-0 px-[16px] text-center text-[14px] leading-[18px] text-ce-beige">
            Geen afbeelding
          </p>
        ) : null}
      </div>

      {previewUrl && canUpload && onDelete && step === 'idle' && (
        <div className="mt-[10px] flex justify-center">
          <DeleteConfirmAction
            onConfirm={onDelete}
            disabled={busy}
            busy={deleting}
            deleteLabel="Delete image"
            confirmLabel="Confirm delete"
            busyLabel="…"
          />
        </div>
      )}
    </section>
  )
}

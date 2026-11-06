import { useEffect, useState } from 'react'
import { LIST_ROW, LIST_SECTION } from '../layout/grid.constants'
import { galleryImageTitle } from '../utils/gallery'
import { DeleteConfirmAction } from './ui/DeleteConfirmAction'
import { FieldSingle } from './ui/Field'
import { resolveMediaUrl } from '../utils/mediaUrl'
import type { GalleryImageDto } from '../types/gallery'

type GalleryImageRowProps = {
  image: GalleryImageDto
  busy?: boolean
  onDelete: () => void
  onTitleSave: (title: string) => void
  onTagsSave: (tags: string) => void
}

/** cursor-publish-modes.md — thumbnail, titel, tags, delete */
export function GalleryImageRow({
  image,
  busy = false,
  onDelete,
  onTitleSave,
  onTagsSave,
}: GalleryImageRowProps) {
  const [title, setTitle] = useState(image.altText)
  const [tags, setTags] = useState(image.tags)

  useEffect(() => {
    setTitle(image.altText)
    setTags(image.tags)
  }, [image.altText, image.tags, image.id])

  return (
    <section className={LIST_SECTION.sourcesItemClass}>
      <div className={`${LIST_ROW.rowClass} items-start text-ce-black`}>
        <div className="flex min-w-0 flex-1 items-start gap-[12px]">
          <img
            src={resolveMediaUrl(image.url) ?? ''}
            alt={galleryImageTitle(image)}
            className="mt-[2px] h-[48px] w-[48px] shrink-0 rounded-[4px] border border-ce-beige object-cover"
          />
          <div className="min-w-0 flex-1 space-y-[8px]">
            <FieldSingle
              placeholder="Titel"
              value={title}
              onChange={setTitle}
              onBlur={() => {
                if (title.trim() !== image.altText.trim()) {
                  onTitleSave(title.trim())
                }
              }}
              aria-label="Titel"
              className="max-w-[420px]"
            />
            <p className="m-0 truncate text-[13px] leading-[17px] text-ce-beige">{image.filename}</p>
            <FieldSingle
              placeholder="Tags (kommagescheiden)"
              value={tags}
              onChange={setTags}
              onBlur={() => {
                if (tags.trim() !== image.tags.trim()) {
                  onTagsSave(tags.trim())
                }
              }}
              aria-label="Tags"
              className="max-w-[420px]"
            />
          </div>
        </div>
        <div className={`${LIST_ROW.actionsClass} pt-[2px]`}>
          <DeleteConfirmAction onConfirm={onDelete} disabled={busy} busy={busy} />
        </div>
      </div>
    </section>
  )
}

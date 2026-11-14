import { useEffect, useState } from 'react'
import { useLocation, useNavigate } from 'react-router-dom'
import { GridShell } from '../layout/GridShell'
import { BREADCRUMB_PUBLICATION_ARTICLE, NavBreadcrumb } from '../components/NavBreadcrumb'
import { FieldMulti, FieldSingle } from '../components/ui/Field'
import { PillButton, pillActionsRowClass } from '../components/PillButton'
import { createFastlaneArticle } from '../api/fastlane'
import { uploadArticleImage } from '../api/articles'
import { fetchObjectives } from '../api/objectives'
import { isApiConfigured } from '../api/client'
import { ImageUpload, resolveImageRatio } from '../components/ImageUpload'
import { ArticleMetaSidebar } from '../components/ArticleMetaSidebar'
import { LIST_SECTION, GRID2_REVIEW_MAIN_WIDTH } from '../layout/grid.constants'
import { useToneOptions } from '../hooks/useToneOptions'
import { useEditorOptions } from '../hooks/useEditorOptions'
import { DEFAULT_META_ANGLE, defaultToneFrom } from '../constants/metaOptions'

const DEFAULT_RATIO = 'square'
const DEFAULT_PLATFORM = 'Linkedin'

type FormState = {
  title: string
  researchUrls: string[]
  extraThoughts: string
  rationale: string
  tone: string
  angle: string
  editor: string | null
  imageRatio: string
  platform: string
  objective: string | null
}

const emptyForm = (): FormState => ({
  title: '',
  researchUrls: ['', '', ''],
  extraThoughts: '',
  rationale: '',
  tone: '',
  angle: DEFAULT_META_ANGLE,
  editor: null,
  imageRatio: DEFAULT_RATIO,
  platform: DEFAULT_PLATFORM,
  objective: null,
})

/** Fastlane — briefing/fastlaneType+.png; zelfde grid als Article */
export function FastlanePage() {
  const navigate = useNavigate()
  const location = useLocation()
  const [form, setForm] = useState<FormState>(emptyForm)
  const { toneOptions } = useToneOptions()
  const { editorOptions } = useEditorOptions(form.editor)
  const [objectiveOptions, setObjectiveOptions] = useState<
    Array<{ id: string; label: string; percentage: number }>
  >([])
  const [imageFile, setImageFile] = useState<File | null>(null)
  const [imagePreview, setImagePreview] = useState<string | null>(null)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    const prefill = (location.state as { prefillContent?: string } | null)?.prefillContent?.trim()
    if (!prefill) return
    setForm((prev) => ({
      ...prev,
      extraThoughts: prefill,
      title: prev.title.trim() ? prev.title : 'Eigen artikel',
    }))
  }, [location.state])

  useEffect(() => {
    if (!isApiConfigured()) return
    void fetchObjectives()
      .then((data) => {
        const options = (data.objectives ?? []).map((item) => ({
          id: item.id,
          label: item.label,
          percentage: item.percentage,
        }))
        setObjectiveOptions(options)
        setForm((prev) => {
          if (prev.objective || options.length === 0) return prev
          return { ...prev, objective: options[0]?.id ?? null }
        })
      })
      .catch(() => {
        setObjectiveOptions([])
      })
  }, [])

  useEffect(() => {
    if (toneOptions.length === 0) return
    setForm((prev) => {
      if (prev.tone && toneOptions.includes(prev.tone)) return prev
      return { ...prev, tone: defaultToneFrom(toneOptions) }
    })
  }, [toneOptions])

  function updateUrl(index: number, value: string) {
    setForm((prev) => ({
      ...prev,
      researchUrls: prev.researchUrls.map((url, i) => (i === index ? value : url)),
    }))
  }

  function handleUndo() {
    if (imagePreview) URL.revokeObjectURL(imagePreview)
    setForm({
      ...emptyForm(),
      objective: objectiveOptions[0]?.id ?? null,
    })
    setImageFile(null)
    setImagePreview(null)
    setError(null)
  }

  function handleImageSelect(file: File) {
    setImageFile(file)
    setImagePreview((prev) => {
      if (prev) URL.revokeObjectURL(prev)
      return URL.createObjectURL(file)
    })
  }

  async function handleContinueToResearch() {
    if (!form.title.trim()) {
      setError('Thema van het artikel is verplicht')
      return
    }

    setSubmitting(true)
    setError(null)

    try {
      if (!isApiConfigured()) {
        navigate('/research/demo')
        return
      }

      const result = await createFastlaneArticle({
        title: form.title.trim(),
        researchUrls: form.researchUrls.map((url) => url.trim()).filter(Boolean),
        extraThoughts: form.extraThoughts.trim() || undefined,
        tone: form.tone,
        angle: form.angle,
        editor: form.editor || undefined,
        imageRatio: form.imageRatio,
        platform: form.platform,
        rationale: form.rationale.trim() || undefined,
        objective: form.objective || undefined,
      })

      if (imageFile) {
        try {
          await uploadArticleImage(result.slotId, resolveImageRatio(form.imageRatio), imageFile)
        } catch (uploadErr) {
          navigate(`/research/${result.slotId}`, {
            state: {
              pollResearch: true,
              imageUploadWarning:
                uploadErr instanceof Error ? uploadErr.message : 'Afbeelding upload mislukt',
            },
          })
          return
        }
      }

      navigate(`/research/${result.slotId}`, { state: { pollResearch: true } })
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Snel artikel mislukt')
    } finally {
      setSubmitting(false)
    }
  }

  const busy = submitting

  return (
    <GridShell
      pageTitle="Snel artikel"
      pageSubtitle="Artikel buiten het weekritme"
      activeNav="article"
      breadcrumb={
        <NavBreadcrumb items={[...BREADCRUMB_PUBLICATION_ARTICLE, { label: 'Snel artikel' }]} />
      }
      mainLayout="grid2"
      mainContentMaxWidth={GRID2_REVIEW_MAIN_WIDTH}
      metaAlign="flush"
      main={
        <>
          <ImageUpload
            ratio={form.imageRatio}
            previewUrl={imagePreview}
            uploading={false}
            onFileSelect={handleImageSelect}
          />

          <div className={LIST_SECTION.bodyStackClass}>
            <FieldSingle
              id="fl-theme"
              variant="title"
              showPencil
              placeholderTone="blue"
              value={form.title}
              onChange={(title) => setForm((prev) => ({ ...prev, title }))}
              placeholder="Thema van het artikel"
            />

            {form.researchUrls.map((url, index) => (
              <FieldSingle
                key={index}
                id={`fl-research-${index}`}
                value={url}
                onChange={(value) => updateUrl(index, value)}
                placeholder={`Bronlink ${index + 1}`}
                placeholderTone="black"
              />
            ))}

            <FieldMulti
              id="fl-extra"
              value={form.extraThoughts}
              onChange={(extraThoughts) => setForm((prev) => ({ ...prev, extraThoughts }))}
              placeholder="Extra gedachten"
              minHeight={120}
            />
          </div>

          {error && <p className="mt-[12px] mb-0 text-[14px] leading-[18px] text-ce-red">{error}</p>}

          <div className={pillActionsRowClass}>
            <PillButton
              variant="main"
              disabled={busy || !form.title.trim()}
              loading={busy}
              onClick={() => void handleContinueToResearch()}
            >
              Verder naar onderzoek
            </PillButton>
            <PillButton variant="second" disabled={busy} onClick={handleUndo}>
              Ongedaan maken
            </PillButton>
          </div>
        </>
      }
      meta={
        <ArticleMetaSidebar
          rationale={form.rationale}
          onRationaleChange={(rationale) => setForm((prev) => ({ ...prev, rationale }))}
          rationaleHint="Optioneel — verfijn tijdens het onderzoek"
          tone={form.tone}
          editor={form.editor}
          imageRatio={form.imageRatio}
          platform={form.platform}
          onToneChange={(tone) => setForm((prev) => ({ ...prev, tone }))}
          onEditorChange={(editor) => setForm((prev) => ({ ...prev, editor }))}
          onImageRatioChange={(imageRatio) => setForm((prev) => ({ ...prev, imageRatio }))}
          onPlatformChange={(platform) => setForm((prev) => ({ ...prev, platform }))}
          objective={form.objective}
          objectiveOptions={objectiveOptions}
          onObjectiveChange={(objective) => setForm((prev) => ({ ...prev, objective }))}
          toneOptions={toneOptions}
          editorOptions={editorOptions}
        />
      }
    />
  )
}

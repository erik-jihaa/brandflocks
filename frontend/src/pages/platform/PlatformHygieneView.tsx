import { useCallback, useEffect, useState } from 'react'
import { LIST_SECTION } from '../../layout/grid.constants'
import { PillButton } from '../../components/PillButton'
import { FieldSingle } from '../../components/ui/Field'
import { SaveNotice } from '../../components/ui/SaveNotice'
import { useSaveNotice } from '../../hooks/useSaveNotice'
import {
  activatePlatformHygieneList,
  addPlatformHygieneCategoryFactor,
  createPlatformHygieneDraft,
  distillPlatformHygieneList,
  fetchPlatformHygiene,
  savePlatformHygieneCompetitors,
  scrapePlatformHygieneList,
  updatePlatformHygieneFactor,
  type PlatformHygieneDetail,
  type PlatformHygieneFactor,
} from '../../api/platform'

const CONTEXT = 'b2b_complex_sale'

const DEFAULT_COMPETITORS = [
  { name: 'HubSpot', baseUrl: 'https://www.hubspot.com' },
  { name: 'Salesforce', baseUrl: 'https://www.salesforce.com' },
  { name: 'monday.com', baseUrl: 'https://monday.com' },
  { name: 'Notion', baseUrl: 'https://www.notion.so' },
  { name: 'Atlassian', baseUrl: 'https://www.atlassian.com' },
  { name: 'Zendesk', baseUrl: 'https://www.zendesk.com' },
]

type CompetitorDraft = { name: string; baseUrl: string }

function reviewLabel(status: string): string {
  switch (status) {
    case 'approved':
      return 'Goedgekeurd'
    case 'rejected':
      return 'Afgewezen'
    default:
      return 'Te beoordelen'
  }
}

export function PlatformHygieneView() {
  const { message, kind, fading, showSaving, showSuccess, showError } = useSaveNotice()
  const [detail, setDetail] = useState<PlatformHygieneDetail | null>(null)
  const [competitors, setCompetitors] = useState<CompetitorDraft[]>(DEFAULT_COMPETITORS)
  const [categoryPhrase, setCategoryPhrase] = useState('')
  const [busy, setBusy] = useState(false)

  const workingListId = detail?.workingListId ?? detail?.draft?.listId ?? null

  const reload = useCallback(async () => {
    const res = await fetchPlatformHygiene(CONTEXT)
    setDetail(res.detail)
    if (res.detail.competitors.length > 0) {
      setCompetitors(res.detail.competitors.map((c) => ({ name: c.name, baseUrl: c.baseUrl })))
    }
  }, [])

  useEffect(() => {
    reload().catch((err) => showError(err instanceof Error ? err.message : 'Laden mislukt'))
  }, [reload, showError])

  async function ensureDraft(): Promise<string> {
    if (workingListId) return workingListId
    const created = await createPlatformHygieneDraft(CONTEXT)
    await reload()
    return created.listId
  }

  async function handleSaveCompetitors() {
    setBusy(true)
    showSaving('Concurrenten opslaan…')
    try {
      const listId = await ensureDraft()
      await savePlatformHygieneCompetitors(listId, competitors.filter((c) => c.name.trim() && c.baseUrl.trim()))
      showSuccess('Concurrenten opgeslagen')
      await reload()
    } catch (err) {
      showError(err instanceof Error ? err.message : 'Opslaan mislukt')
    } finally {
      setBusy(false)
    }
  }

  async function handleScrape() {
    if (!workingListId) return
    setBusy(true)
    showSaving('Websites scrapen…')
    try {
      await scrapePlatformHygieneList(workingListId)
      showSuccess('Scrape voltooid')
      await reload()
    } catch (err) {
      showError(err instanceof Error ? err.message : 'Scrape mislukt')
    } finally {
      setBusy(false)
    }
  }

  async function handleDistill() {
    if (!workingListId) return
    setBusy(true)
    showSaving('Categorietaal destilleren…')
    try {
      const res = await distillPlatformHygieneList(workingListId)
      showSuccess(`Distillatie klaar (${res.inserted ?? 0} voorstellen)`)
      await reload()
    } catch (err) {
      showError(err instanceof Error ? err.message : 'Distillatie mislukt')
    } finally {
      setBusy(false)
    }
  }

  async function handleFactorReview(factor: PlatformHygieneFactor, reviewStatus: 'approved' | 'rejected') {
    setBusy(true)
    try {
      await updatePlatformHygieneFactor(factor.id, { reviewStatus })
      await reload()
    } catch (err) {
      showError(err instanceof Error ? err.message : 'Bijwerken mislukt')
    } finally {
      setBusy(false)
    }
  }

  async function handleAddCategory() {
    if (!workingListId || categoryPhrase.trim() === '') return
    setBusy(true)
    try {
      await addPlatformHygieneCategoryFactor(workingListId, categoryPhrase.trim())
      setCategoryPhrase('')
      showSuccess('Categorievoorwaarde toegevoegd')
      await reload()
    } catch (err) {
      showError(err instanceof Error ? err.message : 'Toevoegen mislukt')
    } finally {
      setBusy(false)
    }
  }

  async function handleActivate() {
    if (!workingListId) return
    setBusy(true)
    showSaving('Lijst activeren…')
    try {
      await activatePlatformHygieneList(workingListId)
      showSuccess('Hygiënefactoren actief voor dit contextprofiel')
      await reload()
    } catch (err) {
      showError(err instanceof Error ? err.message : 'Activeren mislukt')
    } finally {
      setBusy(false)
    }
  }

  const pendingCount = detail?.factors.filter((f) => f.reviewStatus === 'pending').length ?? 0

  return (
    <div className={`${LIST_SECTION} flex flex-col gap-[24px]`}>
      <div>
        <h2 className="m-0 text-[24px] font-semibold leading-[32px] text-ce-black">Hygiënefactoren</h2>
        <p className="mt-[8px] mb-0 max-w-[640px] text-[14px] leading-[21px] text-ce-black/80">
          Contextprofiel <strong>B2B complex sale</strong> — waargenomen categorietaal uit concurrent-websites.
          Alleen zichtbaar voor strateeg; gaat mee in HBA-scoring voor distinctiviteit.
        </p>
        {detail?.active && (
          <p className="mt-[8px] mb-0 text-[14px] text-ce-black">
            Actieve lijst: v{detail.active.version} ({detail.status.factorCount} factoren)
          </p>
        )}
      </div>

      <SaveNotice message={message} kind={kind} fading={fading} />

      <section className="flex flex-col gap-[12px]">
        <h3 className="m-0 text-[18px] font-semibold text-ce-black">1. Concurrenten (6–10)</h3>
        {competitors.map((row, index) => (
          <div key={index} className="grid grid-cols-2 gap-[12px]">
            <FieldSingle
              label="Naam"
              value={row.name}
              onChange={(v) =>
                setCompetitors((prev) => prev.map((c, i) => (i === index ? { ...c, name: v } : c)))
              }
            />
            <FieldSingle
              label="Website"
              value={row.baseUrl}
              onChange={(v) =>
                setCompetitors((prev) => prev.map((c, i) => (i === index ? { ...c, baseUrl: v } : c)))
              }
            />
          </div>
        ))}
        <div className="flex flex-wrap gap-[8px]">
          <PillButton variant="main" onClick={handleSaveCompetitors} disabled={busy}>
            Opslaan
          </PillButton>
          <PillButton variant="second" onClick={handleScrape} disabled={busy || !workingListId}>
            Scrape websites
          </PillButton>
          <PillButton variant="second" onClick={handleDistill} disabled={busy || !workingListId}>
            Destilleer (LLM)
          </PillButton>
        </div>
      </section>

      <section className="flex flex-col gap-[12px]">
        <h3 className="m-0 text-[18px] font-semibold text-ce-black">2. Beoordelen</h3>
        {detail?.factors.length === 0 && (
          <p className="m-0 text-[14px] text-ce-beige">Nog geen factoren — scrape en destilleer eerst.</p>
        )}
        {detail?.factors.map((factor) => (
          <div
            key={factor.id}
            className="rounded-[12px] border border-ce-beige/40 bg-white px-[12px] py-[10px]"
          >
            <div className="flex flex-wrap items-start justify-between gap-[8px]">
              <div>
                <p className="m-0 text-[14px] font-semibold text-ce-black">{factor.phrase}</p>
                <p className="mt-[4px] mb-0 text-[12px] text-ce-beige">
                  {factor.kind === 'category_condition' ? 'Categorievoorwaarde' : 'Waargenomen'}
                  {factor.competitorCount ? ` · ${factor.competitorCount} concurrenten` : ''}
                  {' · '}
                  {reviewLabel(factor.reviewStatus)}
                </p>
              </div>
              {factor.reviewStatus === 'pending' && (
                <div className="flex gap-[8px]">
                  <PillButton variant="main" onClick={() => handleFactorReview(factor, 'approved')} disabled={busy}>
                    Bevestig
                  </PillButton>
                  <PillButton variant="second" onClick={() => handleFactorReview(factor, 'rejected')} disabled={busy}>
                    Schrap
                  </PillButton>
                </div>
              )}
            </div>
          </div>
        ))}
      </section>

      <section className="flex flex-col gap-[12px]">
        <h3 className="m-0 text-[18px] font-semibold text-ce-black">3. Categorievoorwaarde toevoegen</h3>
        <FieldSingle
          label="Formulering"
          value={categoryPhrase}
          onChange={setCategoryPhrase}
          placeholder="Bijv. Betrouwbare cloud-infrastructuur"
        />
        <PillButton variant="second" onClick={handleAddCategory} disabled={busy || !workingListId}>
          Toevoegen
        </PillButton>
      </section>

      <section>
        <PillButton
          variant="main"
          onClick={handleActivate}
          disabled={busy || !workingListId || pendingCount > 0}
        >
          Lijst activeren
        </PillButton>
        {pendingCount > 0 && (
          <p className="mt-[8px] mb-0 text-[14px] text-ce-beige">
            Bevestig eerst {pendingCount} openstaande regel(s).
          </p>
        )}
      </section>
    </div>
  )
}

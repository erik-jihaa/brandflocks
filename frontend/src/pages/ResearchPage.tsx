import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { useLocation, useNavigate, useParams } from 'react-router-dom'
import { GridShell } from '../layout/GridShell'
import { BREADCRUMB_PUBLICATION_ARTICLE, NavBreadcrumb } from '../components/NavBreadcrumb'
import { LoadingDots } from '../components/LoadingDots'
import {
  normalizeResearchFeedback,
  ResearchReviewRow,
  type ResearchFeedback,
} from '../components/ResearchReviewRow'
import { PillButton } from '../components/PillButton'
import { ActionLink } from '../components/ui/ActionLink'
import { ActionNotice } from '../components/ui/ActionNotice'
import { useActionNotice } from '../hooks/useActionNotice'
import {
  fetchPendingResearch,
  fetchSlotResearch,
  saveResearchFeedback,
  submitSlotResearch,
  triggerSlotResearch,
  type PendingResearchItem,
  type ResearchThemeGroup,
} from '../api/onboarding'
import { useAgenda } from '../hooks/useAgenda'
import { isApiConfigured } from '../api/client'
import { researchMock } from '../data/research.mock'
import type { ResearchItemDto } from '../types/onboarding'
import type { AgendaDto } from '../types/api'
import { resolveAgendaPhase } from '../utils/agendaActions'
import { GRID2_REVIEW_MAIN_WIDTH, LIST_SECTION } from '../layout/grid.constants'
import { bumpAgendaRefresh } from '../utils/agendaRefresh'
import { resolveResearchArticleUrl } from '../utils/themeUrl'
import { pulldownBoxClass, frameLineGapBeforeClass } from '../design/identity.constants'
import {
  fetchResearchVerdicts,
  fetchThemeVerdicts,
  runResearchVerdicts,
  runThemeVerdicts,
  type HbaResearchVerdict,
  type HbaThemeVerdict,
} from '../api/hbaV2'
import { ThemeFitAdvice } from '../components/ThemeFitAdvice'
import {
  themeVerdictConflictsWithArchetype,
  themeVerdictToelichtingForDisplay,
} from '../utils/themeVerdict'
import { lichtOnderbouwdLabel as lichtOnderbouwdLabelFromCount } from '../utils/themeOnderbouwing'

const RESEARCH_SUBMIT_NAV_DELAY_MS = 1200
const MAX_ITEMS = 10

function draftFromItems(items: ResearchItemDto[]): Record<string, ResearchFeedback> {
  return Object.fromEntries(items.map((item) => [item.id, normalizeResearchFeedback(item.feedback)]))
}

function dirtyItems(
  items: ResearchItemDto[],
  draft: Record<string, ResearchFeedback>,
): Array<{ id: string; feedback: 'liked' | 'forgotten' | 'none' }> {
  return items
    .filter((item) => draft[item.id] !== normalizeResearchFeedback(item.feedback))
    .map((item) => ({ id: item.id, feedback: draft[item.id] ?? 'none' }))
}

function normalizeCompareText(value: string): string {
  return value.replace(/\s+/g, ' ').trim().toLowerCase()
}

function mergeThemeGroupsByTitle(groups: ResearchThemeGroup[]): ResearchThemeGroup[] {
  const merged: ResearchThemeGroup[] = []
  const indexByTitle = new Map<string, number>()

  for (const group of groups) {
    const titleKey = normalizeCompareText(group.themeTitle)
    const existingIndex = titleKey !== '' ? indexByTitle.get(titleKey) : undefined
    if (existingIndex === undefined) {
      if (titleKey !== '') {
        indexByTitle.set(titleKey, merged.length)
      }
      merged.push({ ...group, items: [...group.items] })
      continue
    }

    const existing = merged[existingIndex]
    const seenIds = new Set(existing.items.map((item) => item.id))
    merged[existingIndex] = {
      ...existing,
      themeRationale: existing.themeRationale || group.themeRationale,
      expectsArticleWrite: existing.expectsArticleWrite || group.expectsArticleWrite,
      items: [
        ...existing.items,
        ...group.items.filter((item) => !seenIds.has(item.id)),
      ].slice(0, MAX_ITEMS),
    }
  }

  return merged
}

function normalizeThemeGroups(
  data: {
    themeGroups?: ResearchThemeGroup[]
    items: ResearchItemDto[]
    slotId: string
    slotTheme: string
    themeRationale?: string
    expectsArticleWrite?: boolean
  },
): ResearchThemeGroup[] {
  if (data.themeGroups && data.themeGroups.length > 0) {
    return mergeThemeGroupsByTitle(
      data.themeGroups.map((group) => ({
        ...group,
        items: group.items.slice(0, MAX_ITEMS),
      })),
    )
  }

  return [
    {
      slotId: data.slotId,
      themeId: '',
      themeTitle: data.slotTheme || 'Thema',
      themeRationale: data.themeRationale?.trim() ?? '',
      expectsArticleWrite: data.expectsArticleWrite,
      items: data.items.slice(0, MAX_ITEMS),
    },
  ]
}

function groupPendingItemsByTheme(items: PendingResearchItem[]): ResearchThemeGroup[] {
  const groups: ResearchThemeGroup[] = []
  const indexByKey = new Map<string, number>()

  for (const item of items) {
    const key = item.themeId || item.slotId || item.id
    let groupIndex = indexByKey.get(key)
    if (groupIndex === undefined) {
      groupIndex = groups.length
      indexByKey.set(key, groupIndex)
      groups.push({
        slotId: item.slotId,
        themeId: item.themeId,
        themeTitle: item.themeTitle,
        themeRationale: item.themeRationale,
        items: [],
      })
    }
    groups[groupIndex].items.push(item)
  }

  return mergeThemeGroupsByTitle(groups)
}

function findDefaultResearchSlotId(agenda: AgendaDto): string | undefined {
  const currentGroup =
    agenda.groups.find((group) => group.weekNumber === agenda.currentWeek) ?? agenda.groups[0]

  const inWeek = currentGroup?.rows.find((row) => {
    const phase = resolveAgendaPhase(row)
    return phase === 'research' || row.slotStatus === 'themed'
  })
  if (inWeek?.slotId) return inWeek.slotId

  for (const group of agenda.groups) {
    for (const row of group.rows) {
      const phase = resolveAgendaPhase(row)
      if (phase === 'research' || row.slotStatus === 'themed') {
        return row.slotId
      }
    }
  }

  return agenda.groups[0]?.rows[0]?.slotId
}

/** Research-scherm — briefing/research.png */
export function ResearchPage() {
  const navigate = useNavigate()
  const location = useLocation()
  const { slotId: routeSlotId } = useParams<{ slotId?: string }>()
  const pollResearch = (location.state as { pollResearch?: boolean } | null)?.pollResearch === true
  const imageUploadWarning =
    (location.state as { imageUploadWarning?: string } | null)?.imageUploadWarning ?? null
  const { agenda, loading: agendaLoading } = useAgenda()
  const [slotId, setSlotId] = useState(routeSlotId ?? '')
  const [themeGroups, setThemeGroups] = useState<ResearchThemeGroup[]>([])
  const [activeThemeIndex, setActiveThemeIndex] = useState(0)
  const [weekLabel, setWeekLabel] = useState('')
  const [lichtOnderbouwdLabel, setLichtOnderbouwdLabel] = useState<string | null>(null)
  const [expectsArticleWrite, setExpectsArticleWrite] = useState(false)
  const [draftFeedback, setDraftFeedback] = useState<Record<string, ResearchFeedback>>({})
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [note, setNote] = useState<string | null>(null)
  const [usingMock, setUsingMock] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const {
    message: submitNotice,
    kind: submitNoticeKind,
    fading: submitNoticeFading,
    showSuccess: showSubmitSuccess,
    clear: clearSubmitNotice,
  } = useActionNotice()
  const [triggering, setTriggering] = useState(false)
  const [pollingMore, setPollingMore] = useState(false)
  const [awaitingResearchPoll, setAwaitingResearchPoll] = useState(false)
  const [reviewQueue, setReviewQueue] = useState<PendingResearchItem[]>([])
  const submittingRef = useRef(false)
  const submitNavigateTimerRef = useRef<number | null>(null)
  const autoResearchSlotRef = useRef<string | null>(null)
  const visibleItemsCountRef = useRef(0)
  /** Na like/forget: geen auto CE-02 of redirect naar Article — alleen via Submit. */
  const suppressPipelineSideEffectsRef = useRef(false)
  /**
   * Queue-mode ("open voorstellen" backlog, auto-save per like/forget) mag alleen als de
   * pagina zonder specifieke slot is geopend (sidebar-link "Research"). Kom je via Fastlane of
   * Agenda-"Edit" met een concrete slotId, dan blijft het altijd single-slot: keuzes blijven
   * lokaal staan tot Submit, ook als er toevallig andere pending research-items bestaan.
   */
  const allowQueueModeRef = useRef(!routeSlotId)
  const [pendingClearedByReview, setPendingClearedByReview] = useState(false)
  const reviewedSlotIdsRef = useRef<Set<string>>(new Set())
  const [verdicts, setVerdicts] = useState<Record<string, HbaResearchVerdict>>({})
  const [watOntbreekt, setWatOntbreekt] = useState<string | null>(null)
  const [verdictError, setVerdictError] = useState<string | null>(null)
  const [guardrailNotice, setGuardrailNotice] = useState<string | null>(null)
  const [verdictsLoading, setVerdictsLoading] = useState(false)
  const [positioningArchetype, setPositioningArchetype] = useState<string | null>(null)
  const [themeFit, setThemeFit] = useState<HbaThemeVerdict | null>(null)
  const [themeFitLoading, setThemeFitLoading] = useState(false)

  useEffect(() => {
    if (routeSlotId) {
      setSlotId(routeSlotId)
      return
    }
    if (agenda && !slotId) {
      const defaultSlot = findDefaultResearchSlotId(agenda)
      if (defaultSlot) setSlotId(defaultSlot)
    }
  }, [routeSlotId, agenda, slotId])

  useEffect(
    () => () => {
      if (submitNavigateTimerRef.current !== null) {
        window.clearTimeout(submitNavigateTimerRef.current)
      }
    },
    [],
  )

  function scheduleNavigateAfterNotice(action: () => void) {
    if (submitNavigateTimerRef.current !== null) {
      window.clearTimeout(submitNavigateTimerRef.current)
    }
    submitNavigateTimerRef.current = window.setTimeout(() => {
      submitNavigateTimerRef.current = null
      action()
    }, RESEARCH_SUBMIT_NAV_DELAY_MS)
  }

  const applyResearchData = useCallback(
    (
      data: {
        items: ResearchItemDto[]
        slotId: string
        slotTheme: string
        weekLabel?: string
        themeRationale?: string
        expectsArticleWrite?: boolean
        themeGroups?: ResearchThemeGroup[]
        note?: string
        slotStatus?: string
        usableResearchCount?: number
        lichtOnderbouwdLabel?: string | null
      },
      options?: { allowResearchDoneRedirect?: boolean },
    ) => {
      if (
        options?.allowResearchDoneRedirect === true &&
        data.slotStatus === 'research_done' &&
        data.slotId
      ) {
        navigate(`/article/${data.slotId}?writing=1`, { replace: true })
        return
      }
      const groups = normalizeThemeGroups(data)
      const limitedItems = groups.flatMap((group) => group.items)
      if (limitedItems.length > 0) {
        setError(null)
        setAwaitingResearchPoll(false)
        setPollingMore(false)
      }
      setThemeGroups(groups)
      const anchorIdx = groups.findIndex((group) => group.slotId === data.slotId)
      setActiveThemeIndex(anchorIdx >= 0 ? anchorIdx : 0)
      setWeekLabel(data.weekLabel?.trim() ?? '')
      const usable =
        typeof data.usableResearchCount === 'number'
          ? data.usableResearchCount
          : limitedItems.length
      setLichtOnderbouwdLabel(
        data.lichtOnderbouwdLabel ?? lichtOnderbouwdLabelFromCount(usable),
      )
      setExpectsArticleWrite(groups.some((group) => group.expectsArticleWrite === true))
      setDraftFeedback((prev) => {
        const fromServer = draftFromItems(limitedItems)
        if (submittingRef.current) {
          return { ...fromServer, ...prev }
        }
        const merged = { ...fromServer }
        for (const item of limitedItems) {
          const local = prev[item.id]
          if (local !== undefined && local !== normalizeResearchFeedback(item.feedback)) {
            merged[item.id] = local
          }
        }
        return merged
      })
      if (data.note) setNote(data.note)
    },
    [navigate],
  )

  const load = useCallback(
    async (id: string) => {
      const anchorId = id === '__pending__' ? '' : id
      setLoading(true)
      setError(null)

      if (!isApiConfigured()) {
        if (!anchorId) {
          setLoading(false)
          return
        }
        applyResearchData(researchMock)
        const mockQueue: PendingResearchItem[] = researchMock.themeGroups.flatMap((group) =>
          group.items.map((item) => ({
            ...item,
            slotId: group.slotId,
            themeId: group.themeId,
            themeTitle: group.themeTitle,
            themeRationale: group.themeRationale,
            weekLabel: researchMock.weekLabel,
          })),
        )
        setReviewQueue(mockQueue)
        setThemeGroups(groupPendingItemsByTheme(mockQueue))
        setActiveThemeIndex(0)
        setUsingMock(true)
        setLoading(false)
        return
      }

      try {
        const pending = allowQueueModeRef.current
          ? await fetchPendingResearch()
          : { items: [] as PendingResearchItem[], total: 0 }
        if (pending.items.length > 0) {
          const groups = groupPendingItemsByTheme(pending.items)
          setReviewQueue(pending.items)
          setThemeGroups(groups)
          reviewedSlotIdsRef.current = new Set(
            pending.items.map((item) => item.slotId).filter(Boolean),
          )
          setWeekLabel(pending.items[0]?.weekLabel?.trim() ?? '')
          const preferredSlot = anchorId || routeSlotId || ''
          let startIndex = 0
          if (preferredSlot) {
            const idx = groups.findIndex((group) => group.slotId === preferredSlot)
            if (idx >= 0) startIndex = idx
          }
          setActiveThemeIndex(startIndex)
          setDraftFeedback((prev) => {
            const fromServer = draftFromItems(pending.items)
            const merged = { ...fromServer }
            for (const item of pending.items) {
              const local = prev[item.id]
              if (local !== undefined && local !== normalizeResearchFeedback(item.feedback)) {
                merged[item.id] = local
              }
            }
            return merged
          })
          setUsingMock(false)
          return
        }

        setReviewQueue([])
        if (!anchorId) {
          setUsingMock(false)
          return
        }
        const data = await fetchSlotResearch(anchorId)
        applyResearchData(data)
        setUsingMock(false)
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Laden mislukt')
        setReviewQueue([])
        if (anchorId) {
          applyResearchData({ items: [], slotTheme: '', slotId: anchorId })
        }
        setUsingMock(false)
      } finally {
        setLoading(false)
      }
    },
    [applyResearchData, routeSlotId],
  )

  useEffect(() => {
    if (isApiConfigured()) {
      void load(slotId || '__pending__')
      return
    }
    if (slotId) void load(slotId)
  }, [slotId, load])

  useEffect(() => {
    if (themeGroups.length === 0) return
    const idx = themeGroups.findIndex((group) => group.slotId === slotId)
    if (idx >= 0) {
      setActiveThemeIndex(idx)
    }
  }, [slotId, themeGroups])

  const queueMode = reviewQueue.length > 0
  const activeThemeGroup = themeGroups[activeThemeIndex] ?? themeGroups[0]
  const showThemeNav = themeGroups.length > 1 && Boolean(activeThemeGroup)
  const canPrevTheme = activeThemeIndex > 0
  const canNextTheme = activeThemeIndex < themeGroups.length - 1

  function goToThemeIndex(index: number) {
    const group = themeGroups[index]
    if (!group) return
    setActiveThemeIndex(index)
    if (group.slotId && group.slotId !== slotId) {
      navigate(`/research/${group.slotId}`, { replace: true })
    }
  }

  const visibleItems = useMemo(
    () =>
      queueMode
        ? themeGroups.flatMap((group) => group.items)
        : themeGroups.flatMap((group) => group.items.slice(0, MAX_ITEMS)),
    [queueMode, themeGroups],
  )
  visibleItemsCountRef.current = visibleItems.length

  const activeThemeItems = useMemo(() => activeThemeGroup?.items ?? [], [activeThemeGroup])
  const activeSlotId = activeThemeGroup?.slotId ?? ''
  /** HBA altijd op de slot uit de URL (agenda/lijst), niet op een andere week-groep. */
  const hbaSlotId = (routeSlotId ?? activeSlotId).trim()
  const hbaThemeItems = useMemo(() => {
    if (!hbaSlotId) return activeThemeItems
    const group = themeGroups.find((g) => g.slotId === hbaSlotId)
    return group?.items?.length ? group.items : activeThemeItems
  }, [hbaSlotId, themeGroups, activeThemeItems])
  const hbaItemKey = hbaThemeItems.map((item) => item.id).join(',')

  useEffect(() => {
    if (usingMock || !hbaSlotId || hbaThemeItems.length === 0) {
      return
    }

    let cancelled = false
    const apply = (res: {
      ok?: boolean
      verdicts?: HbaResearchVerdict[]
      watOntbreekt?: string | null
      error?: string
      code?: string
      architectureFootnote?: string
      positioningArchetype?: string | null
    }) => {
      if (cancelled) return
      if (res.positioningArchetype !== undefined) {
        setPositioningArchetype(res.positioningArchetype)
      }
      if (res.code === 'architecture_incomplete') {
        setVerdictError('Vul eerst je merkarchitectuur in op Merkarchitectuur.')
        return
      }
      if (res.code === 'guardrail_inactive') {
        setGuardrailNotice(
          res.architectureFootnote ??
            'Positioneringsadvies op onderzoek start na de architectuurcheck. Open Merkarchitectuur (stap 7) om die af te ronden.',
        )
        setVerdictError(null)
        return
      }
      if (res.verdicts && res.verdicts.length > 0) {
        setVerdicts((prev) => {
          const map = { ...prev }
          for (const v of res.verdicts ?? []) map[v.itemId] = v
          return map
        })
        const wat = res.watOntbreekt ?? null
        setWatOntbreekt(
          themeVerdictConflictsWithArchetype(wat, res.positioningArchetype) ? null : wat,
        )
      } else if (res.verdicts) {
        setVerdicts({})
      }
      if (res.error && res.code !== 'architecture_incomplete') {
        setVerdictError(res.error)
      }
    }

    setVerdictError(null)
    setGuardrailNotice(null)
    setWatOntbreekt(null)
    setVerdictsLoading(true)

    void fetchResearchVerdicts(hbaSlotId)
      .then(apply)
      .catch(() => {})

    void runResearchVerdicts(hbaSlotId)
      .then((res) => {
        apply(res)
        if (!cancelled && !res.ok && res.code !== 'architecture_incomplete' && res.code !== 'guardrail_inactive' && !res.verdicts?.length) {
          setVerdictError(res.error || 'Onderzoeksadvies kon niet worden geladen.')
        }
      })
      .catch((err) => {
        if (!cancelled) {
          setVerdictError(err instanceof Error ? err.message : 'Onderzoeksadvies kon niet worden geladen.')
        }
      })
      .finally(() => {
        if (!cancelled) setVerdictsLoading(false)
      })

    return () => {
      cancelled = true
    }
  }, [usingMock, hbaSlotId, hbaItemKey, hbaThemeItems.length])

  const activeThemeId = activeThemeGroup?.themeId ?? ''
  const activeThemeVerdictKey = activeThemeId || activeSlotId

  useEffect(() => {
    if (usingMock || !activeThemeVerdictKey) {
      setThemeFit(null)
      setThemeFitLoading(false)
      return
    }

    let cancelled = false
    const applyThemeFit = (res: {
      ok?: boolean
      verdicts?: HbaThemeVerdict[]
      positioningArchetype?: string | null
      code?: string
    }) => {
      if (cancelled) return
      if (res.positioningArchetype !== undefined) {
        setPositioningArchetype(res.positioningArchetype)
      }
      if (res.code === 'architecture_incomplete' || res.code === 'guardrail_inactive') {
        setThemeFit(null)
        return
      }
      const match =
        (res.verdicts ?? []).find((row) => row.themeId === activeThemeVerdictKey)
        ?? (res.verdicts ?? []).find((row) => row.themeId === activeThemeId)
        ?? res.verdicts?.[0]
        ?? null
      if (match) {
        setThemeFit(match)
      }
    }

    setThemeFitLoading(true)
    void fetchThemeVerdicts(undefined, activeThemeId || undefined, hbaSlotId || undefined)
      .then(applyThemeFit)
      .catch(() => {})

    void runThemeVerdicts(undefined, activeThemeId || undefined, hbaSlotId || undefined)
      .then(applyThemeFit)
      .catch(() => {})
      .finally(() => {
        if (!cancelled) setThemeFitLoading(false)
      })

    return () => {
      cancelled = true
    }
  }, [usingMock, activeThemeId, activeThemeVerdictKey, hbaSlotId])

  useEffect(() => {
    if ((!pollResearch && !awaitingResearchPoll) || !slotId || usingMock) {
      if (!pollResearch && !awaitingResearchPoll) {
        setPollingMore(false)
      }
      return
    }

    setPollingMore(true)
    let attempts = 0
    const interval = window.setInterval(() => {
      attempts += 1
      if (attempts > 24) {
        setPollingMore(false)
        setAwaitingResearchPoll(false)
        if (visibleItemsCountRef.current === 0) {
          setError(
            'Onderzoek leverde nog geen bruikbare bronnen op. Klik opnieuw op Onderzoek starten of voeg bronnen toe onder Bronnen.',
          )
        }
        window.clearInterval(interval)
        return
      }
      void load(slotId)
    }, 5000)

    return () => {
      window.clearInterval(interval)
      setPollingMore(false)
    }
  }, [pollResearch, awaitingResearchPoll, slotId, usingMock, load])

  useEffect(() => {
    if (visibleItems.length > 0) {
      setAwaitingResearchPoll(false)
    }
    if (visibleItems.length >= MAX_ITEMS * Math.max(themeGroups.length, 1)) {
      setPollingMore(false)
    }
  }, [visibleItems.length, themeGroups.length])

  const fetchingSources =
    triggering ||
    ((pollResearch || awaitingResearchPoll) && visibleItems.length === 0 && !loading)
  const busy = submitting || fetchingSources
  const researchInProgress =
    loading || triggering || pollingMore || fetchingSources || (expectsArticleWrite && visibleItems.length === 0)
  const showStartResearch =
    !queueMode &&
    visibleItems.length === 0 &&
    !researchInProgress &&
    Boolean(slotId) &&
    isApiConfigured() &&
    !usingMock

  useEffect(() => {
    if (suppressPipelineSideEffectsRef.current) return
    if (!slotId || usingMock || !isApiConfigured() || loading || busy || queueMode) return
    if (!expectsArticleWrite) return
    if (autoResearchSlotRef.current === slotId) return
    if (pollResearch) {
      autoResearchSlotRef.current = slotId
      return
    }
    if (visibleItems.length > 0) {
      autoResearchSlotRef.current = slotId
      return
    }

    autoResearchSlotRef.current = slotId

    void (async () => {
      setTriggering(true)
      setError(null)
      try {
        const data = await triggerSlotResearch(slotId)
        applyResearchData(data)
        if ((data.items?.length ?? 0) === 0) {
          setAwaitingResearchPoll(true)
        }
      } catch (err) {
        autoResearchSlotRef.current = null
        setError(err instanceof Error ? err.message : 'Onderzoek starten mislukt')
      } finally {
        setTriggering(false)
      }
    })()
  }, [
    slotId,
    usingMock,
    loading,
    busy,
    expectsArticleWrite,
    pollResearch,
    visibleItems.length,
    applyResearchData,
    queueMode,
  ])

  async function refreshPendingQueue(preferredThemeIndex = activeThemeIndex) {
    const hadOpenItems = reviewQueue.length > 0
    const pending = await fetchPendingResearch()
    if (hadOpenItems && pending.items.length === 0) {
      suppressPipelineSideEffectsRef.current = true
      setPendingClearedByReview(true)
      setExpectsArticleWrite(false)
      autoResearchSlotRef.current = slotId || null
    } else if (pending.items.length > 0) {
      setPendingClearedByReview(false)
    }
    setReviewQueue(pending.items)
    const groups = groupPendingItemsByTheme(pending.items)
    setThemeGroups(groups)
    setActiveThemeIndex(
      groups.length === 0
        ? 0
        : Math.min(Math.max(0, preferredThemeIndex), groups.length - 1),
    )
    setDraftFeedback((prev) => {
      const fromServer = draftFromItems(pending.items)
      const merged = { ...fromServer }
      for (const item of pending.items) {
        const local = prev[item.id]
        if (local !== undefined && local !== normalizeResearchFeedback(item.feedback)) {
          merged[item.id] = local
        }
      }
      return merged
    })
    return pending.items
  }

  /**
   * Like/Forget is altijd puur lokaal (draft) — geen opslaan of verversen van de lijst.
   * Research-items blijven zichtbaar staan; alleen Submit persisteert en verwerkt de keuzes.
   */
  function handleFeedbackChange(itemId: string, feedback: 'liked' | 'forgotten') {
    setDraftFeedback((prev) => ({ ...prev, [itemId]: feedback }))
  }

  async function persistDraft() {
    if (usingMock) {
      setThemeGroups((prev) =>
        prev.map((group) => ({
          ...group,
          items: group.items.map((item) => ({
            ...item,
            feedback: draftFeedback[item.id] ?? item.feedback,
          })),
        })),
      )
      setReviewQueue((prev) =>
        prev.map((item) => ({
          ...item,
          feedback: draftFeedback[item.id] ?? item.feedback,
        })),
      )
      return
    }

    const pending = dirtyItems(visibleItems, draftFeedback)
    if (pending.length === 0) return

    const result = await saveResearchFeedback(pending)
    if (result.failedCount > 0) {
      throw new Error(`${result.failedCount} onderzoeksitem(s) opslaan mislukt`)
    }
    setThemeGroups((prev) =>
      prev.map((group) => ({
        ...group,
        items: group.items.map((item) => {
          const updated = result.items.find((row) => row.id === item.id)
          return updated ?? item
        }),
      })),
    )
    if (queueMode) {
      await refreshPendingQueue(activeThemeIndex)
    }
    setDraftFeedback((prev) => {
      const next = { ...prev }
      for (const row of result.items) {
        next[row.id] = normalizeResearchFeedback(row.feedback)
      }
      return next
    })
  }

  async function handleSubmit() {
    if (submittingRef.current) return
    const canSubmit =
      queueMode ||
      Boolean(slotId) ||
      pendingClearedByReview ||
      reviewedSlotIdsRef.current.size > 0
    if (!canSubmit) return
    suppressPipelineSideEffectsRef.current = false
    setPendingClearedByReview(false)
    submittingRef.current = true
    setSubmitting(true)
    setError(null)
    clearSubmitNotice()
    let feedbackWarning: string | null = null
    try {
      try {
        await persistDraft()
      } catch (err) {
        feedbackWarning =
          err instanceof Error ? err.message : 'Terugkoppeling opslaan mislukt — indienen gaat door.'
      }

      if (!usingMock) {
        const slotIds = [
          ...new Set([
            ...themeGroups.map((group) => group.slotId).filter(Boolean),
            ...reviewQueue.map((item) => item.slotId).filter(Boolean),
            ...reviewedSlotIdsRef.current,
            ...(slotId ? [slotId] : []),
          ]),
        ]
        let writeSlotId: string | null = null
        let writeWithArticleDraft = true
        let insufficientMessage: string | null = null

        for (const targetSlotId of slotIds) {
          const result = await submitSlotResearch(targetSlotId)
          if (result.reason === 'insufficient_research' && result.message) {
            insufficientMessage = result.message
            continue
          }
          if (result.articleWriteTriggered === true) {
            writeSlotId = targetSlotId
            writeWithArticleDraft = true
          } else if (result.expectsArticleWrite === true && result.reason === 'article_exists') {
            writeSlotId = targetSlotId
            writeWithArticleDraft = false
          }
        }

        if (insufficientMessage && !writeSlotId) {
          setError(insufficientMessage)
          bumpAgendaRefresh()
          reviewedSlotIdsRef.current = new Set()
          return
        }

        if (queueMode) {
          await refreshPendingQueue(0)
        }

        if (writeSlotId) {
          if (feedbackWarning) setError(feedbackWarning)
          reviewedSlotIdsRef.current = new Set()
          bumpAgendaRefresh()
          const submitMessage = writeWithArticleDraft
            ? 'Onderzoek ingediend — artikel wordt geschreven.'
            : 'Onderzoek ingediend — je artikel staat klaar.'
          showSubmitSuccess(submitMessage)
          scheduleNavigateAfterNotice(() =>
            navigate(
              writeWithArticleDraft ? `/article/${writeSlotId}?writing=1` : `/article/${writeSlotId}`,
              {
                state: {
                  researchSubmitted: true,
                  researchSubmitMessage: submitMessage,
                },
              },
            ),
          )
          return
        }
      }

      bumpAgendaRefresh()
      reviewedSlotIdsRef.current = new Set()
      if (feedbackWarning) {
        setError(feedbackWarning)
      }

      if (queueMode) {
        showSubmitSuccess('Onderzoek ingediend — kies het volgende voorstel of ga naar de agenda.')
        return
      }

      showSubmitSuccess('Onderzoek ingediend — agenda bijgewerkt.')
      scheduleNavigateAfterNotice(() =>
        navigate('/agenda', {
          state: {
            researchSubmitted: true,
            researchSubmitMessage: 'Onderzoek ingediend — agenda bijgewerkt.',
          },
        }),
      )
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Indienen mislukt')
    } finally {
      submittingRef.current = false
      setSubmitting(false)
    }
  }

  async function handleTriggerResearch() {
    if (!slotId || usingMock) return
    suppressPipelineSideEffectsRef.current = false
    autoResearchSlotRef.current = null
    setTriggering(true)
    setError(null)
    try {
      const data = await triggerSlotResearch(slotId)
      applyResearchData(data)
      if ((data.items?.length ?? 0) === 0) {
        setAwaitingResearchPoll(true)
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Onderzoek starten mislukt')
    } finally {
      setTriggering(false)
    }
  }

  if (agendaLoading && !routeSlotId && !usingMock && !slotId) {
    return (
      <GridShell
        pageTitle="Onderzoek"
        activeNav="article"
        breadcrumb={
          <NavBreadcrumb items={[...BREADCRUMB_PUBLICATION_ARTICLE, { label: 'Onderzoek' }]} />
        }
        mainLayout="grid2"
        main={<LoadingDots label="Onderzoek laden" className="text-ce-black" />}
      />
    )
  }

  return (
    <GridShell
      pageTitle={
        <span className="block min-w-0" style={{ maxWidth: GRID2_REVIEW_MAIN_WIDTH }}>
          Onderzoek
        </span>
      }
      activeNav="article"
      breadcrumb={
        <NavBreadcrumb items={[...BREADCRUMB_PUBLICATION_ARTICLE, { label: 'Onderzoek' }]} />
      }
      mainLayout="grid2"
      headerContentGap={20}
      main={
        <div style={{ maxWidth: GRID2_REVIEW_MAIN_WIDTH }}>
          {usingMock && (
            <p className="mb-0 text-[14px] leading-[18px] text-ce-beige">
              Mockdata — configureer DB + API voor live onderzoek.
            </p>
          )}
          {weekLabel && (
            <p className="m-0 text-[14px] leading-[18px] font-semibold text-ce-black">
              {queueMode && reviewQueue.length > 0
                ? `${weekLabel} — ${reviewQueue.length} open voorstel${reviewQueue.length === 1 ? '' : 'len'}`
                : weekLabel}
            </p>
          )}
          {lichtOnderbouwdLabel ? (
            <p className="m-0 mt-[6px] text-[14px] leading-[18px] text-ce-beige" role="status">
              {lichtOnderbouwdLabel}
            </p>
          ) : null}
          {note && <p className="mt-[12px] mb-0 text-[14px] leading-[18px] text-ce-black">{note}</p>}
          {error && <p className="mt-[12px] mb-0 text-[14px] leading-[18px] text-ce-red">{error}</p>}
          {imageUploadWarning && (
            <p className="mt-[12px] mb-0 text-[14px] leading-[18px] text-ce-orange">{imageUploadWarning}</p>
          )}
          {pollingMore && visibleItems.length > 0 && visibleItems.length < MAX_ITEMS && (
            <p className="mb-[12px] text-[14px] leading-[18px] text-ce-black">
              Meer bronnen worden opgehaald…
            </p>
          )}
          {loading && <LoadingDots label="Onderzoek laden" className="text-ce-black" />}
          {fetchingSources && <LoadingDots label="Bronnen ophalen" className="text-ce-black" />}

          {!showStartResearch && !researchInProgress && visibleItems.length === 0 && !queueMode && (
            <div className="pt-[20px]">
              <p className="m-0 text-[14px] leading-[18px] text-ce-beige">
                {pendingClearedByReview
                  ? 'Geen open onderzoeksvoorstellen meer. Gebruik Indienen om het onderzoek af te ronden, of ga terug naar de agenda.'
                  : `Nog geen onderzoeksvoorstellen voor ${weekLabel || 'deze week'}.`}
              </p>
            </div>
          )}

          {showStartResearch && (
            <div className="pt-[20px]">
              <p className="m-0 text-[14px] leading-[18px] text-ce-beige">
                Nog geen onderzoeksvoorstellen voor {weekLabel || 'deze week'}.
              </p>
              <div className="mt-[16px]">
                <PillButton
                  variant="main"
                  onClick={() => void handleTriggerResearch()}
                  disabled={busy}
                  loading={fetchingSources}
                >
                  Onderzoek starten
                </PillButton>
              </div>
            </div>
          )}

          {showThemeNav && (
            <div className="flex flex-wrap items-baseline gap-x-[12px] gap-y-[4px] text-[14px] leading-[18px]">
              {canPrevTheme ? (
                <ActionLink variant="statusRow" onClick={() => goToThemeIndex(activeThemeIndex - 1)}>
                  ←
                </ActionLink>
              ) : (
                <span className="font-semibold text-ce-beige">←</span>
              )}
              <span className="font-semibold text-ce-black">
                Thema {activeThemeIndex + 1} / {themeGroups.length}
              </span>
              {canNextTheme ? (
                <ActionLink variant="statusRow" onClick={() => goToThemeIndex(activeThemeIndex + 1)}>
                  →
                </ActionLink>
              ) : (
                <span className="font-semibold text-ce-beige">→</span>
              )}
            </div>
          )}

          <div>
            {activeThemeGroup ? (
              <section key={activeThemeGroup.slotId || activeThemeGroup.themeId} className={LIST_SECTION.sectionClass}>
                <h2 className={`${LIST_SECTION.headerClass} text-ce-black`}>
                  {activeThemeGroup.themeTitle || 'Thema'}
                </h2>
                {activeThemeGroup.themeRationale?.trim() ? (
                  <p className="m-0 pt-[8px] text-[14px] leading-[18px] text-ce-black">
                    {activeThemeGroup.themeRationale.trim()}
                  </p>
                ) : null}
                {guardrailNotice ? (
                  <p className="m-0 mt-[12px] text-[14px] leading-[18px] text-ce-black">{guardrailNotice}</p>
                ) : null}
                {positioningArchetype && !guardrailNotice ? (
                  <p className="m-0 mt-[12px] text-[14px] leading-[18px] text-ce-beige">
                    Past dit onderwerp bij je merk?
                  </p>
                ) : null}
                {positioningArchetype && !guardrailNotice ? (
                  <ThemeFitAdvice
                    label={themeFit?.label ?? null}
                    toelichting={themeVerdictToelichtingForDisplay(
                      themeFit?.toelichting,
                      positioningArchetype,
                    )}
                    loading={themeFitLoading && !themeFit}
                  />
                ) : null}
                {verdictError ? (
                  <p className="m-0 mt-[12px] text-[14px] leading-[18px] text-ce-red">{verdictError}</p>
                ) : null}
                {verdictsLoading && !watOntbreekt ? (
                  <div className="mt-[18px] mb-[18px]">
                    <LoadingDots label="Positionering beoordelen…" className="text-ce-black" />
                  </div>
                ) : null}
                {watOntbreekt ? (
                  <div className={`${pulldownBoxClass} ${frameLineGapBeforeClass} mb-[18px] px-[30px] py-[16px]`}>
                    <p className="m-0 mb-[12px] text-[14px] font-semibold leading-[18px] text-ce-black">
                      Advies voor je onderzoek
                    </p>
                    <p className="m-0 text-[14px] font-normal leading-[18px] text-ce-black">{watOntbreekt}</p>
                  </div>
                ) : null}
                {activeThemeItems.length === 0 ? (
                  <p className="m-0 pt-[12px] text-[14px] leading-[18px] text-ce-beige">
                    Nog geen onderzoek voor dit thema.
                  </p>
                ) : (
                  activeThemeItems.map((item, itemIndex) => {
                    const verdict = verdicts[item.id]
                    return (
                    <ResearchReviewRow
                      key={item.id}
                      itemId={item.id}
                      title={item.title}
                      themeTitle={activeThemeGroup.themeTitle}
                      previousTitle={itemIndex > 0 ? activeThemeItems[itemIndex - 1]?.title : null}
                      snippet={item.snippet || ''}
                      articleUrl={resolveResearchArticleUrl(item.url)}
                      invalshoek={item.invalshoek}
                      waarom={item.itemRationale || undefined}
                      getoetstAan={
                        themeVerdictToelichtingForDisplay(
                          verdict?.toelichting,
                          positioningArchetype,
                        ) || undefined
                      }
                      rationaleIndex={itemIndex + 1}
                      feedback={draftFeedback[item.id] ?? normalizeResearchFeedback(item.feedback)}
                      busy={busy}
                      verdictLabel={verdict?.label ?? null}
                      onFeedbackChange={(feedback) => handleFeedbackChange(item.id, feedback)}
                    />
                    )
                  })
                )}
              </section>
            ) : null}
          </div>

          {activeThemeItems.some((item) => verdicts[item.id]) ? (
            <p className="m-0 pt-[20px] text-[14px] leading-[18px] text-ce-beige">
              Je kiest zelf welke bronnen je meeneemt — dit is advies, geen verplichting. Alleen
              indienen zet het artikel door naar schrijven.
            </p>
          ) : null}

          {visibleItems.length > 0 || pendingClearedByReview ? (
            <div className="mt-[18px]">
              <PillButton variant="main" onClick={() => void handleSubmit()} disabled={busy} loading={submitting}>
                Indienen
              </PillButton>
              <ActionNotice
                message={submitNotice}
                kind={submitNoticeKind}
                fading={submitNoticeFading}
                className="mb-0"
              />
            </div>
          ) : null}
        </div>
      }
    />
  )
}

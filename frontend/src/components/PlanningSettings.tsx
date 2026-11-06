import { useCallback, useEffect, useMemo, useState } from 'react'
import { GRID2_REVIEW_MAIN_WIDTH } from '../layout/grid.constants'
import { PillButton, pillActionsRowClass, designStackClass } from './PillButton'
import { SaveNotice } from './ui/SaveNotice'
import { useSaveNotice } from '../hooks/useSaveNotice'
import { PlanningMetricField } from './PlanningMetricField'
import { fetchAccount, patchAccount } from '../api/account'
import { isApiConfigured } from '../api/client'
import { fetchContentRhythm, saveContentRhythm, type ContentRhythm } from '../api/contentRhythm'
import { rhythmRoleKlantLabel } from '../utils/rhythmRoles'

/** Horizon, posts/week, checks — Planning-pagina. */
export function PlanningSettings() {
  const [planningHorizonWeeks, setPlanningHorizonWeeks] = useState('4')
  const [postsPerWeek, setPostsPerWeek] = useState('2')
  const [sourceChecksPerWeek, setSourceChecksPerWeek] = useState('1')
  const [savedPlanningHorizonWeeks, setSavedPlanningHorizonWeeks] = useState('4')
  const [savedPostsPerWeek, setSavedPostsPerWeek] = useState('2')
  const [savedSourceChecksPerWeek, setSavedSourceChecksPerWeek] = useState('1')
  const [rhythm, setRhythm] = useState<ContentRhythm | null>(null)
  const [loading, setLoading] = useState(true)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [note, setNote] = useState<string | null>(null)
  const {
    message: saveMessage,
    kind: saveNoticeKind,
    fading: saveNoticeFading,
    showSaving: showSaveSaving,
    showSuccess: showSaveSuccess,
    showError: showSaveError,
    clear: clearSaveNotice,
  } = useSaveNotice('Planning opgeslagen')

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    if (!isApiConfigured()) {
      setNote('API niet geconfigureerd')
      setLoading(false)
      return
    }
    try {
      const accountData = await fetchAccount()
      const account = accountData.account
      if (account) {
        const horizon = String(account.planningHorizonWeeks ?? 4)
        const posts = String(account.postsPerWeek ?? 2)
        const checks = String(account.sourceChecksPerWeek ?? 1)
        setPlanningHorizonWeeks(horizon)
        setPostsPerWeek(posts)
        setSourceChecksPerWeek(checks)
        setSavedPlanningHorizonWeeks(horizon)
        setSavedPostsPerWeek(posts)
        setSavedSourceChecksPerWeek(checks)
      }
      try {
        const rhythmData = await fetchContentRhythm()
        setRhythm(rhythmData.rhythm)
      } catch {
        setRhythm(null)
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Laden mislukt')
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    void load()
  }, [load])

  const dirty = useMemo(
    () =>
      planningHorizonWeeks !== savedPlanningHorizonWeeks ||
      postsPerWeek !== savedPostsPerWeek ||
      sourceChecksPerWeek !== savedSourceChecksPerWeek,
    [
      planningHorizonWeeks,
      postsPerWeek,
      sourceChecksPerWeek,
      savedPlanningHorizonWeeks,
      savedPostsPerWeek,
      savedSourceChecksPerWeek,
    ],
  )

  useEffect(() => {
    if (dirty) clearSaveNotice()
  }, [dirty, clearSaveNotice])

  function undo() {
    setPlanningHorizonWeeks(savedPlanningHorizonWeeks)
    setPostsPerWeek(savedPostsPerWeek)
    setSourceChecksPerWeek(savedSourceChecksPerWeek)
  }

  async function save() {
    setSaving(true)
    setError(null)
    showSaveSaving()
    try {
      const posts = Math.max(1, parseInt(postsPerWeek, 10) || 1)
      await patchAccount({
        planningHorizonWeeks: Math.max(1, parseInt(planningHorizonWeeks, 10) || 1),
        postsPerWeek: posts,
        sourceChecksPerWeek: Math.max(1, parseInt(sourceChecksPerWeek, 10) || 1),
      })
      if (rhythm) {
        const next = await saveContentRhythm({
          postsPerWeek: posts,
          roles: rhythm.roles,
        })
        setRhythm(next.rhythm)
      }
      setSavedPlanningHorizonWeeks(planningHorizonWeeks)
      setSavedPostsPerWeek(postsPerWeek)
      setSavedSourceChecksPerWeek(sourceChecksPerWeek)
      showSaveSuccess()
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Opslaan mislukt'
      setError(message)
      showSaveError(message)
    } finally {
      setSaving(false)
    }
  }

  if (loading) {
    return <p className="m-0 text-[14px] leading-[18px] text-ce-beige">Planning laden…</p>
  }

  return (
    <div className={designStackClass} style={{ maxWidth: GRID2_REVIEW_MAIN_WIDTH }}>
      {note && <p className="m-0 text-[14px] leading-[18px] text-ce-beige">{note}</p>}
      {error && <p className="m-0 text-[14px] leading-[18px] text-ce-red">{error}</p>}

      <div className="grid grid-cols-3 items-center gap-x-[12px]">
        <PlanningMetricField
          id="planning-horizon"
          labelLines={['Planhorizon', '(weken)']}
          placeholder="weken"
          value={planningHorizonWeeks}
          onChange={setPlanningHorizonWeeks}
        />
        <PlanningMetricField
          id="planning-posts-per-week"
          labelLines={['Posts', 'per week']}
          placeholder="Aantal"
          value={postsPerWeek}
          onChange={setPostsPerWeek}
        />
        <PlanningMetricField
          id="planning-source-checks-per-week"
          labelLines={['Broncontroles', 'per week']}
          placeholder="Aantal"
          value={sourceChecksPerWeek}
          onChange={setSourceChecksPerWeek}
        />
      </div>

      {rhythm ? (
        <div>
          <p className="m-0 text-[14px] font-semibold leading-[18px] text-ce-black">
            {rhythm.proposed ? 'Voorgesteld ritme' : 'Jullie ritme'}
          </p>
          <p className="m-0 mt-[8px] text-[14px] leading-[18px] text-ce-black">
            Elke week dezelfde rollen:{' '}
            {rhythm.roles.map((role) => rhythmRoleKlantLabel(role.role_name)).join(', ')}.
            Posts per week hierboven geldt voor Overzicht, Thema&apos;s en Agenda.
            Wijzigen is een bewuste actie, geen wekelijkse taak.
          </p>
        </div>
      ) : null}

      <div>
        <div className={pillActionsRowClass}>
          <PillButton variant="main" onClick={() => void save()} disabled={saving || !dirty} loading={saving}>
            Opslaan
          </PillButton>
          <PillButton variant="second" onClick={undo} disabled={saving || !dirty}>
            Ongedaan maken
          </PillButton>
        </div>
        <SaveNotice message={saveMessage} kind={saveNoticeKind} fading={saveNoticeFading} />
      </div>
    </div>
  )
}

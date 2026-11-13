import { useEffect, useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { FieldSingle } from '../components/ui/Field'
import { Pulldown } from '../components/ui/Pulldown'
import { PillButton } from '../components/PillButton'
import { ActionLink } from '../components/ui/ActionLink'
import { LIST_SECTION } from '../layout/grid.constants'
import { ChannelFilterBar } from '../components/ChannelFilterBar'
import { AGENDA_CHANNEL_ALL, matchesChannelKey, periodChannelOptions } from '../utils/agendaFilters'
import { designStackClass, pillActionsRowInnerClass } from '../design/identity.constants'
import {
  CAMPAGNE_MERK_ONDERDELEN,
  CAMPAGNE_PLAN_CHECK_SOORTEN,
  type CampagnePlanCheck,
  type CampagnePlanCheckSignaal,
  type CampagnePlanDecision,
  type CampagnePlanDecisions,
  type CampagnePreviewSlot,
  type CampagneProductExtract,
} from '../api/campagnePreview'

const bodyClass = 'm-0 text-[14px] leading-[18px] text-ce-black'
const hintClass = 'm-0 text-[14px] leading-[18px] text-ce-beige'
const sectionTitleClass = 'm-0 text-[18px] leading-[21px] font-semibold text-ce-blue'

const GAP_SOORTEN = new Set(['doelpagina', 'ankers_te_weinig', 'dekking'])
const STRUCTURAL_SOORTEN = new Set(['balans', 'dekking'])

const MERK_ONDERDEEL_LABELS: Record<string, string> = {
  wat_je_doet: 'Wat je doet',
  waarom: 'Waarom je bestaat',
  hoe_je_werkt: 'Hoe je werkt',
  waar_je_nee_tegen_zegt: 'Waar je nee tegen zegt',
  waar_je_tegen_bent: 'Waar je tegen bent',
}

type PlanChoiceBody = {
  recalculate?: boolean
  slotMerkOnderdeel?: { slotId: string; merkOnderdeel: string | null }
  slotInstructie?: { slotIds: string[]; text: string }
  planDecision?: {
    target: 'advies' | 'signaal'
    soort?: string
    choice: CampagnePlanDecision['choice']
    text?: string
    slotIds?: string[]
  }
}

function formatSlotDate(value: string | null): string {
  if (!value) return ''
  const d = new Date(`${value}T12:00:00`)
  if (Number.isNaN(d.getTime())) return value
  return d.toLocaleDateString('nl-NL', { weekday: 'short', day: 'numeric', month: 'short' })
}

function merkOnderdeelLabel(key: string | null): string | null {
  if (!key) return null
  return MERK_ONDERDEEL_LABELS[key] ?? key.replaceAll('_', ' ')
}

function decisionText(choice: string | undefined): string | null {
  if (choice === 'volgen') return 'Volgen vastgelegd.'
  if (choice === 'niet_volgen') return 'Niet volgen vastgelegd.'
  if (choice === 'zelf') return 'Eigen aanpassing vastgelegd.'
  if (choice === 'akkoord') return 'Akkoord vastgelegd.'
  if (choice === 'aanpassen') return 'Aanpassing vastgelegd.'
  if (choice === 'negeren') return 'Genegeerd.'
  return null
}

function signalStatus(signal: CampagnePlanCheckSignaal | null, checking: boolean): string {
  if (checking || !signal) return 'wacht'
  return signal.status ?? (signal.signaal ? 'aandachtspunt' : 'ok')
}

export type PlanReviewSectionKey = 'gaps' | 'campaign' | 'advies' | 'check'

function SectionHeader({
  title,
  showEdit,
  onEdit,
}: {
  title: string
  showEdit: boolean
  onEdit?: () => void
}) {
  return (
    <div className="flex flex-wrap items-baseline justify-between gap-[12px]">
      <p className={`${sectionTitleClass} m-0`}>{title}</p>
      {showEdit && onEdit ? (
        <PillButton variant="second" className="!mb-0" onClick={onEdit}>
          Edit
        </PillButton>
      ) : null}
    </div>
  )
}

export function PlanReview({
  slots,
  planCheck,
  productExtract,
  extractError,
  progressLabel,
  decisions,
  canEdit,
  planReady,
  onEditSection,
  onPlace,
  onMerkOnderdeel,
  onChoice,
}: {
  slots: CampagnePreviewSlot[]
  planCheck: CampagnePlanCheck | null
  productExtract: CampagneProductExtract | null
  extractError: string | null
  progressLabel: string | null
  decisions: CampagnePlanDecisions
  canEdit: boolean
  planReady: boolean
  onEditSection?: (section: PlanReviewSectionKey) => void
  onPlace: (slotId: string, publishDate: string, publishTime: string | null, platform: string) => void
  onMerkOnderdeel: (slotId: string, merkOnderdeel: string | null) => void
  onChoice: (body: PlanChoiceBody, recheck: boolean) => void
}) {
  const [planChannel, setPlanChannel] = useState(AGENDA_CHANNEL_ALL)
  const visibleSlots = useMemo(
    () =>
      slots.filter((slot) =>
        matchesChannelKey(planChannel, slot.platform, slot.platformKey),
      ),
    [slots, planChannel],
  )

  if (!planReady) {
    return null
  }
  const checking = progressLabel != null
  const bySoort = new Map((planCheck?.signalen ?? []).map((s) => [s.soort, s]))
  const rows = CAMPAGNE_PLAN_CHECK_SOORTEN.map(({ soort, label }) => ({
    soort,
    label,
    signal: bySoort.get(soort) ?? null,
  }))
  const structuralOpen = rows.some(
    (row) => STRUCTURAL_SOORTEN.has(row.soort) && row.signal?.signaal === true && !checking,
  )
  const inOrde = checking
    ? []
    : rows.filter((row) => signalStatus(row.signal, false) === 'ok').map((row) => row.label)
  const gaps = rows.filter((row) => GAP_SOORTEN.has(row.soort) && row.signal?.signaal === true && !checking)
  const onderdeelOptions = ['(fase)', ...CAMPAGNE_MERK_ONDERDELEN.map((k) => merkOnderdeelLabel(k) ?? k)]
  const adviesNote = decisionText(decisions.advies?.choice)
  const sectionEdit = canEdit && !checking

  return (
    <div className={designStackClass}>
      {progressLabel ? <p className={sectionTitleClass}>{progressLabel}</p> : null}
      {extractError && <p className="m-0 text-[14px] leading-[18px] text-ce-red">{extractError}</p>}
      {productExtract?.ontbreekt ? (
        <p className={bodyClass}>Op de productpagina ontbreekt: {productExtract.ontbreekt}</p>
      ) : null}

      <SectionHeader
        title="Wat er is en wat ontbreekt"
        showEdit={sectionEdit}
        onEdit={onEditSection ? () => onEditSection('gaps') : undefined}
      />
      {slots.length === 0 ? (
        <p className={hintClass}>Nog geen plan. Kies Maak plan — daarna zie je wat er is en wat ontbreekt.</p>
      ) : (
        <>
          {inOrde.length > 0 ? <p className={bodyClass}>In orde: {inOrde.join(', ')}.</p> : null}
          {gaps.length === 0 && inOrde.length === 0 ? (
            <p className={hintClass}>{checking ? 'De check loopt.' : 'Nog geen check.'}</p>
          ) : null}
          {gaps.map((row) => (
            <div key={row.soort}>
              <p className="m-0 text-[14px] leading-[18px] font-semibold text-ce-black">{row.label}</p>
              <p className={`${bodyClass} mt-[6px]`}>{row.signal?.tekst}</p>
              {decisionText(decisions.signalen?.[row.soort]?.choice) ? (
                <p className={`${hintClass} mt-[6px]`}>{decisionText(decisions.signalen?.[row.soort]?.choice)}</p>
              ) : null}
              {canEdit ? (
                <InstructForm
                  slots={slots}
                  onSave={(text, slotIds) =>
                    onChoice(
                      {
                        slotInstructie: { slotIds, text },
                        planDecision: {
                          target: 'signaal',
                          soort: row.soort,
                          choice: 'aanpassen',
                          text,
                          slotIds,
                        },
                      },
                      true,
                    )
                  }
                />
              ) : null}
            </div>
          ))}
        </>
      )}

      <SectionHeader
        title="De campagne"
        showEdit={sectionEdit}
        onEdit={onEditSection ? () => onEditSection('campaign') : undefined}
      />
      {slots.length === 0 ? null : (
        <>
          <ChannelFilterBar
            channel={planChannel}
            onChannel={setPlanChannel}
            channels={periodChannelOptions()}
            className="mb-[8px]"
          />
          <p className={`${hintClass} mb-[8px]`}>
            Alleen overzicht — filtert wat je ziet; datum en kanaal per thema pas je hieronder nog aan.
          </p>
          {visibleSlots.length === 0 ? (
            <p className={hintClass}>Geen thema&apos;s op dit kanaal in het plan.</p>
          ) : null}
        <ul className={`m-0 list-none p-0 ${LIST_SECTION.bodyStackClass}`}>
          {visibleSlots.map((slot) => {
            const ankerTekst = slot.anker?.tekst || slot.anker?.vraag || slot.anker?.formulering
            const currentLabel = merkOnderdeelLabel(slot.merkOnderdeel ?? null) ?? '(fase)'
            const oordeel = (planCheck?.postOordelen ?? []).find((item) => item.slotId === slot.slotId)
            return (
              <li key={slot.slotId} className={LIST_SECTION.itemClass}>
                <p className={sectionTitleClass}>
                  {formatSlotDate(slot.publishDate)} · {slot.platform ?? slot.platformKey ?? 'kanaal'}
                </p>
                {canEdit && slot.publishDate ? (
                  <SlotSchedule slot={slot} onPlace={onPlace} />
                ) : null}
                <div className="mt-[8px]">
                  <Link
                    to={`/article/${slot.slotId}`}
                    className="text-[14px] leading-[18px] font-semibold text-ce-blue no-underline"
                  >
                    Schrijf dit thema
                  </Link>
                </div>
                <p className={`${bodyClass} mt-[6px]`}>
                  {slot.faseLabel ?? slot.faseKey ?? 'Fase'}
                  {slot.rol ? ` · ${slot.rol === 'merk' ? 'Merk' : 'Activatie'}` : ''}
                </p>
                {canEdit ? (
                  <div className="mt-[8px]">
                    <p className="mb-[6px] px-[30px] text-[14px] leading-[18px] font-semibold text-ce-black">
                      Merkonderdeel
                    </p>
                    <Pulldown
                      options={onderdeelOptions}
                      value={currentLabel}
                      onChange={(label) => {
                        const key = CAMPAGNE_MERK_ONDERDELEN.find((k) => merkOnderdeelLabel(k) === label) ?? null
                        onMerkOnderdeel(slot.slotId, key)
                      }}
                    />
                  </div>
                ) : currentLabel !== '(fase)' ? (
                  <p className={`${bodyClass} mt-[6px]`}>{currentLabel}</p>
                ) : null}
                {ankerTekst ? <p className={`${hintClass} mt-[6px]`}>Anker: {ankerTekst}</p> : null}
                {slot.channelHints && slot.channelHints.length > 0
                  ? slot.channelHints.map((hint) => (
                      <p key={hint} className={`${hintClass} mt-[6px]`}>
                        {hint}
                      </p>
                    ))
                  : null}
                {slot.opdracht ? <p className={`${bodyClass} mt-[6px]`}>{slot.opdracht}</p> : null}
                {slot.instructie ? (
                  <p className={`${bodyClass} mt-[6px]`}>Aanvullende instructie: {slot.instructie}</p>
                ) : null}
                {oordeel && !checking ? (
                  <p className={`${bodyClass} mt-[6px]`}>
                    {oordeel.label}
                    {oordeel.toelichting ? ` — ${oordeel.toelichting}` : ''}
                  </p>
                ) : null}
              </li>
            )
          })}
        </ul>
        </>
      )}

      <SectionHeader
        title="Advies"
        showEdit={sectionEdit}
        onEdit={onEditSection ? () => onEditSection('advies') : undefined}
      />
      {planCheck?.advies && !checking ? <p className={bodyClass}>{planCheck.advies}</p> : null}
      {planCheck?.llmOk === false && planCheck.llmError && !checking ? (
        <p className={hintClass}>De merk-check op het plan is deels gedaan zonder taalmodel.</p>
      ) : null}
      {adviesNote ? <p className={hintClass}>{adviesNote}</p> : null}
      {canEdit && slots.length > 0 && !checking ? (
        <AdviesActions
          advies={planCheck?.advies ?? ''}
          structural={structuralOpen}
          slots={slots}
          onChoice={onChoice}
        />
      ) : null}

      <SectionHeader
        title="Check vooraf"
        showEdit={sectionEdit}
        onEdit={onEditSection ? () => onEditSection('check') : undefined}
      />
      <ul className={`m-0 list-none p-0 ${LIST_SECTION.bodyStackClass}`}>
        {rows.map((row) => {
          const status = signalStatus(row.signal, checking)
          const note = decisionText(decisions.signalen?.[row.soort]?.choice)
          return (
            <li key={row.soort} className={LIST_SECTION.itemClass}>
              <p className="m-0 text-[14px] leading-[18px] font-semibold text-ce-black">
                {row.label}
                {status === 'wacht' ? '' : ` · ${status === 'ok' ? 'ok' : 'aandachtspunt'}`}
              </p>
              <p className={`mt-[6px] mb-0 text-[14px] leading-[18px] ${row.signal?.signaal && !checking ? 'text-ce-red' : 'text-ce-black'}`}>
                {row.signal?.tekst ?? 'Nog geen check. Kies Maak plan.'}
              </p>
              {row.signal?.voorstel && !checking ? (
                <p className={`${bodyClass} mt-[6px]`}>{row.signal.voorstel}</p>
              ) : null}
              {note ? <p className={`${hintClass} mt-[6px]`}>{note}</p> : null}
              {canEdit && !checking && row.signal ? (
                <SignalActions soort={row.soort} slots={slots} onChoice={onChoice} />
              ) : null}
            </li>
          )
        })}
      </ul>
    </div>
  )
}

function AdviesActions({
  advies,
  structural,
  slots,
  onChoice,
}: {
  advies: string
  structural: boolean
  slots: CampagnePreviewSlot[]
  onChoice: (body: PlanChoiceBody, recheck: boolean) => void
}) {
  const [mode, setMode] = useState<'volgen' | 'zelf' | null>(null)
  return (
    <div className="flex flex-col gap-[8px]">
      <div className="flex flex-wrap gap-[12px]">
        {structural || advies.trim() !== '' ? (
          <ActionLink
            variant="statusRow"
            onClick={() => {
              if (structural) {
                onChoice({ recalculate: true, planDecision: { target: 'advies', choice: 'volgen' } }, true)
                return
              }
              setMode('volgen')
            }}
          >
            Volgen
          </ActionLink>
        ) : null}
        <ActionLink
          variant="statusRow"
          onClick={() => onChoice({ planDecision: { target: 'advies', choice: 'niet_volgen' } }, false)}
        >
          Niet volgen
        </ActionLink>
        <ActionLink variant="statusRow" onClick={() => setMode('zelf')}>
          Zelf aanpassen
        </ActionLink>
      </div>
      {structural ? (
        <p className={hintClass}>Volgen herberekent het hele plan.</p>
      ) : null}
      {mode === 'volgen' && !structural ? (
        <InstructForm
          slots={slots}
          onSave={(text, slotIds) =>
            onChoice(
              {
                slotInstructie: { slotIds, text: text || advies },
                planDecision: { target: 'advies', choice: 'volgen', text: text || advies, slotIds },
              },
              true,
            )
          }
        />
      ) : null}
      {mode === 'zelf' ? (
        <InstructForm
          slots={slots}
          onSave={(text, slotIds) =>
            onChoice(
              {
                slotInstructie: { slotIds, text },
                planDecision: { target: 'advies', choice: 'zelf', text, slotIds },
              },
              true,
            )
          }
        />
      ) : null}
    </div>
  )
}

function SignalActions({
  soort,
  slots,
  onChoice,
}: {
  soort: string
  slots: CampagnePreviewSlot[]
  onChoice: (body: PlanChoiceBody, recheck: boolean) => void
}) {
  const [open, setOpen] = useState(false)
  return (
    <div className="mt-[8px] flex flex-col gap-[8px]">
      <div className={pillActionsRowInnerClass}>
        <PillButton
          variant="second"
          className="!mb-0"
          onClick={() => onChoice({ planDecision: { target: 'signaal', soort, choice: 'akkoord' } }, false)}
        >
          Akkoord
        </PillButton>
        <PillButton variant="second" className="!mb-0" onClick={() => setOpen(true)}>
          Aanpassen
        </PillButton>
        <PillButton
          variant="second"
          className="!mb-0"
          onClick={() => onChoice({ planDecision: { target: 'signaal', soort, choice: 'negeren' } }, false)}
        >
          Negeren
        </PillButton>
      </div>
      {open ? (
        <InstructForm
          slots={slots}
          onSave={(text, slotIds) =>
            onChoice(
              {
                slotInstructie: { slotIds, text },
                planDecision: { target: 'signaal', soort, choice: 'aanpassen', text, slotIds },
              },
              true,
            )
          }
        />
      ) : null}
    </div>
  )
}

function InstructForm({
  slots,
  onSave,
}: {
  slots: CampagnePreviewSlot[]
  onSave: (text: string, slotIds: string[]) => void
}) {
  const [text, setText] = useState('')
  const [selected, setSelected] = useState<string[]>([])
  return (
    <div className="mt-[8px] flex flex-col gap-[8px]">
      <FieldSingle value={text} onChange={setText} placeholder="Aanvullende instructie" placeholderTone="black" />
      <p className={hintClass}>Kies zelf de thema's waar deze instructie naartoe gaat.</p>
      <ul className={`m-0 list-none p-0 ${LIST_SECTION.bodyStackClass}`}>
        {slots.map((slot) => {
          const on = selected.includes(slot.slotId)
          return (
            <li key={slot.slotId}>
              <ActionLink
                variant="statusRow"
                onClick={() =>
                  setSelected((prev) => (prev.includes(slot.slotId) ? prev.filter((id) => id !== slot.slotId) : [...prev, slot.slotId]))
                }
              >
                {on ? 'Gekozen' : 'Kies'} · {formatSlotDate(slot.publishDate)} · {slot.platform ?? slot.platformKey ?? 'kanaal'}
              </ActionLink>
            </li>
          )
        })}
      </ul>
      <div>
        <PillButton variant="second" disabled={text.trim() === '' || selected.length === 0} onClick={() => onSave(text.trim(), selected)}>
          Opslaan
        </PillButton>
      </div>
    </div>
  )
}

function SlotSchedule({
  slot,
  onPlace,
}: {
  slot: CampagnePreviewSlot
  onPlace: (slotId: string, publishDate: string, publishTime: string | null, platform: string) => void
}) {
  const [date, setDate] = useState(slot.publishDate ?? '')
  useEffect(() => {
    setDate(slot.publishDate ?? '')
  }, [slot.publishDate])
  const platform = (slot.platformKey ?? 'linkedin').toLowerCase()

  return (
    <div className="mt-[8px] flex flex-col gap-[8px]">
      <FieldSingle
        id={`slot-date-${slot.slotId}`}
        type="date"
        label="Datum"
        value={date}
        onChange={setDate}
        onBlur={() => {
          if (date !== '' && date !== (slot.publishDate ?? '')) onPlace(slot.slotId, date, null, platform)
        }}
      />
      <Pulldown
        options={['LinkedIn', 'Instagram']}
        value={platform === 'instagram' ? 'Instagram' : 'LinkedIn'}
        onChange={(label) => onPlace(slot.slotId, date || slot.publishDate || '', null, label.toLowerCase())}
      />
    </div>
  )
}

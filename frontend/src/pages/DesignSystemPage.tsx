import { useState } from 'react'
import { GridShell } from '../layout/GridShell'
import { pillLineGapBeforeClass } from '../design/identity.constants'
import { PillButton, pillActionsRowClass } from '../components/PillButton'
import { Pulldown } from '../components/ui/Pulldown'
import { FieldSingle, FieldMulti, FieldStrategy } from '../components/ui/Field'
import { RadioOption } from '../components/ui/RadioOption'
import { PinkChoiceFrame, PinkChoiceOption } from '../components/ui/PinkChoice'
import { MetaSection } from '../components/MetaSection'
import { StatusRow, AgendaRow } from '../components/StatusRow'
import { SourceRow } from '../components/SourceRow'
import { SourceTabs } from '../components/SourceTabs'
import { ActionLink, ActionSep, StatusLabel } from '../components/ui/ActionLink'
import { DeleteConfirmAction } from '../components/ui/DeleteConfirmAction'
import { SignalBlock } from '../components/ui/SignalBlock'
import { articleMock } from '../data/article.mock'

/** Artboard 20Type+.png — alle UI-elementen op één pagina */
export function DesignSystemPage() {
  const [pulldown, setPulldown] = useState('option 1')
  const [radio, setRadio] = useState('b to b')
  const [pinkChoice, setPinkChoice] = useState('artikel')
  const [pinkActions, setPinkActions] = useState<string[]>(['use'])
  const [accOpen, setAccOpen] = useState(true)

  return (
    <GridShell
      pageTitle="Design system"
      activeNav="home"
      main={
        <div className="space-y-[40px]">
          <section>
            <h2 className="m-0 mb-[12px] text-[32px] leading-[40px] font-semibold text-ce-blue">
              General header
            </h2>
            <p className="m-0 text-[14px] leading-[18px]">Corps 14/18 — body copy.</p>
          </section>

          <section>
            <h3 className="m-0 mb-[8px] text-[14px] leading-[18px] font-semibold">Buttons</h3>
            <div className={pillActionsRowClass}>
              <PillButton variant="main">Button main</PillButton>
              <PillButton variant="second">Button second</PillButton>
            </div>
            <div className={pillActionsRowClass}>
              <PillButton variant="main" disabled>
                Button main
              </PillButton>
              <PillButton variant="second" disabled>
                Button second
              </PillButton>
              <p className="m-0 self-center text-[14px] leading-[18px] font-semibold text-ce-beige">
                Button not active
              </p>
            </div>
          </section>

          <section>
            <h3 className="m-0 mb-[8px] text-[14px] leading-[18px] font-semibold">Fields</h3>
            <div className="space-y-[12px]">
              <FieldSingle label="Editable field single line" value="14px" onChange={() => undefined} />
              <FieldSingle placeholder="Source URL" value="" onChange={() => undefined} />
              <FieldSingle placeholder="Title" value="" onChange={() => undefined} />
              <FieldMulti label="Editable field multiple line" value="21/24" onChange={() => undefined} minHeight={120} />
            </div>
          </section>

          <section>
            <h3 className="m-0 mb-[8px] text-[14px] leading-[18px] font-semibold">Strategy (Grid #1)</h3>
            <p className="m-0 mb-[12px] text-[14px] leading-[18px]">General copy — inline label in pink pill.</p>
            <FieldStrategy
              label="Archetype"
              value=""
              onChange={() => undefined}
              minHeight={80}
              placeholder="copy about the archetype"
            />
            <div className={pillActionsRowClass}>
              <PillButton variant="main" disabled>
                Save
              </PillButton>
              <PillButton variant="second" disabled>
                Undo
              </PillButton>
            </div>
          </section>

          <section className="max-w-[320px]">
            <h3 className="m-0 mb-[8px] text-[14px] leading-[18px] font-semibold">Add Sources</h3>
            <SourceTabs onAdd={() => undefined} />
          </section>

          <section className="max-w-[320px]">
            <h3 className="m-0 mb-[8px] text-[14px] leading-[18px] font-semibold">Pulldown</h3>
            <div className={pillLineGapBeforeClass}>
              <Pulldown
                options={['option 1', 'option 2 selected', 'option 3']}
                value={pulldown}
                onChange={setPulldown}
              />
            </div>
          </section>

          <section className="max-w-[240px]">
            <h3 className="m-0 mb-[8px] text-[14px] leading-[18px] font-semibold">Accordion + Radio</h3>
            <MetaSection label="Tone" open={accOpen} onToggle={() => setAccOpen((v) => !v)} selected={radio}>
              <fieldset className="m-0 border-0 p-0">
                {articleMock.tone.options.slice(0, 4).map((opt) => (
                  <RadioOption
                    key={opt}
                    name="demo_tone"
                    value={opt}
                    label={opt}
                    checked={radio === opt}
                    onChange={setRadio}
                  />
                ))}
              </fieldset>
            </MetaSection>
          </section>

          <section className="max-w-[320px]">
            <h3 className="m-0 mb-[8px] text-[14px] leading-[18px] font-semibold">
              Roze keuze-kader (1 kader)
            </h3>
            <p className="m-0 mb-[12px] text-[14px] leading-[18px] text-ce-black">
              Altijd één roze kader — geen kader-in-kader. Copy zwart, radio onderaan, geen inspringing.
            </p>
            <PinkChoiceFrame intro="Kies wat je wilt gebruiken — beide opties kunnen tegelijk aan.">
              <PinkChoiceOption
                name="demo_pink_choice"
                value="artikel"
                checked={pinkChoice === 'artikel'}
                selectedActions={pinkChoice === 'artikel' ? pinkActions : []}
                onToggleAction={(value, action, next) => {
                  setPinkChoice(value)
                  setPinkActions((prev) => {
                    const base = pinkChoice === value ? prev : []
                    if (next) return Array.from(new Set([...base, action]))
                    return base.filter((a) => a !== action)
                  })
                }}
                actions={[
                  { value: 'use', label: 'gebruik dit artikel' },
                  { value: 'add', label: 'voeg toe aan bronnen' },
                ]}
              >
                <span className="block font-semibold">Harvard Business Review — Organizational Behavior</span>
                <span className="mt-[4px] block">
                  Onderzoek naar besluitvorming en de sociale kosten van tegenspraak.
                </span>
                <a
                  href="https://knowledge.wharton.upenn.edu/topic/marketing/"
                  className="mt-[4px] block break-all text-ce-blue underline"
                >
                  https://knowledge.wharton.upenn.edu/topic/marketing/
                </a>
              </PinkChoiceOption>
              <PinkChoiceOption
                name="demo_pink_choice"
                value="tekst"
                checked={pinkChoice === 'tekst'}
                onChange={setPinkChoice}
                actionLabel="gebruik deze tekst"
              >
                <span className="block font-semibold">Why Employees Are Afraid to Speak — HBR</span>
                <blockquote className="m-0 mt-[6px] border-l-2 border-ce-blue pl-[10px]">
                  Agreeing with the boss is safe, while challenging decisions can damage standing.
                </blockquote>
              </PinkChoiceOption>
            </PinkChoiceFrame>
            <div className={pillActionsRowClass}>
              <PillButton
                variant="main"
                disabled={pinkChoice === '' || (pinkChoice === 'artikel' && pinkActions.length === 0)}
              >
                Verwerk in artikel
              </PillButton>
            </div>
          </section>

          <section>
            <h3 className="m-0 mb-[8px] text-[14px] leading-[18px] font-semibold">Sources (Grid #1)</h3>
            <SourceRow title="Example source" checkStatus="green" showBudget />
            <div className="mt-[12px] space-y-[12px]">
              <FieldSingle placeholder="Source URL" value="" onChange={() => undefined} />
              <FieldSingle placeholder="Title" value="" onChange={() => undefined} />
              <div className={pillActionsRowClass}>
                <PillButton variant="main">Submit</PillButton>
                <PillButton variant="second">Back</PillButton>
              </div>
            </div>
          </section>

          <section>
            <h3 className="m-0 mb-[8px] text-[14px] leading-[18px] font-semibold">Status rows</h3>
            <StatusRow title="Source title" checkStatus="green" />
            <AgendaRow
              title="Post name 1"
              actions={[
                { label: 'Edit' },
                { label: 'Delete' },
                { label: 'Approved', status: 'approved' },
                { label: 'Archive' },
              ]}
            />
            <div className="mt-[8px] flex flex-wrap gap-x-[12px] text-[14px] leading-[18px]">
              <StatusLabel status="draft" label="Draft" />
              <StatusLabel status="pending" label="Pending" />
              <StatusLabel status="approved" label="Approved" />
              <StatusLabel status="feedback" label="Feedback" />
              <StatusLabel status="published" label="Published" />
            </div>
            <p className="mt-[12px] text-[14px] leading-[18px] text-ce-green-text">
              Gekoppeld — leesbare groene tekst op offwhite (text-ce-green-text)
            </p>
            <div className="mt-[12px]">
              <SignalBlock
                problem="Signaalblok — horizontale lijn eronder (zoals Agenda), tekst zwart, actie blauw."
                actionLabel="Voorbeeldactie"
                actionPath="/agenda"
              />
            </div>
            <p className="text-[14px] leading-[18px] text-ce-green line-through opacity-60">
              Niet gebruiken: text-ce-green op beige
            </p>
            <div className="mt-[8px]">
              <ActionLink>Edit</ActionLink>
              <ActionSep />
              <DeleteConfirmAction onConfirm={() => undefined} />
            </div>
            <p className="m-0 mt-[8px] text-[14px] leading-[18px] text-ce-beige">
              Delete → rode Confirm delete inline; geen browser-popup.
            </p>
          </section>
        </div>
      }
    />
  )
}

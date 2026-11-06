import { useState } from 'react'
import { LIST_SECTION } from '../../layout/grid.constants'
import type { PlatformEvent } from '../../api/platform'

function eventTimestamp(event: PlatformEvent): string {
  return event.createdAt ?? event.created_at ?? ''
}

function severityClass(severity: string): string {
  if (severity === 'error') return 'text-ce-red'
  if (severity === 'warn') return 'text-ce-orange'
  return 'text-ce-black'
}

type PlatformEventLogSectionProps = {
  events: PlatformEvent[]
  title?: string
  emptyLabel?: string
  id?: string
}

/** Tenant logboek — fouten/waarschuwingen met oplossingsvoorstel. */
export function PlatformEventLogSection({
  events,
  title = 'Logboek',
  emptyLabel = 'Geen recente meldingen',
  id,
}: PlatformEventLogSectionProps) {
  const [expandedId, setExpandedId] = useState<number | null>(null)

  const filtered = events.filter((e) => e.severity === 'error' || e.severity === 'warn')

  return (
    <section id={id} className={id ? 'scroll-mt-[24px]' : undefined}>
      <h3 className={`m-0 ${LIST_SECTION.headerClass} text-ce-black`}>{title}</h3>
      {filtered.length === 0 ? (
        <p className="m-0 mt-[20px] text-[14px] leading-[18px] text-ce-green-text">{emptyLabel}</p>
      ) : (
        <ul className={`m-0 mt-[20px] list-none p-0 ${LIST_SECTION.bodyStackClass}`}>
          {filtered.map((event, index) => {
            const id = event.id ?? index
            const expanded = expandedId === id
            const context = event.context
            const hasContext = context != null && Object.keys(context).length > 0

            return (
              <li
                key={`${id}-${eventTimestamp(event)}`}
                className={`${LIST_SECTION.itemClass} text-[14px] leading-[18px]`}
              >
                <div className="flex flex-wrap items-baseline gap-x-[8px] gap-y-[2px]">
                  <span className={`font-semibold ${severityClass(event.severity)}`}>{event.event}</span>
                  <span className="text-ce-beige">({event.category})</span>
                  <span className="text-ce-beige">{eventTimestamp(event)}</span>
                </div>
                <p className="m-0 mt-[4px] text-ce-black">{event.message}</p>
                {event.suggestion && (
                  <p className="m-0 mt-[8px] text-[14px] leading-[18px] text-ce-blue">
                    <span className="font-semibold">Actie: </span>
                    {event.suggestion}
                  </p>
                )}
                {hasContext && (
                  <button
                    type="button"
                    className="mt-[6px] cursor-pointer border-0 bg-transparent p-0 text-[14px] font-semibold leading-[18px] text-ce-blue"
                    onClick={() => setExpandedId(expanded ? null : id)}
                  >
                    {expanded ? 'Verberg context' : 'Toon context'}
                  </button>
                )}
                {expanded && hasContext && (
                  <pre className="mt-[6px] overflow-x-auto rounded-[8px] border border-ce-beige/40 bg-ce-offwhite p-[10px] text-[12px] leading-[16px] text-ce-black">
                    {JSON.stringify(context, null, 2)}
                  </pre>
                )}
              </li>
            )
          })}
        </ul>
      )}
    </section>
  )
}

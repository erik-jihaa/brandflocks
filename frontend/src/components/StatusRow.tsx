import { Link, useNavigate } from 'react-router-dom'
import { LIST_ROW, LIST_SECTION } from '../layout/grid.constants'
import {
  ActionLink,
  ActionSep,
  StatusLabel,
  statusRowActionClass,
  statusRowActionStaticClass,
  statusRowArchiveClass,
  statusRowDimmedStaticClass,
} from './ui/ActionLink'
import { DeleteConfirmAction } from './ui/DeleteConfirmAction'
import type { AgendaAction } from '../utils/agendaActions'
import { hbaContentrolLabel } from '../constants/hba'

function isArchiveAction(action: AgendaAction): boolean {
  return action.action === 'archive' || action.label === 'Archive' || action.label === 'Archiveren'
}

function isDeleteAction(action: AgendaAction): boolean {
  return action.action === 'delete' || action.label === 'Delete' || action.label === 'Verwijderen'
}

/** Verlopen week: Archive én Delete blijven actief */
function isPastWeekActiveAction(action: AgendaAction): boolean {
  return isArchiveAction(action) || isDeleteAction(action)
}

function agendaLinkClass(action: AgendaAction, dimmed: boolean): string {
  if (dimmed) {
    return isPastWeekActiveAction(action) ? statusRowArchiveClass : statusRowDimmedStaticClass
  }
  return action.noHover ? statusRowActionStaticClass : statusRowActionClass
}

export type CheckStatus = 'none' | 'red' | 'green'

function CheckDot({ status }: { status: CheckStatus }) {
  return (
    <span className="inline-flex items-baseline gap-[6px]">
      <ActionLink variant="statusRow">Check</ActionLink>
      {status === 'none' ? (
        <span className="relative top-[1px] inline-block h-[8px] w-[8px] rounded-full bg-ce-beige" />
      ) : (
        <span
          className={`relative top-[1px] inline-block h-[8px] w-[8px] rounded-full ${
            status === 'red' ? 'bg-ce-red' : 'bg-ce-green-fill'
          }`}
        />
      )}
    </span>
  )
}

type StatusRowProps = {
  title: string
  checkStatus: CheckStatus
  showBudget?: boolean
}

export function StatusRow({ title, checkStatus, showBudget = true }: StatusRowProps) {
  return (
    <div className={`${LIST_ROW.rowClass} text-ce-black`}>
      <span className="font-semibold">{title}</span>
      <div className={LIST_ROW.actionsClass}>
        <ActionLink variant="statusRow">Bewerken</ActionLink>
        <ActionSep />
        <CheckDot status={checkStatus} />
        {showBudget && (
          <>
            <ActionSep />
            <ActionLink variant="statusRow">Budget</ActionLink>
          </>
        )}
        <ActionSep />
        <ActionLink variant="statusRow">Verwijderen</ActionLink>
      </div>
    </div>
  )
}

type ArchiveRowProps = {
  slotId: string
  title: string
}

/** briefing/archive.png — zelfde rij als Agenda, View Article rechts */
export function ArchiveRow({ slotId, title }: ArchiveRowProps) {
  return (
    <div className={`${LIST_ROW.rowClass} text-ce-black`}>
      <span className="font-semibold">{title}</span>
      <div className={LIST_ROW.actionsClass}>
        <Link to={`/archive/${slotId}`} className={statusRowArchiveClass}>
          Bekijk artikel
        </Link>
      </div>
    </div>
  )
}

type AgendaRowProps = {
  title: string
  phaseLabel?: string | null
  actions: AgendaAction[]
  onAction?: (action: 'delete' | 'archive') => void
  onTitleClick?: () => void
  dimmed?: boolean
  busy?: boolean
}

export function AgendaRow({
  title,
  phaseLabel,
  actions,
  onAction,
  onTitleClick,
  dimmed = false,
  busy = false,
}: AgendaRowProps) {
  const titleLink = !dimmed && !onTitleClick ? actions.find((action) => action.to)?.to : undefined

  return (
    <div
      className={`${LIST_ROW.rowClass} w-full gap-x-[12px] ${dimmed ? 'text-ce-beige' : 'text-ce-black'}`}
    >
      <span className="inline-flex min-w-0 flex-1 items-baseline font-semibold">
        {phaseLabel ? (
          <>
            <span className="shrink-0 text-ce-beige">{phaseLabel}</span>
            <ActionSep />
          </>
        ) : null}
        {onTitleClick && !dimmed ? (
          <button
            type="button"
            onClick={onTitleClick}
            disabled={busy}
            className={`min-w-0 border-0 bg-transparent p-0 text-left ${statusRowActionClass}`}
          >
            {title}
          </button>
        ) : titleLink ? (
          <Link to={titleLink} className={`min-w-0 ${statusRowActionClass}`}>
            {title}
          </Link>
        ) : (
          <span className="min-w-0">{title}</span>
        )}
      </span>
      <div className={`${LIST_ROW.actionsClass} flex shrink-0 items-baseline whitespace-nowrap`}>
        {actions.map((action, i) => {
          const pastWeekActive = isPastWeekActiveAction(action)
          const actionClass = agendaLinkClass(action, dimmed)
          const statusDimmed = dimmed && !pastWeekActive

          return (
          <span key={`${action.label}-${i}`}>
            {i > 0 && <ActionSep dimmed={dimmed} />}
            {action.action && onAction ? (
              isDeleteAction(action) ? (
                <DeleteConfirmAction
                  onConfirm={() => onAction(action.action!)}
                  disabled={busy}
                  busy={busy}
                  className={agendaLinkClass(action, dimmed)}
                />
              ) : (
                <ActionLink
                  variant="archive"
                  onClick={() => onAction(action.action!)}
                  disabled={busy}
                >
                  {action.label}
                </ActionLink>
              )
            ) : action.status ? (
              action.to && !action.noHover && !dimmed ? (
                <Link to={action.to} className="inline no-underline">
                  <StatusLabel status={action.status} label={action.label} dimmed={statusDimmed} />
                </Link>
              ) : (
                <span className="inline">
                  <StatusLabel status={action.status} label={action.label} dimmed={statusDimmed} />
                </span>
              )
            ) : action.onClick ? (
              <ActionLink variant="statusRow" onClick={action.onClick} disabled={busy}>
                {action.label}
              </ActionLink>
            ) : action.to ? (
              action.noHover && !pastWeekActive ? (
                <span className={actionClass}>{action.label}</span>
              ) : (
                <Link to={action.to} className={actionClass}>
                  {action.label}
                </Link>
              )
            ) : action.noHover && !pastWeekActive ? (
              <span className={actionClass}>{action.label}</span>
            ) : dimmed && !pastWeekActive ? (
              <span className={actionClass}>{action.label}</span>
            ) : (
              <ActionLink variant="statusRow">{action.label}</ActionLink>
            )}
          </span>
          )
        })}
      </div>
    </div>
  )
}

type CampagneListRowProps = {
  title: string
  phaseLabel: string
  onEdit: () => void
  onDelete: () => void
  onArchive: () => void
  busy?: boolean
}

const campagneListLinkClass =
  'cursor-pointer border-0 bg-transparent p-0 text-[14px] font-normal leading-[18px] text-ce-blue no-underline hover:underline decoration-[1.5px] underline-offset-[3px]'

const campagneListTitleClass = `min-w-0 text-left ${campagneListLinkClass}`

const campagneListConfirmDeleteClass =
  'cursor-pointer border-0 bg-transparent p-0 text-[14px] font-normal leading-[18px] text-ce-red no-underline hover:underline hover:decoration-ce-red'

/** Campagnes-overzicht — typografie zoals Agenda-kalender (14/18 regular). */
export function CampagneListRow({
  title,
  phaseLabel,
  onEdit,
  onDelete,
  onArchive,
  busy = false,
}: CampagneListRowProps) {
  return (
    <div className={`${LIST_ROW.rowClass} w-full gap-x-[12px] text-[14px] font-normal leading-[18px] text-ce-black`}>
      <span className="inline-flex min-w-0 flex-1 items-baseline">
        <span className="shrink-0 font-normal text-ce-beige">{phaseLabel}</span>
        <ActionSep dimmed={false} />
        <button
          type="button"
          onClick={onEdit}
          disabled={busy}
          className={`${campagneListTitleClass} ${busy ? 'cursor-not-allowed opacity-45' : ''}`}
        >
          {title}
        </button>
      </span>
      <div className={`${LIST_ROW.actionsClass} flex shrink-0 items-baseline whitespace-nowrap font-normal`}>
        <button
          type="button"
          onClick={onEdit}
          disabled={busy}
          className={`${campagneListLinkClass} ${busy ? 'cursor-not-allowed opacity-45' : ''}`}
        >
          Bewerken
        </button>
        <ActionSep dimmed={false} />
        <DeleteConfirmAction
          onConfirm={onDelete}
          disabled={busy}
          busy={busy}
          className={campagneListLinkClass}
          confirmClassName={campagneListConfirmDeleteClass}
        />
        <ActionSep dimmed={false} />
        <button
          type="button"
          onClick={onArchive}
          disabled={busy}
          className={`${campagneListLinkClass} ${busy ? 'cursor-not-allowed opacity-45' : ''}`}
        >
          Archiveren
        </button>
      </div>
    </div>
  )
}

type HomePipelineSectionProps = {
  label: string
  lines: string[]
  path: string
}

/** Home Overview — briefing/Home.png: sectie-header + statusregels + View */
export function HomePipelineSection({ label, lines, path }: HomePipelineSectionProps) {
  return (
    <section className={LIST_SECTION.sectionClass}>
      <h2 className={`${LIST_SECTION.headerClass} text-ce-black`}>{label}</h2>
      <div className={`${LIST_SECTION.bodyClass} flex items-end justify-between`}>
        <div className={`min-w-0 pr-[16px] ${LIST_SECTION.bodyStackClass}`}>
          {lines.map((line) => (
            <p key={line} className="m-0 text-[14px] leading-[18px] font-semibold text-ce-black">
              {line}
            </p>
          ))}
        </div>
        <Link to={path} className={`${statusRowActionClass} shrink-0`}>
          Bekijken
        </Link>
      </div>
    </section>
  )
}

type HomeTodoRowProps = {
  label: string
  reason: string
  path: string
  blocking: boolean
}

function TodoPriorityDot({ blue }: { blue: boolean }) {
  return (
    <span
      className={`relative top-[1px] mr-[8px] inline-block h-[8px] w-[8px] shrink-0 rounded-full ${
        blue ? 'bg-ce-blue' : 'bg-ce-beige'
      }`}
      aria-hidden
    />
  )
}

/** Overview to-do rij — zelfde stramien als nextAction */
export function HomeTodoRow({ label, reason, path, blocking }: HomeTodoRowProps) {
  const navigate = useNavigate()
  const target = path.startsWith('/') ? path : `/${path}`
  const blueDot = blocking
  const reasonClass = blocking ? 'text-ce-beige' : 'text-ce-beige-text'

  return (
    <div className="flex items-baseline justify-between border-b border-ce-black/10 pb-[12px]">
      <div className="min-w-0 pr-[16px]">
        <p className="m-0 flex items-baseline text-[14px] leading-[18px] font-semibold text-ce-black">
          <TodoPriorityDot blue={blueDot} />
          <span className="min-w-0">{label}</span>
        </p>
        <p className={`m-0 mt-[4px] pl-[16px] text-[14px] leading-[18px] ${reasonClass}`}>{reason}</p>
      </div>
      <button
        type="button"
        onClick={() => navigate(target)}
        className={`${statusRowActionClass} shrink-0`}
      >
        Ga
      </button>
    </div>
  )
}

type HomeResultLineProps = {
  label: string
  value: string
  detail?: string
}

function HomeResultLine({ label, value, detail }: HomeResultLineProps) {
  return (
    <div>
      <p className="m-0 text-[14px] leading-[18px] font-semibold text-ce-black">{label}</p>
      <p className="m-0 pt-[4px] text-[14px] leading-[18px] text-ce-black">{value}</p>
      {detail && (
        <p className="m-0 pt-[2px] text-[14px] leading-[18px] text-ce-beige">{detail}</p>
      )}
    </div>
  )
}

function formatWeeklyViews(value: number | null | undefined): string {
  return `${(value ?? 0).toLocaleString('nl-NL')} weergaven`
}

function formatWebsiteViewsValue(
  value: number | null | undefined,
  hasAnalytics: boolean,
): string {
  if (hasAnalytics) {
    return formatWeeklyViews(value ?? 0)
  }
  if (value != null) {
    return formatWeeklyViews(value)
  }
  return '— (GA4 in Bronnen)'
}

function formatLinkedInEngagement(
  totals: { likes: number; comments: number; reactions?: number },
): string {
  const parts: string[] = []
  parts.push(`${(totals.reactions ?? totals.likes).toLocaleString('nl-NL')} reacties`)
  parts.push(`${totals.likes.toLocaleString('nl-NL')} likes`)
  parts.push(`${totals.comments.toLocaleString('nl-NL')} opmerkingen`)
  return parts.join(', ')
}

type HomeResultSectionProps = {
  postsPerWeek: number
  publishedThisWeek: number
  publishedThisMonth: number
  angleMix: { archetype: number; adversary: number; general: number } | null
  contentrolMix?: Record<string, number> | null
  weekLabel: string
  websiteViewsThisWeek: number | null
  hasAnalytics: boolean
  linkedInEngagement?: {
    hasSource: boolean
    thisWeek: { reactions: number; likes: number; comments: number; reposts: number; posts: number }
    last90Days: { reactions: number; likes: number; comments: number; reposts: number; posts: number }
  }
  sourcesReview?: {
    summary: string
    expandTips: Array<{ id: string; label: string; reason: string }>
  }
  findability?: {
    score: number | null
    geoScore: number | null
    seoScore: number | null
    hasMeasurement: boolean
  }
}

/** Overview — Result-kolom (rechts, bovenaan uitgelijnd met trendkaarten) */
export function HomeResultSection({
  postsPerWeek,
  publishedThisWeek,
  publishedThisMonth,
  angleMix,
  contentrolMix = null,
  weekLabel,
  websiteViewsThisWeek,
  hasAnalytics,
  linkedInEngagement,
  sourcesReview,
  findability,
}: HomeResultSectionProps) {
  return (
    <section>
      <h2 className={`${LIST_SECTION.headerClass} text-ce-black`}>Resultaat</h2>
      <div className={`${LIST_SECTION.bodyStackClass} pt-[20px]`}>
        <HomeResultLine
          label="Ritme — artikelen/week"
          value={`${publishedThisWeek}/${postsPerWeek}`}
        />
        <HomeResultLine
          label="Gepubliceerd deze maand"
          value={String(publishedThisMonth)}
        />
        {contentrolMix && Object.keys(contentrolMix).length > 0 ? (
          <div className="flex flex-col gap-[4px]">
            {Object.entries(contentrolMix)
              .sort(([, a], [, b]) => b - a)
              .slice(0, 4)
              .map(([role, pct]) => (
                <p key={role} className="m-0 text-[14px] leading-[18px] text-ce-black">
                  {hbaContentrolLabel(role) || role} {pct}%
                </p>
              ))}
          </div>
        ) : angleMix ? (
          <div className="flex flex-col gap-[4px]">
            <p className="m-0 text-[14px] leading-[18px] text-ce-black">
              Persoonlijkheid {angleMix.archetype}%
            </p>
            <p className="m-0 text-[14px] leading-[18px] text-ce-black">
              Waar je tegen vecht {angleMix.adversary}%
            </p>
            <p className="m-0 text-[14px] leading-[18px] text-ce-black">
              Algemeen {angleMix.general}%
            </p>
          </div>
        ) : null}
        {findability?.hasMeasurement && findability.score !== null && (
          <HomeResultLine
            label="Vindbaarheidsscore"
            value={`${findability.score}/100`}
            detail={
              [
                findability.geoScore !== null ? `GEO ${findability.geoScore}%` : null,
                findability.seoScore !== null ? `SEO ${findability.seoScore}%` : null,
              ]
                .filter(Boolean)
                .join(' · ') || undefined
            }
          />
        )}
        <HomeResultLine
          label={`Website-weergaven — ${weekLabel}`}
          value={formatWebsiteViewsValue(websiteViewsThisWeek, hasAnalytics)}
        />
        {linkedInEngagement?.hasSource && (
          <>
            <HomeResultLine
              label={`LinkedIn — ${weekLabel}`}
              value={formatLinkedInEngagement(linkedInEngagement.thisWeek)}
            />
            <HomeResultLine
              label="LinkedIn — 90 dagen"
              value={formatLinkedInEngagement(linkedInEngagement.last90Days)}
            />
          </>
        )}
        {sourcesReview && sourcesReview.expandTips.length > 0 && (
          <div className="space-y-[12px]">
            <HomeResultLine label="Bronnen" value={sourcesReview.summary} />
            <div>
              <p className="m-0 pb-[8px] text-[14px] leading-[18px] font-semibold text-ce-black">
                Uitbreiden
              </p>
              <div className="flex flex-col gap-[8px]">
                {sourcesReview.expandTips.map((tip) => (
                  <div key={tip.id}>
                    <p className="m-0 text-[14px] leading-[18px] font-semibold text-ce-black">
                      {tip.label}
                    </p>
                    <p className="m-0 pt-[2px] text-[14px] leading-[18px] text-ce-beige">
                      {tip.reason}
                    </p>
                  </div>
                ))}
              </div>
            </div>
          </div>
        )}
      </div>
    </section>
  )
}

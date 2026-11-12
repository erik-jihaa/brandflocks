import { useRef, useState } from 'react'
import { FieldSingle } from './ui/Field'
import { FieldHint, FORM_FIELD_STACK_CLASS } from './ui/FieldHint'
import { Pulldown } from './ui/Pulldown'
import { PillButton, pillActionsRowClass } from './PillButton'
import { IntegrationStatusPill } from './IntegrationStatusPill'
import type { IntegrationStatus } from '../api/integrations'
import type { SourceTab } from '../types/sources'
import type { SourceFolderDto } from '../types/sources'
import { parseApifyActorFromUrl, scraperActorDisplayUrl, isLinkedInScraperSource, DEFAULT_LINKEDIN_APIFY_ACTOR_URL, DEFAULT_LINKEDIN_APIFY_ACTOR_ID } from '../utils/apifySource'

export const BUDGET_OPTIONS = ['€ 0.05', '€ 0.10', '€ 0.25', '€ 0.50', '€ 1.00'] as const

export type SourceFormValues = {
  folderId: string
  title: string
  url: string
  description: string
  budgetLabel: string
  maxUnitsPerRun: string
  trelloBoardId: string
  trelloApiTokenRef: string
  mailboxEmail: string
  mailboxImapHost: string
  mailboxPasswordRef: string
  analyticsPropertyId: string
  calendarId: string
  calendarName: string
  scraperApiTokenRef: string
  scraperActorOrTaskId: string
  scraperWebhookUrl: string
  scraperLinkedinProfile: string
  scraperLinkedinPasswordRef: string
  driveFileUrl: string
  sourceTier: 'core' | 'supplementary'
}

export function emptySourceFormValues(): SourceFormValues {
  return {
    folderId: '',
    title: '',
    url: '',
    description: '',
    budgetLabel: BUDGET_OPTIONS[0],
    maxUnitsPerRun: '',
    trelloBoardId: '',
    trelloApiTokenRef: '',
    mailboxEmail: '',
    mailboxImapHost: '',
    mailboxPasswordRef: '',
    analyticsPropertyId: '',
    calendarId: '',
    calendarName: '',
    scraperApiTokenRef: '',
    scraperActorOrTaskId: '',
    scraperWebhookUrl: '',
    scraperLinkedinProfile: '',
    scraperLinkedinPasswordRef: '',
    driveFileUrl: '',
    sourceTier: 'core',
  }
}

export function budgetLabelToAmount(label: string): number | null {
  const match = label.match(/[\d.]+/)
  if (!match) return null
  const value = Number.parseFloat(match[0])
  return Number.isNaN(value) ? null : value
}

export function amountToBudgetLabel(amount: number | null | undefined): string {
  if (amount == null) return BUDGET_OPTIONS[0]
  const found = BUDGET_OPTIONS.find((opt) => budgetLabelToAmount(opt) === amount)
  return found ?? `€ ${amount.toFixed(2)}`
}

type SourceFormProps = {
  category: SourceTab
  values: SourceFormValues
  onChange: (values: SourceFormValues) => void
  onSubmit: () => void
  onCancel?: () => void
  submitLabel?: string
  busy?: boolean
  analyticsOAuthConnected?: boolean
  analyticsOAuthEmail?: string | null
  analyticsOAuthConfigured?: boolean
  analyticsOAuthNote?: string | null
  onConnectGoogle?: () => void
  calendarOAuthConnected?: boolean
  calendarOAuthEmail?: string | null
  calendarOAuthConfigured?: boolean
  calendarOAuthNote?: string | null
  onConnectGoogleCalendar?: () => void
  calendarOptions?: Array<{ id: string; name: string; primary?: boolean }>
  loadingCalendarOptions?: boolean
  calendarOptionsNote?: string | null
  mailboxPasswordConfigured?: boolean
  scraperTokenConfigured?: boolean
  scraperLinkedinPasswordConfigured?: boolean
  driveOAuthConnected?: boolean
  driveOAuthEmail?: string | null
  driveOAuthConfigured?: boolean
  driveOAuthNote?: string | null
  onConnectGoogleDrive?: () => void
  documentFilename?: string | null
  documentUploadedAt?: string | null
  documentChunkCount?: number | null
  onDocumentFileChange?: (file: File | null) => void
  folders?: SourceFolderDto[]
  integrationStatus?: IntegrationStatus
  integrationStatusLabel?: string
  integrationStatusNote?: string | null
}

/** sources 2/3Type+.png — FieldSingle placeholders (Artboard 20), geen labels boven velden. */
export function SourceForm({
  category,
  values,
  onChange,
  onSubmit,
  onCancel,
  submitLabel = 'Submit',
  busy = false,
  folders = [],
  analyticsOAuthConnected = false,
  analyticsOAuthEmail = null,
  analyticsOAuthConfigured = true,
  analyticsOAuthNote = null,
  onConnectGoogle,
  calendarOAuthConnected = false,
  calendarOAuthEmail = null,
  calendarOAuthConfigured = true,
  calendarOAuthNote = null,
  onConnectGoogleCalendar,
  calendarOptions = [],
  loadingCalendarOptions = false,
  calendarOptionsNote = null,
  mailboxPasswordConfigured = false,
  scraperTokenConfigured = false,
  scraperLinkedinPasswordConfigured = false,
  driveOAuthConnected = false,
  driveOAuthEmail = null,
  driveOAuthConfigured = true,
  driveOAuthNote = null,
  onConnectGoogleDrive,
  documentFilename = null,
  documentUploadedAt = null,
  documentChunkCount = null,
  onDocumentFileChange,
  integrationStatus,
  integrationStatusLabel,
  integrationStatusNote = null,
}: SourceFormProps) {
  function patch(partial: Partial<SourceFormValues>) {
    onChange({ ...values, ...partial })
  }

  const folderOptions = ['Geen map', ...folders.map((folder) => folder.name)]
  const selectedFolderName =
    folders.find((folder) => folder.id === values.folderId)?.name ?? 'Geen map'

  const calendarPulldownOptions =
    calendarOptions.length > 0
      ? calendarOptions.map((c) => calendarOptionLabel(c))
      : values.calendarName
        ? [calendarOptionLabel({ id: values.calendarId, name: values.calendarName, primary: false })]
        : []

  const selectedCalendarName =
    calendarOptions.find((c) => c.id === values.calendarId)?.name != null
      ? calendarOptionLabel(calendarOptions.find((c) => c.id === values.calendarId)!)
      : values.calendarName
        ? calendarOptionLabel({ id: values.calendarId, name: values.calendarName, primary: false })
        : calendarPulldownOptions[0] ?? ''

  function googleIntegrationHint(): string | null {
    if (integrationStatus === 'connected') return null
    if (integrationStatusNote) return integrationStatusNote
    if (category === 'Analythics') {
      if (!analyticsOAuthConnected) {
        return 'Klik op Inloggen met Google om GA4 te koppelen — daarna Submit met je Property ID.'
      }
      if (!values.analyticsPropertyId.trim()) {
        return 'Vul het GA4 Property ID in en klik Submit.'
      }
      return 'Sla op en gebruik Check om de koppeling te verifiëren.'
    }
    if (category === 'Calendar') {
      if (!calendarOAuthConnected) {
        return 'Klik op Inloggen met Google om je agenda te koppelen.'
      }
      if (!values.calendarId.trim()) {
        return 'Kies een agenda en klik Submit.'
      }
      return 'Sla op en gebruik Check om de koppeling te verifiëren.'
    }
    if (category === 'Drive') {
      if (!driveOAuthConnected) {
        return 'Klik op Inloggen met Google om Drive te koppelen.'
      }
      if (!values.driveFileUrl.trim()) {
        return 'Plak de URL van het gedeelde bestand en klik Submit.'
      }
      return 'Sla op en gebruik Check om de koppeling te verifiëren.'
    }
    return null
  }

  const googleIntegrationHintText = googleIntegrationHint()

  const showLinkedInFields = isLinkedInScraperSource(values.url, values.scraperActorOrTaskId)
  const documentInputRef = useRef<HTMLInputElement>(null)
  const [selectedDocumentFile, setSelectedDocumentFile] = useState<File | null>(null)
  const documentFileReady = Boolean(selectedDocumentFile || documentFilename)

  return (
    <div className={FORM_FIELD_STACK_CLASS}>
      {folderOptions.length > 1 && (
        <Pulldown
          options={folderOptions}
          value={selectedFolderName}
          onChange={(name) => {
            if (name === 'Geen map') {
              patch({ folderId: '' })
              return
            }
            const folder = folders.find((item) => item.name === name)
            patch({ folderId: folder?.id ?? '' })
          }}
        />
      )}

      <Pulldown
        options={['Kern-bron (eigen materiaal)', 'Aanvullende bron']}
        value={values.sourceTier === 'supplementary' ? 'Aanvullende bron' : 'Kern-bron (eigen materiaal)'}
        onChange={(label) =>
          patch({ sourceTier: label.startsWith('Aanvullend') ? 'supplementary' : 'core' })
        }
      />

      {(category === 'URL/RSS' || category === 'Scraper') && category === 'URL/RSS' && (
        <>
          <FieldSingle
            placeholder="Title"
            value={values.title}
            onChange={(title) => patch({ title })}
          />
          <FieldSingle
            placeholder="Source URL"
            value={values.url}
            onChange={(url) => patch({ url })}
            hint="RSS-feed (bijv. /feed/) of een enkele webpagina. Sommige sites blokkeren bots (403) — probeer dan de feed-URL van het domein."
          />
        </>
      )}

      {category === 'Blogs' && (
        <>
          <FieldSingle
            placeholder="Title"
            value={values.title}
            onChange={(title) => patch({ title })}
          />
          <FieldSingle
            placeholder="Blogoverzicht-URL"
            value={values.url}
            onChange={(url) => patch({ url })}
            hint="Plak de overzichtspagina — we lezen de onderliggende blogposts. Voorbeeld: https://domein.nl/blog/ of https://domein.nl/blogs.html"
            helpKey="blog_overview_url"
          />
        </>
      )}

      {category === 'Document' && (
        <>
          <p className="m-0 px-[30px] text-[14px] leading-[18px] text-ce-black">
            Upload een PDF-, Word- (.docx) of PowerPoint-bestand (.pptx) (jaarverslag, whitepaper, brancherapport).
            We knippen de tekst in stukken voor research — het bestand verandert niet meer tot je
            opnieuw uploadt.
          </p>
          <FieldSingle
            placeholder="Title"
            value={values.title}
            onChange={(title) => patch({ title })}
          />
          <div className="px-[30px]">
            <p className="m-0 mb-[8px] text-[14px] leading-[18px] font-semibold text-ce-black">
              Document
            </p>
            {selectedDocumentFile ? (
              <p className="m-0 mb-[8px] text-[14px] leading-[18px] text-ce-green-text">
                {selectedDocumentFile.name}
              </p>
            ) : documentFilename ? (
              <p className="m-0 mb-[8px] text-[14px] leading-[18px] text-ce-green-text">
                {documentFilename}
                {documentUploadedAt ? ` — geüpload ${formatSourceDate(documentUploadedAt)}` : ''}
                {documentChunkCount != null ? ` (${documentChunkCount} fragmenten)` : ''}
              </p>
            ) : (
              <p className="m-0 mb-[8px] text-[14px] leading-[18px] text-ce-beige">
                Geen bestand geselecteerd.
              </p>
            )}
            <input
              ref={documentInputRef}
              type="file"
              accept=".pdf,.docx,.pptx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.openxmlformats-officedocument.presentationml.presentation"
              className="sr-only"
              onChange={(event) => {
                const file = event.target.files?.[0] ?? null
                setSelectedDocumentFile(file)
                onDocumentFileChange?.(file)
              }}
            />
          </div>
        </>
      )}

      {category === 'YouTube' && (
        <>
          <FieldSingle
            placeholder="Title"
            value={values.title}
            onChange={(title) => patch({ title })}
          />
          <FieldSingle
            placeholder="YouTube-kanaal-URL"
            value={values.url}
            onChange={(url) => patch({ url })}
            hint="Plak de kanaalpagina — we volgen nieuwe video's via de RSS-feed. Voorbeeld: https://www.youtube.com/@kanaal"
            helpKey="youtube_channel_url"
          />
        </>
      )}

      {category === 'Drive' && (
        <>
          <FieldSingle
            placeholder="Title"
            value={values.title}
            onChange={(title) => patch({ title })}
          />
          <p className="m-0 px-[30px] text-[14px] leading-[18px] text-ce-black">
            Koppel Google Drive met scope drive.file — je deelt alleen het bestand dat je hier
            aanwijst.
          </p>
          {driveOAuthNote ? <FieldHint position="below">{driveOAuthNote}</FieldHint> : null}
          {driveOAuthConnected ? (
            <p className="m-0 text-[14px] leading-[18px] text-ce-green-text">
              Gekoppeld{driveOAuthEmail ? ` als ${driveOAuthEmail}` : ''}
            </p>
          ) : (
            <PillButton
              variant="main"
              onClick={onConnectGoogleDrive}
              disabled={busy || !driveOAuthConfigured || !onConnectGoogleDrive}
            >
              Inloggen met Google
            </PillButton>
          )}
          <FieldSingle
            placeholder="Google Drive-bestands-URL"
            value={values.driveFileUrl}
            onChange={(driveFileUrl) => patch({ driveFileUrl })}
            helpKey="drive_file_url"
          />
          {googleIntegrationHintText ? (
            <FieldHint position="below">{googleIntegrationHintText}</FieldHint>
          ) : null}
        </>
      )}

      {category === 'Linkedin' && (
        <>
          <p className="m-0 px-[30px] text-[14px] leading-[18px] text-ce-black">
            LinkedIn als research-source. Vul je profiel- of bedrijfspagina-URL in — Apify draait op
            platformniveau (max. 1 compute unit per run).
          </p>
          <FieldSingle
            placeholder="Title"
            value={values.title}
            onChange={(title) => patch({ title })}
          />
          <FieldSingle
            placeholder="LinkedIn profiel- of bedrijfspagina-URL"
            value={values.scraperLinkedinProfile}
            onChange={(scraperLinkedinProfile) => patch({ scraperLinkedinProfile })}
            hint="Persoonlijk: linkedin.com/in/naam. Bedrijf: linkedin.com/company/jouwbedrijf of admin-URL met company-ID."
          />
          <FieldSingle
            placeholder="LinkedIn wachtwoord (optioneel)"
            type="password"
            value={values.scraperLinkedinPasswordRef}
            onChange={(scraperLinkedinPasswordRef) => patch({ scraperLinkedinPasswordRef })}
            hint={
              scraperLinkedinPasswordConfigured && values.scraperLinkedinPasswordRef === ''
                ? 'LinkedIn-wachtwoord opgeslagen (versleuteld) — laat leeg om te behouden.'
                : undefined
            }
          />
        </>
      )}

      {category === 'Scraper' && (
        <>
          <p className="m-0 px-[30px] text-[14px] leading-[18px] text-ce-black">
            Apify-koppeling — plak dezelfde waarden als in n8n: POST actor-URL en API-token (query
            parameter <code className="text-[13px]">token</code>). Het token slaan we versleuteld op.
          </p>
          <FieldSingle
            placeholder="Title"
            value={values.title}
            onChange={(title) => patch({ title })}
          />
          <FieldSingle
            placeholder="Apify actor URL (POST)"
            value={values.url}
            onChange={(url) => {
              const parsed = parseApifyActorFromUrl(url)
              patch({
                url,
                scraperActorOrTaskId: parsed.actorId ?? values.scraperActorOrTaskId,
              })
            }}
            helpKey="scraper_actor_url"
          />
          <FieldSingle
            placeholder="Apify API token"
            type="password"
            value={values.scraperApiTokenRef}
            onChange={(scraperApiTokenRef) => patch({ scraperApiTokenRef })}
            hint={
              scraperTokenConfigured && values.scraperApiTokenRef === ''
                ? 'API-token opgeslagen (versleuteld) — laat leeg om te behouden, of vul opnieuw in om te wijzigen.'
                : undefined
            }
          />
          <FieldSingle
            placeholder="Actor ID (optioneel, bv. user~actor-name)"
            value={values.scraperActorOrTaskId}
            onChange={(scraperActorOrTaskId) => patch({ scraperActorOrTaskId })}
          />
          <FieldSingle
            placeholder="Webhook URL (optioneel, alternatief voor actor)"
            value={values.scraperWebhookUrl}
            onChange={(scraperWebhookUrl) => patch({ scraperWebhookUrl })}
          />
          {showLinkedInFields && (
            <>
              <FieldSingle
                placeholder="LinkedIn profielnaam of URL"
                value={values.scraperLinkedinProfile}
                onChange={(scraperLinkedinProfile) => patch({ scraperLinkedinProfile })}
                hint="LinkedIn actor — profielnaam of URL voor Apify usernames. Wachtwoord alleen nodig bij inlog-actors; wij slaan het versleuteld op."
                hintPosition="above"
              />
              <FieldSingle
                placeholder="LinkedIn wachtwoord"
                type="password"
                value={values.scraperLinkedinPasswordRef}
                onChange={(scraperLinkedinPasswordRef) => patch({ scraperLinkedinPasswordRef })}
                hint={
                  scraperLinkedinPasswordConfigured && values.scraperLinkedinPasswordRef === ''
                    ? 'LinkedIn-wachtwoord opgeslagen (versleuteld) — laat leeg om te behouden.'
                    : undefined
                }
              />
            </>
          )}
          <Pulldown
            options={[...BUDGET_OPTIONS]}
            value={values.budgetLabel}
            onChange={(budgetLabel) => patch({ budgetLabel })}
          />
          <FieldSingle
            placeholder="Max units per run"
            value={values.maxUnitsPerRun}
            onChange={(maxUnitsPerRun) => patch({ maxUnitsPerRun })}
          />
        </>
      )}

      {category === 'Mailbox' && (
        <>
          <FieldSingle
            placeholder="Title"
            value={values.title}
            onChange={(title) => patch({ title })}
          />
          <FieldSingle
            placeholder="Mailbox e-mailadres"
            value={values.mailboxEmail}
            onChange={(mailboxEmail) => patch({ mailboxEmail })}
          />
          <FieldSingle
            placeholder="IMAP-host (bijv. mail.jouwdomein.nl)"
            value={values.mailboxImapHost}
            onChange={(mailboxImapHost) => patch({ mailboxImapHost })}
            helpKey="mailbox_imap_host"
          />
          <FieldSingle
            placeholder="Wachtwoord"
            type="password"
            value={values.mailboxPasswordRef}
            onChange={(mailboxPasswordRef) => patch({ mailboxPasswordRef })}
            hint={
              mailboxPasswordConfigured && values.mailboxPasswordRef === ''
                ? 'Wachtwoord opgeslagen — laat leeg om te behouden, of vul opnieuw in om te wijzigen.'
                : undefined
            }
          />
        </>
      )}

      {category === 'Analythics' && (
        <>
          <FieldSingle
            placeholder="Title"
            value={values.title}
            onChange={(title) => patch({ title })}
          />
          <p className="m-0 px-[30px] text-[14px] leading-[18px] text-ce-black">
            Statistieken uit GA4 ophalen voor rapportages — via de Google Analytics Data API.
            Google gebruikt geen API-key; koppel met je Google-account (OAuth via Google Cloud).
          </p>
          {analyticsOAuthNote ? (
            <FieldHint position="below">{analyticsOAuthNote}</FieldHint>
          ) : null}
          {analyticsOAuthConnected ? (
            <p className="m-0 text-[14px] leading-[18px] text-ce-green-text">
              Gekoppeld{analyticsOAuthEmail ? ` als ${analyticsOAuthEmail}` : ''}
            </p>
          ) : (
            <PillButton
              variant="main"
              onClick={onConnectGoogle}
              disabled={busy || !analyticsOAuthConfigured || !onConnectGoogle}
            >
              Inloggen met Google
            </PillButton>
          )}
          <FieldSingle
            placeholder="GA4 Property ID (optioneel)"
            value={values.analyticsPropertyId}
            onChange={(analyticsPropertyId) => patch({ analyticsPropertyId })}
            helpKey="analytics_property_id"
          />
          {integrationStatus && integrationStatusLabel ? (
            <div className="flex flex-wrap items-center gap-[10px] px-[30px]">
              <IntegrationStatusPill status={integrationStatus} label={integrationStatusLabel} />
            </div>
          ) : null}
          {googleIntegrationHintText ? (
            <FieldHint position="below">{googleIntegrationHintText}</FieldHint>
          ) : null}
        </>
      )}

      {category === 'Calendar' && (
        <>
          <FieldSingle
            placeholder="Title"
            value={values.title}
            onChange={(title) => patch({ title })}
          />
          <p className="m-0 px-[30px] text-[14px] leading-[18px] text-ce-black">
            Koppel je Google-agenda — events (titel, beschrijving, datum) worden meegenomen als
            research-input voor thema&apos;s.
          </p>
          {calendarOAuthNote ? (
            <FieldHint position="below">{calendarOAuthNote}</FieldHint>
          ) : null}
          {calendarOAuthConnected ? (
            <p className="m-0 text-[14px] leading-[18px] text-ce-green-text">
              Gekoppeld{calendarOAuthEmail ? ` als ${calendarOAuthEmail}` : ''}
            </p>
          ) : (
            <PillButton
              variant="main"
              onClick={onConnectGoogleCalendar}
              disabled={busy || !calendarOAuthConfigured || !onConnectGoogleCalendar}
            >
              Inloggen met Google
            </PillButton>
          )}
          {calendarOAuthConnected && (
            <div>
              <p className="m-0 mb-[8px] px-[30px] text-[14px] leading-[18px] font-semibold text-ce-black">
                Agenda
              </p>
              {calendarOptionsNote && (
                <p className="m-0 px-[30px] text-[14px] leading-[18px] text-ce-red">{calendarOptionsNote}</p>
              )}
              {calendarPulldownOptions.length > 0 ? (
                <Pulldown
                  options={calendarPulldownOptions}
                  value={loadingCalendarOptions ? 'Agenda\'s laden…' : selectedCalendarName}
                  placeholder="Kies een agenda"
                  onChange={(label) => {
                    const match = calendarOptions.find((c) => calendarOptionLabel(c) === label)
                    if (match) {
                      patch({ calendarId: match.id, calendarName: match.name })
                    }
                  }}
                />
              ) : (
                !loadingCalendarOptions && !calendarOptionsNote && (
                  <p className="m-0 px-[30px] text-[14px] leading-[18px] text-ce-beige">
                    Geen agenda&apos;s geladen.
                  </p>
                )
              )}
            </div>
          )}
          {integrationStatus && integrationStatusLabel ? (
            <div className="flex flex-wrap items-center gap-[10px] px-[30px]">
              <IntegrationStatusPill status={integrationStatus} label={integrationStatusLabel} />
            </div>
          ) : null}
          {googleIntegrationHintText ? (
            <FieldHint position="below">{googleIntegrationHintText}</FieldHint>
          ) : null}
        </>
      )}

      {category === 'Document' ? (
        <div className={pillActionsRowClass}>
          <PillButton
            variant={documentFileReady ? 'second' : 'main'}
            onClick={() => documentInputRef.current?.click()}
            disabled={busy}
          >
            Bladeren
          </PillButton>
          <PillButton variant="main" onClick={onSubmit} disabled={busy || !documentFileReady} loading={busy}>
            {submitLabel}
          </PillButton>
          {onCancel ? (
            <PillButton variant="second" onClick={onCancel} disabled={busy}>
              Back
            </PillButton>
          ) : null}
        </div>
      ) : (
        <div className={pillActionsRowClass}>
          <PillButton variant="main" onClick={onSubmit} disabled={busy} loading={busy}>
            {submitLabel}
          </PillButton>
          {onCancel ? (
            <PillButton variant="second" onClick={onCancel} disabled={busy}>
              Back
            </PillButton>
          ) : null}
        </div>
      )}
    </div>
  )
}

export function sourceFormValuesToPayload(
  category: SourceTab,
  values: SourceFormValues,
): Record<string, string | number | null | undefined> {
  const payload: Record<string, string | number | null | undefined> = {
    category,
    folderId: values.folderId || null,
    title: values.title || undefined,
    url: values.url || undefined,
    description: values.description || undefined,
    sourceTier: values.sourceTier,
  }

  if (category === 'Linkedin') {
    payload.url = DEFAULT_LINKEDIN_APIFY_ACTOR_URL
    payload.scraperActorOrTaskId = DEFAULT_LINKEDIN_APIFY_ACTOR_ID
    payload.scraperLinkedinProfile = values.scraperLinkedinProfile.trim() || undefined
    payload.scraperLinkedinPasswordRef = values.scraperLinkedinPasswordRef || undefined
    payload.maxUnitsPerRun = 1
  }

  if (category === 'Scraper') {
    const apify = parseApifyActorFromUrl(values.url)
    payload.url = (apify.apiUrl ?? values.url.trim()) || undefined
    payload.scraperApiTokenRef = values.scraperApiTokenRef || undefined
    payload.scraperActorOrTaskId =
      apify.actorId || values.scraperActorOrTaskId.trim() || undefined
    payload.scraperWebhookUrl = values.scraperWebhookUrl || undefined
    payload.scraperLinkedinProfile = values.scraperLinkedinProfile.trim() || undefined
    payload.scraperLinkedinPasswordRef = values.scraperLinkedinPasswordRef || undefined
    payload.budgetPerUnit = budgetLabelToAmount(values.budgetLabel)
    const maxUnits = Number.parseInt(values.maxUnitsPerRun, 10)
    payload.maxUnitsPerRun = Number.isNaN(maxUnits) ? null : maxUnits
  }

  if (category === 'Mailbox') {
    payload.mailboxEmail = values.mailboxEmail || undefined
    payload.mailboxImapHost = values.mailboxImapHost || undefined
    payload.mailboxPasswordRef = values.mailboxPasswordRef || undefined
  }

  if (category === 'Analythics') {
    payload.analyticsPropertyId = values.analyticsPropertyId || undefined
  }

  if (category === 'Calendar') {
    payload.calendarId = values.calendarId || undefined
    payload.calendarName = values.calendarName || undefined
  }

  if (category === 'YouTube') {
    payload.url = values.url.trim() || undefined
  }

  if (category === 'Drive') {
    payload.driveFileUrl = values.driveFileUrl.trim() || undefined
  }

  return payload
}

export function sourceDtoToFormValues(source: {
  folderId?: string | null
  title: string
  url: string | null
  description: string | null
  category?: SourceTab
  budgetPerUnit: number | null
  maxUnitsPerRun: number | null
  trelloBoardId?: string | null
  trelloTokenConfigured?: boolean
  trelloApiTokenRef?: string | null
  mailboxEmail?: string | null
  mailboxImapHost?: string | null
  mailboxPasswordConfigured?: boolean
  mailboxPasswordRef?: string | null
  analyticsPropertyId?: string | null
  analyticsOAuthConnected?: boolean
  analyticsOAuthEmail?: string | null
  calendarId?: string | null
  calendarName?: string | null
  calendarOAuthConnected?: boolean
  calendarOAuthEmail?: string | null
  scraperTokenConfigured?: boolean
  scraperApiTokenRef?: string | null
  scraperActorOrTaskId?: string | null
  scraperWebhookUrl?: string | null
  scraperLinkedinProfile?: string | null
  scraperLinkedinPasswordConfigured?: boolean
  driveFileUrl?: string | null
  sourceTier?: 'core' | 'supplementary'
}): SourceFormValues {
  const displayUrl =
    source.category === 'Linkedin'
      ? ''
      : source.category === 'Scraper'
        ? scraperActorDisplayUrl(source.url, source.scraperActorOrTaskId)
        : (source.url ?? '')

  return {
    folderId: source.folderId ?? '',
    title: source.title,
    url: displayUrl,
    description: source.description ?? '',
    budgetLabel: amountToBudgetLabel(source.budgetPerUnit),
    maxUnitsPerRun: source.maxUnitsPerRun != null ? String(source.maxUnitsPerRun) : '',
    trelloBoardId: source.trelloBoardId ?? '',
    trelloApiTokenRef: '',
    mailboxEmail: source.mailboxEmail ?? '',
    mailboxImapHost: source.mailboxImapHost ?? '',
    mailboxPasswordRef: '',
    analyticsPropertyId: source.analyticsPropertyId ?? '',
    calendarId: source.calendarId ?? '',
    calendarName: source.calendarName ?? '',
    scraperApiTokenRef: '',
    scraperActorOrTaskId: source.scraperActorOrTaskId ?? '',
    scraperWebhookUrl: source.scraperWebhookUrl ?? '',
    scraperLinkedinProfile: source.scraperLinkedinProfile ?? '',
    scraperLinkedinPasswordRef: '',
    driveFileUrl: source.driveFileUrl ?? '',
    sourceTier: source.sourceTier === 'supplementary' ? 'supplementary' : 'core',
  }
}

export function defaultSourceRowTitle(category: SourceTab): string {
  return {
    'URL/RSS': 'URL title',
    Blogs: 'Blog title',
    Document: 'Document title',
    YouTube: 'YouTube title',
    Drive: 'Google Drive',
    Linkedin: 'LinkedIn title',
    Scraper: 'Scraper title',
    Mailbox: 'Mailbox title',
    Analythics: 'Analythics title',
    Calendar: 'Google Calendar',
  }[category]
}

function formatSourceDate(value: string): string {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return date.toLocaleDateString('nl-NL', { day: 'numeric', month: 'short', year: 'numeric' })
}

function calendarOptionLabel(calendar: { id: string; name: string; primary?: boolean }): string {
  return calendar.primary ? `${calendar.name} (primair)` : calendar.name
}

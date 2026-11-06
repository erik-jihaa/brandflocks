import type { CheckStatus } from '../components/StatusRow'

export type SourceFolderDto = {
  id: string
  name: string
  sortOrder: number
  objectiveId?: string | null
  objectiveLabel?: string | null
}

export type SourceTab =
  | 'URL/RSS'
  | 'Blogs'
  | 'Document'
  | 'YouTube'
  | 'Asana'
  | 'Drive'
  | 'Linkedin'
  | 'Scraper'
  | 'Mailbox'
  | 'Analythics'
  | 'Trello'
  | 'Calendar'

export type SourceDto = {
  id: string
  category: SourceTab
  folderId?: string | null
  folderName?: string | null
  title: string
  url: string | null
  description: string | null
  checkStatus: CheckStatus
  showBudget: boolean
  budgetPerUnit: number | null
  maxUnitsPerRun: number | null
  trelloBoardId?: string | null
  trelloTokenConfigured?: boolean
  trelloApiTokenRef?: string | null
  mailboxEmail?: string | null
  mailboxImapHost?: string | null
  mailboxPasswordConfigured?: boolean
  mailboxPasswordRef?: string | null
  analyticsDatastreamId?: string | null
  analyticsSecretConfigured?: boolean
  analyticsApiSecretRef?: string | null
  analyticsPropertyId?: string | null
  analyticsOAuthConnected?: boolean
  analyticsOAuthEmail?: string | null
  calendarOAuthConnected?: boolean
  calendarOAuthEmail?: string | null
  calendarId?: string | null
  calendarName?: string | null
  scraperTokenConfigured?: boolean
  platformApifyConfigured?: boolean
  scraperApiTokenRef?: string | null
  scraperActorOrTaskId?: string | null
  scraperWebhookUrl?: string | null
  scraperLinkedinProfile?: string | null
  scraperLinkedinPasswordConfigured?: boolean
  scraperLinkedinPasswordRef?: string | null
  asanaPatConfigured?: boolean
  asanaWorkspaceGid?: string | null
  asanaProjectGid?: string | null
  asanaWorkspaceName?: string | null
  asanaProjectName?: string | null
  documentFilename?: string | null
  documentUploadedAt?: string | null
  documentChunkCount?: number | null
  driveOAuthConnected?: boolean
  driveOAuthEmail?: string | null
  driveFileUrl?: string | null
  active: boolean
  lastCheckedAt: string | null
  checkMessage?: string | null
  sourceTier?: 'core' | 'supplementary'
}

export type CreateSourcePayload = {
  category: SourceTab
  folderId?: string | null
  title?: string
  url?: string
  description?: string
  budgetPerUnit?: number | null
  maxUnitsPerRun?: number | null
  trelloBoardId?: string
  trelloApiTokenRef?: string
  mailboxEmail?: string
  mailboxImapHost?: string
  mailboxPasswordRef?: string
  analyticsDatastreamId?: string
  analyticsApiSecretRef?: string
  analyticsPropertyId?: string
  calendarId?: string
  calendarName?: string
  scraperApiTokenRef?: string
  scraperActorOrTaskId?: string
  scraperWebhookUrl?: string
  scraperLinkedinProfile?: string
  scraperLinkedinPasswordRef?: string
  asanaPatRef?: string
  asanaWorkspaceGid?: string
  asanaProjectGid?: string
  asanaWorkspaceName?: string
  asanaProjectName?: string
  driveFileUrl?: string
  sourceTier?: 'core' | 'supplementary'
}

export type UpdateSourcePayload = Partial<Omit<CreateSourcePayload, 'category'>>

export type MailboxNewsletterItem = {
  id: string
  subject: string
  bodyText: string | null
  receivedAt: string | null
}

export type SourceInspirationSuggestion = {
  category: SourceTab
  title: string
  url: string
  description: string
  stelling?: string
  invalshoek?: string
  wat?: string
  verrassendOmdat?: string
}

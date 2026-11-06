import { apiFetch } from './client'

export type SupportChatMessage = {
  role: 'user' | 'assistant'
  content: string
}

export type SupportChatContext = {
  fieldKey: string
  screen?: string
}

export type SupportChatBudget = {
  usedEur: number
  limitEur: number
  remainingEur: number
  blocked: boolean
  messageCount: number
}

export function fetchSupportChatBudget(): Promise<{ budget: SupportChatBudget }> {
  return apiFetch('/support-chat')
}

export function sendSupportChatMessages(
  messages: SupportChatMessage[],
  context?: SupportChatContext,
): Promise<{ reply: string; budget: SupportChatBudget }> {
  const body: { messages: SupportChatMessage[]; context?: SupportChatContext } = { messages }
  if (context?.fieldKey) {
    body.context = context
  }
  return apiFetch('/support-chat', {
    method: 'POST',
    body: JSON.stringify(body),
  })
}

export function escalateSupportChat(payload: {
  messages: SupportChatMessage[]
  context?: SupportChatContext
  draft?: string
}): Promise<{ ok: true; message: string }> {
  const body: {
    messages: SupportChatMessage[]
    context?: SupportChatContext
    draft?: string
  } = {
    messages: payload.messages,
  }
  if (payload.context?.fieldKey) {
    body.context = payload.context
  }
  const draft = payload.draft?.trim()
  if (draft) {
    body.draft = draft
  }
  return apiFetch('/support-chat/escalate', {
    method: 'POST',
    body: JSON.stringify(body),
  })
}

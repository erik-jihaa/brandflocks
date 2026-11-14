import { apiFetch } from './client'

export type ChannelConstraintsDto = {
  textvormByPlatform: Record<string, Array<{ value: string; label: string }>>
  defaultTextvormByPlatform: Record<string, string>
  hardLimits: Record<
    string,
    {
      max_combined_chars?: number | null
      max_body_chars?: number | null
      max_headline_chars?: number | null
      max_hashtags?: number | null
    }
  >
}

let cache: ChannelConstraintsDto | null = null
let inflight: Promise<ChannelConstraintsDto | null> | null = null

export async function fetchChannelConstraints(): Promise<ChannelConstraintsDto | null> {
  if (cache) return cache
  if (inflight) return inflight
  inflight = apiFetch<{ ok: boolean } & ChannelConstraintsDto>('/channel-constraints')
    .then((data) => {
      const { ok: _ok, ...rest } = data
      cache = rest
      return cache
    })
    .catch(() => null)
    .finally(() => {
      inflight = null
    })
  return inflight
}

export function channelConstraintsCache(): ChannelConstraintsDto | null {
  return cache
}
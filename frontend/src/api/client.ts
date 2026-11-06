const API_BASE = import.meta.env.VITE_API_BASE ?? '/api'

export class ApiError extends Error {
  status: number

  constructor(message: string, status: number) {
    super(message)
    this.name = 'ApiError'
    this.status = status
  }
}

function apiNetworkErrorMessage(path: string, err: unknown): string {
  const msg = err instanceof Error ? err.message : ''
  if (msg.includes('NetworkError') || msg.includes('Failed to fetch') || msg.includes('Load failed')) {
    if (path.includes('/trigger')) {
      return 'Research-start time-out — de server wacht niet meer op CE-02. Vernieuw de pagina over een minuut.'
    }
    if (path.includes('/themes/generate')) {
      return 'Theme-generatie duurde te lang voor de serververbinding. Vernieuw over een minuut — of klik opnieuw op Genereer.'
    }
    return 'Verbinding met de server mislukt — controleer je netwerk en vernieuw de pagina.'
  }
  return msg || 'Verzoek mislukt'
}

export async function apiFetch<T>(path: string, init?: RequestInit): Promise<T> {
  let response: Response
  try {
    response = await fetch(`${API_BASE}${path}`, {
      credentials: 'include',
      ...init,
      headers: {
        Accept: 'application/json',
        ...(init?.body ? { 'Content-Type': 'application/json' } : {}),
        ...init?.headers,
      },
    })
  } catch (err) {
    throw new ApiError(apiNetworkErrorMessage(path, err), 0)
  }

  if (!response.ok) {
    let message = `Request failed (${response.status})`
    try {
      const body = (await response.json()) as { error?: string }
      if (body.error) message = body.error
    } catch {
      // ignore parse errors
    }
    throw new ApiError(message, response.status)
  }

  return response.json() as Promise<T>
}

export function isApiConfigured(): boolean {
  return import.meta.env.VITE_USE_MOCK !== 'true'
}

export type ObjectiveDto = {
  id: string
  clientId: string
  label: string
  description: string
  percentage: number
  sortOrder: number
  writingStyle: string
  sourceFolderId?: string | null
}

export type ObjectivesPayload = {
  objectives: ObjectiveDto[]
  percentageTotal: number
  percentageValid: boolean
}

export type ObjectiveDraft = {
  id: string
  label: string
  description: string
  percentage: number
  sortOrder: number
  writingStyle: string
  sourceFolderId: string
}

import { useEffect, useState } from 'react'
import { channelConstraintsCache, fetchChannelConstraints, type ChannelConstraintsDto } from '../api/channelConstraints'

export function useChannelConstraints(): {
  constraints: ChannelConstraintsDto | null
  loading: boolean
} {
  const [constraints, setConstraints] = useState<ChannelConstraintsDto | null>(channelConstraintsCache())
  const [loading, setLoading] = useState(constraints === null)

  useEffect(() => {
    let cancelled = false
    void fetchChannelConstraints().then((data) => {
      if (cancelled) return
      setConstraints(data)
      setLoading(false)
    })
    return () => {
      cancelled = true
    }
  }, [])

  return { constraints, loading }
}
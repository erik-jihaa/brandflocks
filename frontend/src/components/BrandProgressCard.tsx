import type { HbaProgress } from '../api/hbaV2'
import { BrandWatcherSection } from './BrandWatcherSection'

type BrandProgressCardProps = {
  progress: HbaProgress
}

/** Overview — compact HBA opbouwfase / PBS (WP6 + WP5). */
export function BrandProgressCard({ progress }: BrandProgressCardProps) {
  return <BrandWatcherSection progress={progress} variant="compact" />
}

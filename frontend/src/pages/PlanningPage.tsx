import { GridShell } from '../layout/GridShell'
import { PlanningSettings } from '../components/PlanningSettings'

/** Planning — horizon, posts/week, bronchecks/week. */
export function PlanningPage() {
  return (
    <GridShell
      pageTitle="Planning"
      activeNav="planning"
      main={<PlanningSettings />}
    />
  )
}

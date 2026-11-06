import { GridShell } from '../layout/GridShell'
import { GRID2_REVIEW_MAIN_WIDTH } from '../layout/grid.constants'
import { ObjectivesSection } from '../components/ObjectivesSection'

/** Objective — uitklapbare doelstellingen met percentage en schrijfstijl */
export function TargetsPage() {
  return (
    <GridShell
      pageTitle="Doelen"
      activeNav="targets"
      mainLayout="grid2"
      main={
        <div style={{ maxWidth: GRID2_REVIEW_MAIN_WIDTH }}>
          <ObjectivesSection showPercentages />
        </div>
      }
    />
  )
}

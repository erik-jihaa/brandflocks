import { GridShell } from '../layout/GridShell'
import { MerkarchitectuurSection } from '../components/MerkarchitectuurSection'
import { GRID2_REVIEW_MAIN_WIDTH } from '../layout/grid.constants'

const PAGE_TITLE = 'Merkarchitectuur'
const PAGE_SUBTITLE =
  'Gebaseerd op Hero Brand Core Logic — als je merkarchitectuur staat, kan merkcontrole op artikelen draaien.'

/** HBA-wizard — Story Triangle, branche, kalibratie. */
export function BrandArchitecturePage() {
  return (
    <GridShell
      pageTitle={PAGE_TITLE}
      pageSubtitle={PAGE_SUBTITLE}
      activeNav="brandArchitecture"
      mainLayout="grid2"
      main={
        <div style={{ maxWidth: GRID2_REVIEW_MAIN_WIDTH }}>
          <MerkarchitectuurSection />
        </div>
      }
    />
  )
}

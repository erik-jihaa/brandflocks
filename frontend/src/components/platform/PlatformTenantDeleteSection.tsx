import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { LIST_SECTION } from '../../layout/grid.constants'
import { PillButton } from '../PillButton'
import { FieldSingle } from '../ui/Field'
import { SaveNotice } from '../ui/SaveNotice'
import { useSaveNotice } from '../../hooks/useSaveNotice'
import { deletePlatformTenant } from '../../api/platform'

type PlatformTenantDeleteSectionProps = {
  clientId: string
  company: string
  environment: string
  onDeleted: () => void
}

export function PlatformTenantDeleteSection({
  clientId,
  company,
  environment,
  onDeleted,
}: PlatformTenantDeleteSectionProps) {
  const navigate = useNavigate()
  const { message, kind, fading, showSaving, showSuccess, showError } = useSaveNotice()
  const [confirmName, setConfirmName] = useState('')
  const [busy, setBusy] = useState(false)

  const isProduction = environment === 'production'

  async function handleDelete() {
    if (confirmName !== company) {
      showError('Typ de exacte bedrijfsnaam om te bevestigen')
      return
    }

    setBusy(true)
    showSaving('Tenant verwijderen…')
    try {
      await deletePlatformTenant(clientId, {
        confirmCompanyName: confirmName,
        ...(isProduction ? { confirmEnvironment: 'production' } : {}),
      })
      showSuccess(`${company} verwijderd`)
      onDeleted()
      navigate('/platform')
    } catch (err) {
      showError(err instanceof Error ? err.message : 'Verwijderen mislukt')
    } finally {
      setBusy(false)
    }
  }

  return (
    <section className={LIST_SECTION.bodyClass}>
      <h3 className={`m-0 ${LIST_SECTION.headerClass} text-ce-red`}>Tenant verwijderen</h3>
      <p className="m-0 mt-[12px] text-[14px] leading-[18px] text-ce-beige">
        Verwijdert de tenant en alle gekoppelde data permanent.
        {isProduction ? ' Op productie is extra bevestiging verplicht.' : ''}
      </p>

      <div className="mt-[20px]">
        <FieldSingle
          value={confirmName}
          onChange={setConfirmName}
          placeholder={`Typ "${company}" om te bevestigen`}
        />
      </div>

      <div className="mt-[20px]">
        <PillButton variant="second" onClick={() => void handleDelete()} disabled={busy || confirmName === ''}>
          Definitief verwijderen
        </PillButton>
      </div>
      <SaveNotice message={message} kind={kind} fading={fading} />
    </section>
  )
}

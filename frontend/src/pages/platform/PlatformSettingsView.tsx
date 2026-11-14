import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { LIST_SECTION } from '../../layout/grid.constants'
import { PillButton } from '../../components/PillButton'
import { SaveNotice } from '../../components/ui/SaveNotice'
import { useSaveNotice } from '../../hooks/useSaveNotice'
import { ApiError } from '../../api/client'
import {
  downloadPlatformDerivedBranchesCsv,
  fetchPlatformDerivedBranches,
  fetchPlatformSession,
  importPlatformSupportEmails,
  platformSupportImportSkipLabel,
  type PlatformDerivedBrancheRow,
} from '../../api/platform'
import { usePlatformData } from '../../components/platform/PlatformDataContext'

export function PlatformSettingsView() {
  const navigate = useNavigate()
  const { status, platformUser, reload } = usePlatformData()
  const { message, kind, fading, showSaving, showSuccess, showError } = useSaveNotice()
  const [importBusy, setImportBusy] = useState(false)
  const [importDetails, setImportDetails] = useState<string[]>([])
  const [derivedRows, setDerivedRows] = useState<PlatformDerivedBrancheRow[]>([])
  const [derivedError, setDerivedError] = useState<string | null>(null)
  const [csvBusy, setCsvBusy] = useState(false)
  const isAdmin = platformUser?.role === 'admin'

  useEffect(() => {
    void fetchPlatformDerivedBranches()
      .then((res) => setDerivedRows(res.items ?? []))
      .catch((err) => setDerivedError(err instanceof Error ? err.message : 'Logboek laden mislukt'))
  }, [])

  async function handleImportEmails() {
    if (!isAdmin) return
    setImportBusy(true)
    setImportDetails([])
    showSaving('Support-mails ophalen…')
    try {
      const session = await fetchPlatformSession()
      if (!session.authenticated) {
        navigate('/platform/login', { replace: true, state: { from: '/platform/settings' } })
        return
      }
      const result = await importPlatformSupportEmails()
      const imported = result.importedCount ?? 0
      const skipped = result.skippedCount ?? 0
      const lines =
        result.skipped?.map((item) => {
          const label = platformSupportImportSkipLabel(item.reason)
          const subject = item.subject ? ` — ${item.subject}` : ''
          const sender = item.sender ? ` (${item.sender})` : ''
          const detail = item.detail ? `: ${item.detail}` : ''
          return `${label}${sender}${subject}${detail}`
        }) ?? []
      setImportDetails(lines)
      showSuccess(`${imported} geïmporteerd · ${skipped} overgeslagen`)
      reload()
    } catch (err) {
      if (err instanceof ApiError && err.status === 401) {
        navigate('/platform/login', { replace: true, state: { from: '/platform/settings' } })
        return
      }
      showError(err instanceof Error ? err.message : 'Import mislukt')
    } finally {
      setImportBusy(false)
    }
  }

  return (
    <div className={LIST_SECTION.bodyStackClass}>
      <section className={LIST_SECTION.bodyClass}>
        <h2 className={`${LIST_SECTION.headerClass} text-ce-blue`}>Settings</h2>
        {status && (
          <dl className="m-0 mt-[20px] space-y-[10px] text-[14px] leading-[18px]">
            <div>
              <dt className="font-semibold">Omgeving</dt>
              <dd className="m-0 text-ce-beige">{status.environment}</dd>
            </div>
            {status.lastMigration && (
              <div>
                <dt className="font-semibold">Laatste migratie</dt>
                <dd className="m-0 text-ce-beige">
                  {status.lastMigration.filename} ({status.lastMigration.applied_at})
                </dd>
              </div>
            )}
          </dl>
        )}
      </section>

      {isAdmin && (
        <section className={LIST_SECTION.bodyClass}>
          <h3 className={`m-0 ${LIST_SECTION.headerClass} text-ce-black`}>Support-mail import</h3>
          <p className="m-0 mt-[12px] text-[14px] leading-[18px] text-ce-beige">
            Haalt de 25 meest recente mails op uit de support-inbox. Alleen mails van afzenders die exact
            overeenkomen met het contact- of reviewer-e-mailadres van een tenant worden een ticket. Automatisch
            zonder login: <code className="text-[13px]">./scripts/import-support-emails.sh production</code>.
          </p>
          <div className="mt-[16px]">
            <PillButton variant="main" disabled={importBusy} loading={importBusy} onClick={() => void handleImportEmails()}>
              Importeer support-mails
            </PillButton>
          </div>
          {importDetails.length > 0 && (
            <ul className="m-0 mt-[12px] list-none space-y-[6px] p-0 text-[14px] leading-[18px] text-ce-beige">
              {importDetails.map((line, index) => (
                <li key={`${index}-${line}`}>{line}</li>
              ))}
            </ul>
          )}
          <SaveNotice message={message} kind={kind} fading={fading} />
        </section>
      )}

      <section className={LIST_SECTION.bodyClass}>
        <h3 className={`m-0 ${LIST_SECTION.headerClass} text-ce-black`}>Afgeleide branches</h3>
        <p className="m-0 mt-[12px] text-[14px] leading-[18px] text-ce-beige">
          Namen die klanten invoerden en die niet in de gecureerde bibliotheek stonden. CSV volgt de kolommen van
          branchelijst.csv, plus aantal keer en laatste datum.
        </p>
        {derivedError ? (
          <p className="m-0 mt-[12px] text-[14px] leading-[18px] text-ce-red">{derivedError}</p>
        ) : derivedRows.length === 0 ? (
          <p className="m-0 mt-[12px] text-[14px] leading-[18px] text-ce-beige">Nog geen afgeleide branches.</p>
        ) : (
          <ul className="m-0 mt-[12px] list-none space-y-[8px] p-0 text-[14px] leading-[18px] text-ce-black">
            {derivedRows.slice(0, 12).map((row) => (
              <li key={row.typedLabel}>
                {row.typedLabel} — {row.hitCount}×, laatst {row.lastSeenAt || '—'}
              </li>
            ))}
          </ul>
        )}
        <div className="mt-[16px]">
          <PillButton
            variant="second"
            disabled={csvBusy}
            loading={csvBusy}
            onClick={() => {
              setCsvBusy(true)
              void downloadPlatformDerivedBranchesCsv()
                .catch((err) => showError(err instanceof Error ? err.message : 'CSV-export mislukt'))
                .finally(() => setCsvBusy(false))
            }}
          >
            Exporteer CSV
          </PillButton>
        </div>
      </section>
    </div>
  )
}

import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { LIST_SECTION } from '../../layout/grid.constants'
import { PillButton, pillActionsRowClass } from '../../components/PillButton'
import { FieldSingle } from '../../components/ui/Field'
import { FieldHint } from '../../components/ui/FieldHint'
import { CheckboxOption } from '../../components/ui/RadioOption'
import { SaveNotice } from '../../components/ui/SaveNotice'
import { useSaveNotice } from '../../hooks/useSaveNotice'
import { usePlatformData } from '../../components/platform/PlatformDataContext'
import { createPlatformTenant } from '../../api/platform'
import { DEFAULT_REVIEWER_EMAIL } from '../../config/installContact'

const ERIK_REVIEWER_EMAIL = DEFAULT_REVIEWER_EMAIL

export function PlatformCreateTenantView() {
  const navigate = useNavigate()
  const { platformUser, reload } = usePlatformData()
  const { message, kind, fading, showSaving, showSuccess, showError } = useSaveNotice()

  const [company, setCompany] = useState('')
  const [contactEmail, setContactEmail] = useState('')
  const [contactName, setContactName] = useState('')
  const [password, setPassword] = useState('')
  const [postsPerWeek, setPostsPerWeek] = useState('1')
  const [erikMeekijk, setErikMeekijk] = useState(true)
  const [busy, setBusy] = useState(false)

  const isAdmin = platformUser?.role === 'admin'

  if (!isAdmin) {
    return (
      <p className="m-0 text-[14px] leading-[18px] text-ce-beige">
        Alleen platform-admins kunnen tenants aanmaken.
      </p>
    )
  }

  async function handleSubmit() {
    const companyTrim = company.trim()
    const emailTrim = contactEmail.trim().toLowerCase()
    if (companyTrim === '' || emailTrim === '') {
      showError('Bedrijfsnaam en e-mail zijn verplicht')
      return
    }
    if (password !== '' && password.length < 8) {
      showError('Wachtwoord moet minimaal 8 tekens zijn')
      return
    }

    const posts = Math.max(1, parseInt(postsPerWeek, 10) || 1)

    setBusy(true)
    showSaving('Tenant aanmaken…')
    try {
      const result = await createPlatformTenant({
        company: companyTrim,
        contactEmail: emailTrim,
        contactName: contactName.trim() || undefined,
        password: password || undefined,
        postsPerWeek: posts,
        ...(erikMeekijk
          ? { reviewerEmail: ERIK_REVIEWER_EMAIL, contactRole: 'contact' as const }
          : {}),
      })
      showSuccess(`${companyTrim} aangemaakt`)
      reload()
      const clientId = result.client?.clientId
      if (clientId) {
        navigate(`/platform/tenant/${clientId}`)
      }
    } catch (err) {
      showError(err instanceof Error ? err.message : 'Aanmaken mislukt')
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className={LIST_SECTION.bodyStackClass}>
      <section className={LIST_SECTION.bodyClass}>
        <h2 className={`m-0 ${LIST_SECTION.headerClass} text-ce-blue`}>Nieuwe tenant</h2>
        <p className="m-0 mt-[12px] text-[14px] leading-[18px] text-ce-beige">
          Maakt een lege tenant aan. Strategie, bronnen en integraties stel je daarna in via de tenant-app of
          provisioning-scripts.
        </p>

        <div className="mt-[20px] grid gap-[16px]">
          <FieldSingle
            value={company}
            onChange={setCompany}
            placeholder="Bedrijfsnaam"
            autoComplete="organization"
          />
          <FieldSingle
            value={contactEmail}
            onChange={setContactEmail}
            placeholder="Contact e-mail"
            type="email"
            autoComplete="email"
          />
          <FieldSingle
            value={contactName}
            onChange={setContactName}
            placeholder="Contactpersoon (optioneel)"
            autoComplete="name"
          />
          <FieldSingle
            value={password}
            onChange={setPassword}
            placeholder="Wachtwoord (optioneel, min. 8 tekens)"
            type="password"
            autoComplete="new-password"
          />
          <FieldSingle
            value={postsPerWeek}
            onChange={setPostsPerWeek}
            placeholder="Posts per week"
            type="text"
          />
          <fieldset className="m-0 flex flex-col gap-[6px] border-0 p-0">
            <CheckboxOption
              checked={erikMeekijk}
              onChange={setErikMeekijk}
              label={`Erik meekijk (${ERIK_REVIEWER_EMAIL})`}
            />
            {erikMeekijk ? (
              <FieldHint position="below">
                Review-mails gaan naar {ERIK_REVIEWER_EMAIL}. Erik kan inloggen met dat adres en hetzelfde
                wachtwoord als de klant.
              </FieldHint>
            ) : null}
          </fieldset>
        </div>

        <div className={pillActionsRowClass}>
          <PillButton variant="main" onClick={() => void handleSubmit()} disabled={busy} loading={busy}>
            Tenant aanmaken
          </PillButton>
        </div>
        <SaveNotice message={message} kind={kind} fading={fading} />
      </section>
    </div>
  )
}

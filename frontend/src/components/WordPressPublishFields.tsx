import { FieldSingle } from './ui/Field'
import { FieldHint, FORM_FIELD_STACK_CLASS } from './ui/FieldHint'
import { Pulldown } from './ui/Pulldown'
import type { AccountDto, WebsiteCms } from '../api/account'
import { WEBSITE_CMS_OPTIONS, websiteCmsFromLabel, websiteCmsLabel } from '../api/account'

type WordPressPublishFieldsProps = {
  draft: AccountDto
  saved: AccountDto
  onDraftChange: (updater: (prev: AccountDto) => AccountDto) => void
  wordpressAppPassword: string
  onWordpressAppPasswordChange: (value: string) => void
  joomlaApiToken: string
  onJoomlaApiTokenChange: (value: string) => void
  siteFtpPassword: string
  onSiteFtpPasswordChange: (value: string) => void
}

function emptyCmsFields(cms: WebsiteCms): Partial<AccountDto> {
  if (cms === 'wordpress') {
    return {
      joomlaSiteUrl: '',
      joomlaCategoryId: null,
      siteFtpHost: '',
      siteFtpPort: 21,
      siteFtpUser: '',
      siteFtpRemotePath: '',
    }
  }
  if (cms === 'joomla') {
    return {
      wordpressSiteUrl: '',
      wordpressUser: '',
      wordpressAuthorId: null,
      siteFtpHost: '',
      siteFtpPort: 21,
      siteFtpUser: '',
      siteFtpRemotePath: '',
    }
  }
  if (cms === 'static') {
    return {
      wordpressSiteUrl: '',
      wordpressUser: '',
      wordpressAuthorId: null,
      joomlaSiteUrl: '',
      joomlaCategoryId: null,
    }
  }
  return {}
}

/** Website CMS — Settings → Integrations */
export function WordPressPublishFields({
  draft,
  saved,
  onDraftChange,
  wordpressAppPassword,
  onWordpressAppPasswordChange,
  joomlaApiToken,
  onJoomlaApiTokenChange,
  siteFtpPassword,
  onSiteFtpPasswordChange,
}: WordPressPublishFieldsProps) {
  const cmsLabel = websiteCmsLabel(draft.websiteCms)

  function handleCmsChange(label: string) {
    const nextCms = websiteCmsFromLabel(label)
    onDraftChange((prev) => ({
      ...prev,
      websiteCms: nextCms,
      ...emptyCmsFields(nextCms),
    }))
    if (nextCms !== 'wordpress') onWordpressAppPasswordChange('')
    if (nextCms !== 'joomla') onJoomlaApiTokenChange('')
    if (nextCms !== 'static') onSiteFtpPasswordChange('')
  }

  return (
    <div className={FORM_FIELD_STACK_CLASS}>
      <Pulldown
        placeholder="Website-CMS kiezen"
        options={WEBSITE_CMS_OPTIONS.map((opt) => opt.label)}
        value={cmsLabel}
        onChange={handleCmsChange}
      />

      <FieldSingle
        placeholder="Blog-URL (publiek)"
        value={draft.websiteBlogUrl}
        onChange={(v) => onDraftChange((d) => ({ ...d, websiteBlogUrl: v }))}
        hint={
          draft.websiteCms === 'static'
            ? 'Overzichtspagina van je blog, bijv. https://jihaa.nl/blogs.html'
            : 'Publieke blog-URL op je site — gebruikt voor live links na publicatie.'
        }
        hintPosition="above"
      />

      {draft.websiteCms === 'wordpress' ? (
        <>
          <FieldSingle
            placeholder="WordPress site-URL (https://jouwsite.nl)"
            value={draft.wordpressSiteUrl}
            onChange={(v) => onDraftChange((d) => ({ ...d, wordpressSiteUrl: v }))}
            hint="REST-basis, geen /wp-admin. CE-blog: https://ce.igisnext.com/blog — niet staging.igisnext.com (dat is alleen de app)."
          />
          <FieldSingle
            placeholder="WordPress gebruiker"
            value={draft.wordpressUser}
            onChange={(v) => onDraftChange((d) => ({ ...d, wordpressUser: v }))}
            hint="Loginnaam (content-engine), geen e-mail of display name. App-wachtwoord via wp-admin → Gebruikers → Application Passwords."
          />
          <FieldSingle
            id="integrations-wp-app-password"
            placeholder={saved.hasWordpressAppPassword ? '•••••••• (app-wachtwoord)' : 'WordPress app-wachtwoord'}
            type="password"
            value={wordpressAppPassword}
            onChange={onWordpressAppPasswordChange}
            helpKey="wordpress_app_password"
          />
          <FieldSingle
            placeholder="WordPress auteur-ID (optioneel)"
            value={draft.wordpressAuthorId != null ? String(draft.wordpressAuthorId) : ''}
            onChange={(v) =>
              onDraftChange((d) => ({
                ...d,
                wordpressAuthorId: v.trim() === '' ? null : Math.max(1, parseInt(v, 10) || 1),
              }))
            }
            hint="Leeg laten = de ingelogde WP-gebruiker."
          />
        </>
      ) : null}

      {draft.websiteCms === 'joomla' ? (
        <>
          <FieldSingle
            placeholder="Joomla site-URL (https://jouwsite.nl)"
            value={draft.joomlaSiteUrl}
            onChange={(v) => onDraftChange((d) => ({ ...d, joomlaSiteUrl: v }))}
          />
          <FieldSingle
            placeholder="Joomla categorie-ID"
            value={draft.joomlaCategoryId != null ? String(draft.joomlaCategoryId) : ''}
            onChange={(v) =>
              onDraftChange((d) => ({
                ...d,
                joomlaCategoryId: v.trim() === '' ? null : Math.max(1, parseInt(v, 10) || 1),
              }))
            }
            hint="Content → Categories — het numerieke ID van de blog-categorie."
          />
          <FieldSingle
            id="integrations-joomla-api-token"
            placeholder={saved.hasJoomlaApiToken ? '•••••••• (API-token)' : 'Joomla API-token'}
            type="password"
            value={joomlaApiToken}
            onChange={onJoomlaApiTokenChange}
            helpKey="joomla_api_token"
          />
        </>
      ) : null}

      {draft.websiteCms === 'static' ? (
        <>
          <FieldHint position="above" helpKey="site_ftp" />
          <FieldSingle
            placeholder="FTP host"
            value={draft.siteFtpHost}
            onChange={(v) => onDraftChange((d) => ({ ...d, siteFtpHost: v }))}
          />
          <FieldSingle
            placeholder="FTP poort"
            value={String(draft.siteFtpPort)}
            onChange={(v) =>
              onDraftChange((d) => ({
                ...d,
                siteFtpPort: Math.max(1, parseInt(v.replace(/\D/g, ''), 10) || 21),
              }))
            }
          />
          <FieldSingle
            placeholder="FTP gebruiker"
            value={draft.siteFtpUser}
            onChange={(v) => onDraftChange((d) => ({ ...d, siteFtpUser: v }))}
          />
          <FieldSingle
            id="integrations-ftp-password"
            placeholder={saved.hasSiteFtpPassword ? '••••••••' : 'FTP wachtwoord'}
            type="password"
            value={siteFtpPassword}
            onChange={onSiteFtpPasswordChange}
          />
          <FieldSingle
            placeholder="FTP pad op server (bijv. /httpdocs)"
            value={draft.siteFtpRemotePath}
            onChange={(v) => onDraftChange((d) => ({ ...d, siteFtpRemotePath: v }))}
          />
        </>
      ) : null}

      {!draft.websiteCms ? (
        <FieldHint position="below">Kies eerst WordPress, Joomla of statische HTML (FTP).</FieldHint>
      ) : (
        <FieldHint position="below">
          Alleen de velden van {cmsLabel} worden opgeslagen — andere CMS-instellingen worden gewist.
        </FieldHint>
      )}
    </div>
  )
}

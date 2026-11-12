import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { GridShell } from '../layout/GridShell'
import { GRID2_REVIEW_MAIN_WIDTH } from '../layout/grid.constants'
import { borderedSectionClass, designStackClass } from '../design/identity.constants'
import { fetchSupportInfo, type SupportInfo } from '../api/home'
import { isApiConfigured } from '../api/client'
import { SupportChatBox } from '../components/SupportChatBox'
import { DEFAULT_DOCS_URL, DEFAULT_SUPPORT_EMAIL } from '../config/installContact'

const SECTION_CLASS = borderedSectionClass

/** Support — contact & hulp */
export function SupportPage() {
  const [searchParams] = useSearchParams()
  const askKey = searchParams.get('ask') ?? undefined
  const fromPath = searchParams.get('from') ?? undefined
  const [info, setInfo] = useState<SupportInfo | null>(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    if (!isApiConfigured()) {
      setLoading(false)
      return
    }
    void fetchSupportInfo()
      .then(setInfo)
      .finally(() => setLoading(false))
  }, [])

  const email = info?.email ?? DEFAULT_SUPPORT_EMAIL
  const docsUrl = info?.docsUrl ?? DEFAULT_DOCS_URL

  return (
    <GridShell
      pageTitle="Help"
      activeNav="support"
      main={
        <div className={designStackClass} style={{ maxWidth: GRID2_REVIEW_MAIN_WIDTH }}>
          {loading && <p className="m-0 text-[14px] leading-[18px] text-ce-beige">Laden…</p>}

          <section className={SECTION_CLASS}>
            <p className="m-0 text-[24px] leading-[28px] font-semibold text-ce-blue">Contact</p>
            <p className="m-0 pt-[12px] text-[14px] leading-[21px] text-ce-black">
              Vragen over onboarding of een bug? Mail ons:
            </p>
            <a
              href={`mailto:${email}`}
              className="mt-[10px] inline-block text-[18px] font-semibold text-ce-blue underline"
            >
              {email}
            </a>
          </section>

          <section id="support-chat" className={SECTION_CLASS}>
            <p className="m-0 text-[24px] leading-[28px] font-semibold text-ce-blue">
              Onboarding-assistent
            </p>
            <div className="pt-[12px]">
              <SupportChatBox askKey={askKey} fromPath={fromPath} supportEmail={email} />
            </div>
          </section>

          <section className={SECTION_CLASS}>
            <p className="m-0 text-[24px] leading-[28px] font-semibold text-ce-blue">
              Veelgestelde onderwerpen
            </p>
            <ul className="m-0 list-disc space-y-[8px] pl-[20px] pt-[12px] text-[14px] leading-[21px] text-ce-black">
              <li>
                <strong>BrandElements & Sources</strong> — eenmalig bij onboarding; daarna sporadisch
                aanpassen.
              </li>
              <li>
                <strong>Theme → Research → Agenda</strong> — wekelijkse cyclus; CE-01/02 vullen
                thema&apos;s en research automatisch aan.
              </li>
              <li>
                <strong>Submit vs AI-review</strong> — Submit stuurt naar hoofdredacteur (human review);
                AI-review vraagt Claude-commentaar in de app.
              </li>
              <li>
                <strong>Review-link</strong> — na human submit krijg je een <code>reviewUrl</code> in
                de API-response (ook zonder mail).
              </li>
            </ul>
          </section>

          <section className={SECTION_CLASS}>
            <p className="m-0 text-[24px] leading-[28px] font-semibold text-ce-blue">Kosten & verbruik</p>
            <p className="m-0 pt-[12px] text-[14px] leading-[21px] text-ce-black">
              Per tenant: Claude, n8n, Apify, dataverkeer en overige diensten (Yoast, hosting, …).
            </p>
            <Link
              to="/support/costs"
              className="mt-[10px] inline-block text-[14px] font-semibold text-ce-blue underline"
            >
              Open rekentool →
            </Link>
          </section>

          <section className="text-[14px] leading-[21px] text-ce-beige">
            <p className="m-0">
              Documentatie:{' '}
              <a href={docsUrl} className="text-ce-blue underline" target="_blank" rel="noopener noreferrer">
                {docsUrl}
              </a>
            </p>
            {info && (
              <p className="m-0 pt-[8px]">
                Human review webhook:{' '}
                {info.humanReviewConfigured ? (
                  <span className="text-ce-black">geconfigureerd</span>
                ) : (
                  <span className="text-ce-beige">niet geconfigureerd — mail wordt overgeslagen</span>
                )}
              </p>
            )}
          </section>
        </div>
      }
    />
  )
}

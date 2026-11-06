import { useNavigate } from 'react-router-dom'
import { ArrowIcon } from './ArrowIcon'
import { ActionLink } from './ui/ActionLink'

/** Inklapbare tips onder Thema's — scherpere, USP-gerichte voorstellen. */
export function ThemeGenerationTips() {
  const navigate = useNavigate()

  return (
    <details className="group mt-[18px] border-t border-ce-beige pt-[18px]">
      <summary className="flex cursor-pointer list-none items-center gap-[6px] text-[14px] leading-[18px] font-semibold text-ce-black [&::-webkit-details-marker]:hidden">
        <span>Tip — Betere thema&apos;s genereren</span>
        <span className="inline-flex transition-transform group-open:rotate-180">
          <ArrowIcon direction="up" className="h-[9px] w-[9px]" />
        </span>
      </summary>

      <div className="mt-[12px] space-y-[12px] text-[14px] leading-[18px] text-ce-black">
        <p className="m-0 text-ce-beige">
          Je krijgt maximaal drie AI-voorstellen per week om uit te kiezen. Kwaliteit hangt vooral af van
          jullie merkcontext en bronnen.
        </p>

        <ul className="m-0 list-disc space-y-[8px] pl-[18px]">
          <li>
            Scherp Merkelementen en Merkarchitectuur aan:{' '}
            <strong className="font-semibold">waar je tegen vecht</strong>, merkdoel en schrijfwijzer (wat
            je wél en niet post).
            <span className="mt-[4px] flex flex-wrap gap-x-[12px] gap-y-[4px]">
              <ActionLink variant="statusRow" onClick={() => navigate('/styleguide')}>
                Merkelementen →
              </ActionLink>
              <ActionLink variant="statusRow" onClick={() => navigate('/brandarchitecture')}>
                Merkarchitectuur →
              </ActionLink>
            </span>
          </li>
          <li>
            Voeg vak-RS-feeds, blogs of nieuws toe onder Bronnen — meer signalen betekent scherpere
            invalshoeken.
            <span className="mt-[4px] block">
              <ActionLink variant="statusRow" onClick={() => navigate('/sources')}>
                Bronnen →
              </ActionLink>
            </span>
          </li>
          <li>
            Zwakke voorstellen? Zet ze allemaal op verwijderen en klik op Indienen — dan draait een nieuwe
            scan.
          </li>
          <li>
            Wacht een week voordat je opnieuw genereert — dan is er weer een verse pool aan artikelen en
            signalen uit je bronnen om uit te putten.
          </li>
          <li>
            Vul onderaan een <strong className="font-semibold">eigen thema</strong> in als je al een
            scherpe USP-hoek hebt.
          </li>
          <li>
            Kies vooral voorstellen met label <strong className="font-semibold">versterkt</strong> in het
            positioneringsadvies hierboven.
          </li>
          <li>
            Meer thema&apos;s per week indienen? Verhoog posts per week in Planning (je blijft maximaal drie
            AI-voorstellen krijgen).
            <span className="mt-[4px] block">
              <ActionLink variant="statusRow" onClick={() => navigate('/planning')}>
                Planning →
              </ActionLink>
            </span>
          </li>
        </ul>
      </div>
    </details>
  )
}

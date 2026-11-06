/**
 * iGiSNext identity — gedeelde Tailwind-classes (canonical: docs/igisnext-identity.md)
 */

/** Body line-height — ruimte boven/onder pill-vorm t.o.v. lijn of ander element */
export const PILL_LINE_GAP_PX = 18

/** Pulldown / Add Sources menu — 1px beige outline, pill-vorm */
export const pulldownBoxClass =
  'w-full rounded-[20px] border border-ce-beige bg-transparent'

/** Pulldown met max-breedte (Sources) */
export const pulldownBoxClassNarrow = `${pulldownBoxClass} max-w-[320px]`

/** Ruimte vóór pill-knoppen of pulldown t.o.v. veld, copy of lijn */
export const pillLineGapBeforeClass = 'mt-[18px]'

/** Copy/regel → afgerond beige kader: één witregel (14/18) */
export const frameLineGapBeforeClass = 'mt-[18px]'

/** Ruimte onder pill-knoppen t.o.v. lijn eronder */
export const pillLineGapAfterClass = 'mb-[18px]'

/**
 * Horizontale rij main/second pills.
 * 18px boven de rij; knoppen zelf zonder extra mb (voorkomt dubbele witregel tot lijn).
 * In `designStackClass` valt de extra mt weg — de stack-gap ís de witregel.
 */
export const pillActionsRowClass = `ce-pill-actions ${pillLineGapBeforeClass} flex flex-wrap items-start gap-[12px] [&_button]:mb-0`

/** Pill-rij binnen StrategySection of andere wrapper die al mt-[18px] regel→inhoud levert */
export const pillActionsRowInnerClass =
  'flex flex-wrap items-start gap-[12px] [&_button]:mb-0'

/**
 * Verticale stack tussen design-items: max één witregel (14/18).
 * Geen gap + mt/mb stapelen. Lijstrijen blijven LIST_SECTION (20px).
 */
export const designStackClass = 'flex flex-col gap-[18px] [&_.ce-pill-actions]:mt-0'

/** Hyperlink — Figtree semibold blauw; underline bij hover (canonical: docs/igisnext-identity.md) */
export const hyperlinkClass =
  'font-semibold text-ce-blue no-underline hover:underline decoration-[1.5px] underline-offset-[3px]'

/** Lijst- en statusrij-acties (14/18) */
export const hyperlinkClassBody = `${hyperlinkClass} text-[14px] leading-[18px]`

/** Grotere actielink (18/32) */
export const hyperlinkClassLg = `${hyperlinkClass} text-[18px] leading-[32px]`

/** Theme/Research bron-URL — 14/18 zwart, pijl →, geen underline (geen hypertext-stijl) */
export const sourceArticleUrlLinkClass =
  'text-[14px] leading-[18px] font-semibold text-ce-black no-underline hover:text-ce-black break-all'

/**
 * Navigatie naar andere pagina — naast pill-knoppen (Integrations, Settings).
 * 14px semibold, pijl →, geen underline. Verticaal gecentreerd met pill (40px).
 * Licht: blauw; Account-dark: groen (text-ce-blue token-swap).
 */
export const pageNavLinkClass =
  'inline-flex h-[40px] items-center self-start text-[14px] font-semibold leading-none text-ce-blue no-underline hover:text-ce-blue hover:no-underline'

/** Pill-rij met page-nav-link: centreer link-typo met knoppen */
export const pillActionsRowWithNavLinkClass = `${pillActionsRowClass} items-center [&_a]:mb-0`

/** Sectie met lijn onder — 18px tussen inhoud en lijn */
export const borderedSectionClass = 'border-b border-ce-beige pb-[18px]'

/**
 * Wrapper voor opeenvolgende `<section>`-blokken (StrategyField / StrategySection).
 * Lijn → kop: elke sectie na de eerste krijgt pt-[18px] vóór de blauwe kop.
 * Kop → lijn: borderedSectionClass op elke sectie (pb-[18px] vóór border-b).
 * Geen tussenliggende divs met kleinere gap — anders klopt lijn→kop niet.
 */
export const borderedSectionStackClass = '[&>section+section]:pt-[18px]'

/** Opeenvolgende design-items met lijn ertussen: 18px na de lijn, geen extra gap.
 *  Directe pill-knoppen krijgen mt i.p.v. pt — pt op <button> duwt label naar beneden in de pill. */
export const designItemStackClass =
  '[&>*+*]:pt-[18px] [&>button]:mt-[18px] [&>button]:pt-0'

/** Grid layout — briefing/stramien 1|2|3.png */
export const GRID = {
  totalWidth: 1440,
  topMargin: 60,
  headerHeight: 63,
  /** Tenant-logo veld (sidebar + Account): 1,5× header, groeit omhoog */
  tenantLogoHeight: 63 * 1.5,
  /**
   * Max logo-hoogte: 2× 20% kleiner dan veldhoogte → 0.8² × 94.5 ≈ 60.5px.
   * Breedte blijft iGiSNext-referentie (200px).
   */
  tenantLogoMaxHeight: 63 * 1.5 * 0.8 * 0.8,
  headerContentGap: 40,
  colNav: 270,
  /** Tenant-logoveld: zelfde max-breedte als iGiSNext platform-logo (max-w 200px) */
  tenantLogoMaxWidth: 200,
  /** Gutter tussen nav en main */
  colGap: 30,
  colMain: 820,
  /** Gutter tussen main en meta */
  colGapMeta: 30,
  colMeta: 290,
  /** Grid #2 — content-blok max 740px, padding 30px → inner 680px */
  colMainInner: 740,
  colMainInnerPadding: 30,
  /** Grid #1 list pages — main content starts aligned with sidebar "Home" (row 3) */
  navItemLineHeight: 24,
} as const

/**
 * Tenant-logo anker + content-uitlijning met sidebar-nav.
 * PAGE_HEADER: py 4px + title leading 32px → onderkant typografie op 36px.
 * Sidebar-logo: onderkant = die lijn (groeit omhoog in het vlak).
 * Sidebar-nav (Overview) start na baseline + headerContentGap.
 */
export const TENANT_LOGO_BASELINE =
  4 + 32 /* PAGE_HEADER py-top + pageTitle line-height */

/** Verschil header-band (63) vs titelbaseline (36) — content trekt dit omhoog. */
export const MAIN_CONTENT_NAV_ALIGN =
  GRID.headerHeight - TENANT_LOGO_BASELINE

export const TENANT_LOGO_LAYOUT = {
  /** Sidebar — onderkant veld = onderkant header-typografie (Account, Overview, …) */
  sidebarMarginTop: -(GRID.tenantLogoHeight - TENANT_LOGO_BASELINE),
  /** Account-upload in content-flow; logo rechtsonder in het vlak */
  accountMarginTop: 0,
} as const

export const GRID_TEMPLATE = `${GRID.colNav}px ${GRID.colGap}px ${GRID.colMain}px ${GRID.colGapMeta}px ${GRID.colMeta}px`

/** Agenda kalender — main+meta (820+30+290), geen meta-kolom */
export const AGENDA_MAIN_WIDTH = GRID.colMain + GRID.colGapMeta + GRID.colMeta

/** Platform dashboard — main-kolom 500px (zelfde als Strategy/Account grid2) */
export const PLATFORM_MAIN_WIDTH = 500

export function platformGridTemplate(mainWidth: number = PLATFORM_MAIN_WIDTH): string {
  return `${GRID.colNav}px ${GRID.colGap}px ${mainWidth}px`
}

export const GRID2_INNER_WIDTH =
  GRID.colMainInner - GRID.colMainInnerPadding * 2

/** Overview — main inner (820 − 2×30) minus Result-kolom en gap */
export const OVERVIEW_MAIN_INNER_WIDTH = GRID.colMain - GRID.colMainInnerPadding * 2
export const OVERVIEW_RESULT_WIDTH = 240
export const OVERVIEW_COLUMNS_GAP = 40
export const OVERVIEW_TODO_COLUMN_WIDTH =
  OVERVIEW_MAIN_INNER_WIDTH - OVERVIEW_RESULT_WIDTH - OVERVIEW_COLUMNS_GAP

/** Article-tekst — zelfde regelbreedte als Overview To-do kolom (480px) */
export const ARTICLE_COLUMN_WIDTH = 480
export const ARTICLE_TEXT_MAX_WIDTH_CLASS = 'max-w-[480px]' as const

/** Article/Fastlane: meta (incl. HBA-hint) direct naast de contentkolom */
export function gridTemplateFlush(mainInner: number): string {
  return `${GRID.colNav}px ${GRID.colGap}px ${GRID.colMainInnerPadding + mainInner}px ${GRID.colGapMeta}px ${GRID.colMeta}px`
}

/** Consistente horizontale padding voor main-kolom (header + content) */
export const GRID_MAIN_PAD = {
  paddingLeft: GRID.colMainInnerPadding,
  paddingRight: GRID.colMainInnerPadding,
  boxSizing: 'border-box' as const,
}

/** Lijst-secties (Home, Agenda, Sources, …) — witruimte uit design-spec */
export const LIST_SECTION = {
  headerClass: 'm-0 text-[24px] leading-[32px] font-semibold',
  /** 10px boven sectie-header (of boven eerste item in platte lijst) */
  sectionClass: 'pt-[10px]',
  listTopClass: 'pt-[10px]',
  /** Header → eerste regel: 20px; na laatste regel vóór lijn: 20px */
  bodyClass: 'border-b border-ce-black/10 pt-[20px] pb-[20px]',
  /** 20px tussen regels binnen één sectie */
  bodyStackClass: 'flex flex-col gap-[20px]',
  /** Enkele regel zonder sectie-header — lijn + 20px onder de regel */
  itemClass: 'border-b border-ce-black/10 pb-[20px]',
  /** Overzicht/Rapport — sectie met horizontale lijn (zoals Agenda), geen roze kaders */
  dashboardSectionClass: 'border-b border-ce-black/10 pt-[20px] pb-[20px]',
  /** Sources: 20px boven én onder elke bronregel (titel = header) */
  sourcesItemClass: 'border-b border-ce-black/10 pt-[20px] pb-[20px]',
  /** Bron in map: lijn uit met map-titel (pijl 11px + gap 8px) */
  sourcesFolderChildIndentClass: 'pl-[19px]',
} as const

/** Pagina-header — titel mag over meerdere regels (min. één regelhoogte 63px band) */
export const PAGE_HEADER = {
  rowClass: 'flex min-h-[63px] shrink-0 items-start py-[4px]',
  titleOffsetClass: '',
  pageTitleClass:
    'm-0 block w-full min-w-0 whitespace-normal break-words text-[32px] leading-[32px] font-semibold text-ce-blue',
  sectionTitleClass: `m-0 whitespace-normal break-words ${LIST_SECTION.headerClass} text-ce-black`,
} as const

/** Grid #2 rij — zelfde kolom-x als Article/Feedback (680px inner, 220px side, gap 20) */
export const GRID2_ROW = {
  rowClass: 'flex items-start gap-[20px]',
  mainClass: 'min-w-0 flex-1',
  sideClass: 'w-[220px] shrink-0',
  sideWidth: 220,
  gap: 20,
} as const

/** Breedte pink kader naast review-kolom — research.png / account velden */
export const GRID2_REVIEW_MAIN_WIDTH =
  GRID.colMainInner - GRID2_ROW.sideWidth - GRID2_ROW.gap

/** Meta-kolom — zelfde x als ArticleMetaSidebar radio's */
export const GRID_META = {
  width: GRID.colMeta,
  widthClass: 'w-[290px]',
} as const

/** Pink kader — px/py uit Artboard 20 / ArticleCard */
export const PINK_BOX_CLASS = 'rounded-[20px] bg-ce-pink px-[30px] py-[16px]'

/** Rij met titel links + acties/status rechts — baseline titel = baseline acties */
export const LIST_ROW = {
  rowClass:
    'flex items-baseline justify-between text-[14px] leading-[18px]',
  actionsClass: 'shrink-0',
} as const

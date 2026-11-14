/** Klikmodel v271 — artikel-wizard (7 stappen). Teksten letterlijk waar mogelijk. */

export type ArticleStartMode = '' | 'een' | 'meerdere' | 'eigen'

export const ARTICLE_START_MODE_LABELS: Record<Exclude<ArticleStartMode, ''>, string> = {
  een: 'Eén artikel',
  meerdere: 'Meerdere artikelen',
  eigen: 'Ik heb het artikel al',
}

export const ARTICLE_START_STEPS = [
  { title: 'Hoeveel artikelen?', question: 'Hoeveel artikelen?' },
  { title: 'Titel', question: 'Waar gaat het over?' },
  { title: 'Welke vragen en feiten gaan mee?', question: 'Welke vragen en feiten gaan mee?' },
  { title: 'Aantal artikelen en kanalen', question: 'Hoeveel artikelen, en op welke kanalen?' },
  { title: 'Invalshoeken', question: 'Welke invalshoek per artikel?' },
  { title: 'Periode', question: 'Wanneer?' },
  { title: 'Klopt het plan?', question: 'Klopt het plan?' },
] as const

export const ARTICLE_START_PAGE_LEAD =
  'Begin bij een stuk content. Jij bepaalt of daar één artikel uit komt, meerdere, of dat je al iets hebt.'

export const ARTICLE_START_FORK_NOTE_EEN_MEERDERE =
  'Bij stap 2 (Titel) geef je dit artikel een onderwerp. De rest van de stappen volgt net als bij een campagne.'

export const ARTICLE_START_EIGEN_NOTE =
  "Geen Onderzoek-stap hierbij — je tekst gaat direct naar het Artikel-detail, waar 'm nog wel langs dezelfde merkarchitectuur-check en aandachtspunten gaat als een door de app geschreven artikel."
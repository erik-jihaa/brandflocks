import type { ArticleStartDraft } from './useArticleStartDraft'
import { ARTICLE_START_CHANNELS } from './articleStartMerk'
import { borderedSectionClass } from '../design/identity.constants'
import { CheckboxOption } from '../components/ui/RadioOption'
import { FieldSingle } from '../components/ui/Field'
import { PillButton } from '../components/PillButton'

const SECTION = borderedSectionClass

type Props = {
  draft: ArticleStartDraft
  setDraft: (patch: Partial<ArticleStartDraft>) => void
}

/** Klikmodel `p-artikelstart-kanalen`. */
export function ArticleStartStepKanalen({ draft, setDraft }: Props) {
  const linkedinOn = draft.channels.linkedin

  function toggleChannel(key: (typeof ARTICLE_START_CHANNELS)[number]['key']) {
    setDraft({
      channels: { ...draft.channels, [key]: !draft.channels[key] },
    })
  }

  function selectedChannelCount(): number {
    return ARTICLE_START_CHANNELS.filter((c) => draft.channels[c.key]).length
  }

  return (
    <div className="space-y-[14px]">
      <section className={SECTION}>
        <h2 className="m-0 text-[18px] leading-[22px] font-semibold text-ce-black">Artikelen</h2>
        <div className="mt-[12px]">
        <FieldSingle
          id="article-start-count"
          label="Aantal"
          value={String(draft.articleCount)}
          onChange={(v) => {
            const n = Number(v.replace(/\D/g, ''))
            if (!Number.isFinite(n)) return
            setDraft({ articleCount: Math.min(99, Math.max(1, n || 1)) })
          }}
          placeholder="2"
        />
        </div>
      <h2 className="m-0 mt-[18px] text-[14px] leading-[18px] font-semibold text-ce-black">Voor welke kanalen?</h2>
      <p className="m-0 mt-[8px] text-[14px] leading-[21px] text-ce-beige">
        Dit zijn geen losse artikelen per kanaal — het is hetzelfde artikel, in de vorm die bij elk kanaal past. Gebruik
        je een artikel toch niet op een van deze kanalen, dan plaats je &apos;m daar simpelweg niet; publiceren doe je
        overigens niet met deze tool, hier maak je alleen de content.
      </p>
      <div className="mt-[12px] flex flex-col gap-[10px]">
        {ARTICLE_START_CHANNELS.map((ch) => (
          <CheckboxOption
            key={ch.key}
            checked={draft.channels[ch.key]}
            onChange={() => toggleChannel(ch.key)}
            label={ch.label}
          />
        ))}
      </div>
      {selectedChannelCount() === 0 ? (
        <p className="m-0 mt-[8px] text-[14px] leading-[18px] text-ce-red">Kies minstens één kanaal.</p>
      ) : null}
    </section>

    {linkedinOn ? (
      <section className={SECTION}>
        <h2 className="m-0 text-[18px] leading-[22px] font-semibold text-ce-black">LinkedIn: posts of artikelen?</h2>
        <div className="mt-[12px] flex flex-wrap items-center gap-[6px]">
          <PillButton
            variant={draft.linkedinType === 'post' ? 'main' : 'second'}
            className="mb-0"
            onClick={() => setDraft({ linkedinType: 'post' })}
          >
            Posts
          </PillButton>
          <PillButton
            variant={draft.linkedinType === 'artikel' ? 'main' : 'second'}
            className="mb-0"
            onClick={() => setDraft({ linkedinType: 'artikel' })}
          >
            Artikelen
          </PillButton>
        </div>
        <p className="m-0 mt-[10px] text-[14px] leading-[21px] text-ce-beige">
          Instagram, X en Facebook kennen alleen posts, dus die keuze geldt hier alleen voor LinkedIn.
        </p>
      </section>
    ) : null}
    </div>
  )
}
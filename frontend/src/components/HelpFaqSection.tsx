import { useState } from 'react'
import { HELP_FAQ } from '../data/helpFaq.klikmodel'
import { filterToggleClass } from './channelFilterStyles'

/**
 * FAQ uit klikmodel — onderwerp-pills, één vraag tegelijk open (geen scroll-hack).
 */
export function HelpFaqSection() {
  const [activeTopic, setActiveTopic] = useState(0)
  const [openQuestion, setOpenQuestion] = useState<number | null>(null)

  const topic = HELP_FAQ[activeTopic]
  if (!topic) return null

  function chooseTopic(index: number) {
    setActiveTopic(index)
    setOpenQuestion(null)
  }

  function toggleQuestion(index: number) {
    setOpenQuestion((current) => (current === index ? null : index))
  }

  return (
    <div>
      <p className="m-0 text-[14px] leading-[21px] text-ce-beige">
        Kies een onderwerp. Daaronder staan de bijbehorende vragen — klik een vraag open voor het
        antwoord.
      </p>
      <div className="mt-[14px] flex flex-wrap items-baseline gap-x-[0px] gap-y-[8px]" role="tablist">
        {HELP_FAQ.map((item, index) => (
          <span key={item.topic} className="inline-flex items-baseline">
            {index > 0 ? (
              <span className="px-[8px] text-[14px] font-semibold leading-[18px] text-ce-black">|</span>
            ) : null}
            <button
              type="button"
              role="tab"
              aria-selected={index === activeTopic}
              className={filterToggleClass(index === activeTopic)}
              onClick={() => chooseTopic(index)}
            >
              {item.topic}
            </button>
          </span>
        ))}
      </div>
      <div id={`faq-topic-${activeTopic}`} className="mt-[14px] flex flex-col gap-[10px]">
        {topic.vragen.map((item, index) => {
          const open = openQuestion === index
          const panelId = `faq-a-${activeTopic}-${index}`
          return (
            <div
              key={`${activeTopic}-${index}-${item.v}`}
              className="border-b border-ce-beige pb-[10px] last:border-b-0 last:pb-0"
            >
              <button
                type="button"
                className="flex w-full cursor-pointer items-start justify-between gap-[10px] border-0 bg-transparent p-0 text-left"
                aria-expanded={open}
                aria-controls={panelId}
                onClick={() => toggleQuestion(index)}
              >
                <span className="flex-1 text-[14px] font-semibold leading-[18px] text-ce-black">
                  {item.v}
                </span>
                <span className="shrink-0 text-[13px] leading-[18px] text-ce-blue whitespace-nowrap">
                  {open ? '– verberg' : '+ toon'}
                </span>
              </button>
              {open ? (
                <p
                  id={panelId}
                  className="m-0 mt-[8px] text-[14px] leading-[21px] text-ce-black"
                >
                  {item.a}
                </p>
              ) : null}
            </div>
          )
        })}
      </div>
    </div>
  )
}
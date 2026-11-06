/** Render beperkte rich text: **bold**, koppen met ### of ## als vet regels. */
export function RichTextDisplay({ text, className = '' }: { text: string; className?: string }) {
  if (!text.trim()) {
    return null
  }

  const blocks = text.split(/\n{2,}/)

  return (
    <div className={`flex flex-col gap-[12px] ${className}`.trim()}>
      {blocks.map((block, index) => {
        const trimmed = block.trim()
        if (!trimmed) return null

        const headingMatch = trimmed.match(/^#{1,3}\s+(.+)$/m)
        if (headingMatch && !trimmed.includes('\n')) {
          return (
            <p key={index} className="m-0 text-[14px] font-semibold leading-[18px] text-ce-black">
              {inlineBold(headingMatch[1])}
            </p>
          )
        }

        return (
          <p key={index} className="m-0 whitespace-pre-line text-[14px] leading-[18px] text-ce-black">
            {inlineBold(trimmed.replace(/^#{1,3}\s+/gm, ''))}
          </p>
        )
      })}
    </div>
  )
}

function inlineBold(text: string): React.ReactNode {
  const parts = text.split(/(\*\*[^*]+\*\*)/g)
  return parts.map((part, i) => {
    if (part.startsWith('**') && part.endsWith('**')) {
      return (
        <strong key={i} className="font-semibold">
          {part.slice(2, -2)}
        </strong>
      )
    }
    return part
  })
}

/** Horizontale scheidingslijn — 1px beige door de hele app. */
export const CE_DIVIDER_TOP = 'border-t border-ce-beige'
export const CE_DIVIDER_BOTTOM = 'border-b border-ce-beige'
export const CE_DIVIDER_Y = 'border-y border-ce-beige'

type HorizontalRuleProps = {
  className?: string
}

export function HorizontalRule({ className = '' }: HorizontalRuleProps) {
  return (
    <div className={`py-[10px] ${className}`.trim()} role="presentation">
      <hr className={`m-0 border-0 ${CE_DIVIDER_TOP}`} />
    </div>
  )
}

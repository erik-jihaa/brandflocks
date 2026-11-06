type ArrowDirection = 'up' | 'down' | 'right'

const rotation: Record<ArrowDirection, string> = {
  up: '-rotate-90',
  down: 'rotate-90',
  right: 'rotate-0',
}

type ArrowIconProps = {
  direction?: ArrowDirection
  className?: string
}

export function ArrowIcon({ direction = 'right', className = '' }: ArrowIconProps) {
  return (
    <img
      src="/svg/arrow.svg"
      alt=""
      width={15}
      height={13}
      className={`inline-block shrink-0 ${rotation[direction]} ${className}`}
      aria-hidden
    />
  )
}

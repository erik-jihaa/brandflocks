import { Link, type LinkProps } from 'react-router-dom'
import { pageNavLinkClass } from '../../design/identity.constants'

type PageNavLinkProps = LinkProps & {
  /** Pijl achter label (default aan) */
  showArrow?: boolean
}

/** URL naar andere pagina — blauw + pijl (licht) / groen + pijl (Account-dark), geen underline. */
export function PageNavLink({
  className = '',
  children,
  showArrow = true,
  ...props
}: PageNavLinkProps) {
  return (
    <Link className={`${pageNavLinkClass} ${className}`.trim()} {...props}>
      {children}
      {showArrow ? ' →' : null}
    </Link>
  )
}

import { Link, type LinkProps } from 'react-router-dom'
import { hyperlinkClassBody } from '../../design/identity.constants'

/** iGiSNext identity — nav/hulp-links (Forgot password, Terug naar login) */
export const TEXT_LINK_CLASS = hyperlinkClassBody

export function TextLink({ className = '', ...props }: LinkProps) {
  return <Link className={`${TEXT_LINK_CLASS} ${className}`.trim()} {...props} />
}

/** Standaard merklabels (ronde E). Server mag overriden via /health.branding. */
export type ProductBranding = {
  productName: string
  siteTitle: string
  stagingLabel: string
  platformLabel: string
}

export const DEFAULT_PRODUCT_BRANDING: ProductBranding = {
  productName: 'Brandflocks',
  siteTitle: 'Brandflocks',
  stagingLabel: 'Brandflocks staging',
  platformLabel: 'Brandflocks platform',
}

export function parseProductBranding(raw: unknown): ProductBranding {
  if (!raw || typeof raw !== 'object') {
    return DEFAULT_PRODUCT_BRANDING
  }
  const row = raw as Record<string, unknown>
  const productName =
    typeof row.productName === 'string' && row.productName.trim() !== ''
      ? row.productName.trim()
      : DEFAULT_PRODUCT_BRANDING.productName

  return {
    productName,
    siteTitle:
      typeof row.siteTitle === 'string' && row.siteTitle.trim() !== ''
        ? row.siteTitle.trim()
        : productName,
    stagingLabel:
      typeof row.stagingLabel === 'string' && row.stagingLabel.trim() !== ''
        ? row.stagingLabel.trim()
        : `${productName} staging`,
    platformLabel:
      typeof row.platformLabel === 'string' && row.platformLabel.trim() !== ''
        ? row.platformLabel.trim()
        : `${productName} platform`,
  }
}

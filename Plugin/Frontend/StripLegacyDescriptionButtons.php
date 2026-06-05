<?php
declare(strict_types=1);

namespace ETechFlow\VariantLinks\Plugin\Frontend;

use ETechFlow\VariantLinks\Model\Config;
use ETechFlow\VariantLinks\Model\LegacyButtonStripper;
use Magento\Catalog\Helper\Output;

/**
 * Removes legacy hand-coded "View Other …" / button-styled anchors from the
 * description and short_description as they render. Both Luma and Hyvä route
 * those attributes through Output::productAttribute(), so this single seam works
 * on every theme without touching templates. The stored description is untouched
 * (only the rendered HTML is cleaned), so the change is fully reversible. Gated
 * by the `buttons/strip_legacy` config flag.
 */
class StripLegacyDescriptionButtons
{
    private const ATTRIBUTES = ['description', 'short_description'];

    public function __construct(
        private readonly LegacyButtonStripper $stripper,
        private readonly Config $config
    ) {
    }

    /**
     * @param Output $subject
     * @param string $result
     * @param mixed  $product
     * @param string $attributeHtml
     * @param string $attributeName
     * @return string
     */
    public function afterProductAttribute(
        Output $subject,
        $result,
        $product,
        $attributeHtml,
        $attributeName
    ) {
        if (!in_array($attributeName, self::ATTRIBUTES, true)) {
            return $result;
        }
        if (!$this->config->isLegacyStrippingEnabled()) {
            return $result;
        }
        return $this->stripper->strip((string) $result);
    }
}

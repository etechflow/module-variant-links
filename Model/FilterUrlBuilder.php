<?php
declare(strict_types=1);

namespace ETechFlow\VariantLinks\Model;

use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Turns a stored relative/legacy link (e.g. "/euro-cylinders?manufacturer=2081",
 * or a full URL on the WRONG domain) into a correct absolute URL on THIS store's
 * base URL. The domain is therefore always fixed automatically.
 *
 * This is the single seam the future SEO module hooks into: an around-plugin on
 * build() can rewrite ID-based query params (?manufacturer=2081) into label-based
 * ones (?manufacturer=Yale) without changing anything else.
 */
class FilterUrlBuilder
{
    public function __construct(
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    public function build(string $stored): string
    {
        $stored = trim($stored);
        if ($stored === '') {
            return '';
        }

        // Keep only path + query, discarding any scheme/host (incl. a wrong domain).
        $path = $stored;
        if (preg_match('~^https?://[^/]+(/.*)$~i', $stored, $m)) {
            $path = $m[1];
        }
        $path = ltrim($path, '/');

        $base = rtrim($this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_LINK), '/');

        return $path === '' ? $base . '/' : $base . '/' . $path;
    }
}

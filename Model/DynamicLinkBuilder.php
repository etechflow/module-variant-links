<?php
declare(strict_types=1);

namespace ETechFlow\VariantLinks\Model;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Builds a "View Other …" relative URL on the fly from a product's own primary
 * category + Manufacturer / Finish / Size, used when no manual variant_*_url
 * override is stored. Gated by config (dynamic/enabled).
 *
 *   Options  -> /{category}?manufacturer={mfr}                  (this brand's range in the category)
 *   Finishes -> /{category}?manufacturer={mfr}[&{size}={val}]   (vary finish; only if the product has a finish)
 *   Sizes    -> /{category}?manufacturer={mfr}[&finish={val}]   (vary size; only if the category has a mapped size attr)
 *
 * buildCustom() serves admin-defined buttons: hold any set of attributes fixed.
 */
class DynamicLinkBuilder
{
    /** @var array<string,string> category-ids key => path */
    private array $catCache = [];

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config
    ) {
    }

    public function build(ProductInterface $product, string $buttonCode): string
    {
        $cat = $this->primaryCategoryPath($product);
        if ($cat === '') {
            return '';
        }
        $mfr    = trim((string) $product->getData('manufacturer'));
        $finish = trim((string) $product->getData('finish'));

        $params = [];
        if ($mfr !== '') {
            $params['manufacturer'] = $mfr;
        }

        if ($buttonCode === 'variant_finishes_url') {
            if ($finish === '') {
                return ''; // no finish -> nothing to vary
            }
            $sizeAttr = $this->config->getSizeAttribute($cat);
            if ($sizeAttr !== '') {
                $sizeVal = trim((string) $product->getData($sizeAttr));
                if ($sizeVal !== '') {
                    $params[$sizeAttr] = $sizeVal; // hold size fixed so only finish varies
                }
            }
        } elseif ($buttonCode === 'variant_sizes_url') {
            $sizeAttr = $this->config->getSizeAttribute($cat);
            if ($sizeAttr === '') {
                return ''; // no size dimension configured for this category
            }
            $sizeVal = trim((string) $product->getData($sizeAttr));
            if ($sizeVal === '') {
                return '';
            }
            if ($finish !== '') {
                $params['finish'] = $finish; // hold finish fixed so only size varies
            }
        } elseif ($buttonCode !== 'variant_options_url') {
            return '';
        }

        $query = $params ? '?' . http_build_query($params) : '';
        return '/' . $cat . $query;
    }

    /**
     * Admin-defined button: pin the given attribute codes to this product's
     * own values (so everything else in the same category varies). `__size__`
     * resolves to the per-category size attribute. Returns '' (button hidden)
     * when the category or any held attribute value is missing.
     *
     * @param string[] $holdCodes
     */
    public function buildCustom(ProductInterface $product, array $holdCodes): string
    {
        $cat = $this->primaryCategoryPath($product);
        if ($cat === '') {
            return '';
        }
        $params = [];
        foreach ($holdCodes as $code) {
            $code = trim($code);
            if ($code === '') {
                continue;
            }
            if ($code === '__size__') {
                $code = $this->config->getSizeAttribute($cat);
                if ($code === '') {
                    return ''; // category has no size dimension to hold
                }
            }
            $val = trim((string) $product->getData($code));
            if ($val === '') {
                return ''; // a held attribute has no value -> button not meaningful
            }
            $params[$code] = $val;
        }
        $query = $params ? '?' . http_build_query($params) : '';
        return '/' . $cat . $query;
    }

    /** Deepest-level assigned category that has a clean URL. */
    private function primaryCategoryPath(ProductInterface $product): string
    {
        $ids = $product->getCategoryIds();
        if (!$ids) {
            return '';
        }
        sort($ids);
        $key = implode(',', $ids);
        if (array_key_exists($key, $this->catCache)) {
            return $this->catCache[$key];
        }
        $conn = $this->resource->getConnection();
        $frontStoreId = 0;
        try {
            $d = $this->storeManager->getDefaultStoreView();
            if ($d) {
                $frontStoreId = (int) $d->getId();
            }
        } catch (\Throwable $e) {
            $frontStoreId = 0;
        }

        $select = $conn->select()
            ->from(['ce' => $this->resource->getTableName('catalog_category_entity')], [])
            ->join(
                ['r' => $this->resource->getTableName('url_rewrite')],
                "r.entity_id = ce.entity_id AND r.entity_type = 'category' AND r.redirect_type = 0",
                ['request_path']
            )
            ->where('ce.entity_id IN (?)', $ids)
            ->where('r.store_id IN (?)', array_values(array_unique([0, $frontStoreId])))
            ->where('r.request_path NOT LIKE ?', '%?%')
            ->order('ce.level DESC')
            ->limit(1);

        $path = trim((string) $conn->fetchOne($select), '/');
        return $this->catCache[$key] = $path;
    }
}

<?php
declare(strict_types=1);

namespace ETechFlow\VariantLinks\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Maps category request paths (as stored in the variant_*_url relative URLs,
 * e.g. "euro-double-cylinders") to readable labels, using url_rewrite +
 * category names. Cached per request.
 */
class CategoryResolver
{
    private ?array $paths = null;

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /** @return array<string,string> request_path => "Category Name (request_path)" */
    public function getAllPaths(): array
    {
        if ($this->paths !== null) {
            return $this->paths;
        }
        $conn = $this->resource->getConnection();
        $rewrite = $this->resource->getTableName('url_rewrite');
        $cv      = $this->resource->getTableName('catalog_category_entity_varchar');
        $eav     = $this->resource->getTableName('eav_attribute');

        $nameAttrId = (int) $conn->fetchOne(
            "SELECT attribute_id FROM {$eav} WHERE entity_type_id=3 AND attribute_code='name'"
        );

        // Use the default FRONTEND store view — category URL rewrites live there
        // (store 1), never under the admin store (0), else this is empty in admin.
        $frontStoreId = 0;
        try {
            $default = $this->storeManager->getDefaultStoreView();
            if ($default) {
                $frontStoreId = (int) $default->getId();
            }
        } catch (\Throwable $e) {
            $frontStoreId = 0;
        }

        // category request paths (no redirects), store 0 + default frontend view
        $select = $conn->select()
            ->from(['r' => $rewrite], ['request_path', 'entity_id'])
            ->where('r.entity_type = ?', 'category')
            ->where('r.redirect_type = 0')
            ->where('r.store_id IN (?)', array_values(array_unique([0, $frontStoreId])));
        $rows = $conn->fetchAll($select);

        $idName = [];
        $catIds = array_values(array_unique(array_map(static fn($r) => (int) $r['entity_id'], $rows)));
        if ($catIds) {
            $nameSel = $conn->select()
                ->from($cv, ['entity_id', 'value'])
                ->where('attribute_id = ?', $nameAttrId)
                ->where('store_id IN (?)', [0])
                ->where('entity_id IN (?)', $catIds);
            foreach ($conn->fetchAll($nameSel) as $n) {
                $idName[(int) $n['entity_id']] = (string) $n['value'];
            }
        }

        $out = [];
        foreach ($rows as $r) {
            $path = trim((string) $r['request_path'], '/');
            if ($path === '' || str_contains($path, '?')) {
                continue;
            }
            $name = $idName[(int) $r['entity_id']] ?? $path;
            $out[$path] = $name . '  (/' . $path . ')';
        }
        ksort($out);
        return $this->paths = $out;
    }
}

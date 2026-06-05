<?php
declare(strict_types=1);

namespace ETechFlow\VariantLinks\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\App\ResourceConnection;

/** Filterable, option-based product attributes (value = attribute_code). */
class FilterAttributes implements OptionSourceInterface
{
    private ?array $cache = null;

    public function __construct(private readonly ResourceConnection $resource)
    {
    }

    public function toOptionArray(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }
        $conn = $this->resource->getConnection();
        $ea  = $this->resource->getTableName('eav_attribute');
        $cea = $this->resource->getTableName('catalog_eav_attribute');
        $rows = $conn->fetchAll(
            "SELECT ea.attribute_code, ea.frontend_label
             FROM {$cea} c JOIN {$ea} ea ON ea.attribute_id=c.attribute_id
             WHERE ea.entity_type_id=4 AND c.is_filterable IN (1,2)
               AND ea.frontend_input IN ('select','multiselect','swatch_visual','swatch_text')
             ORDER BY ea.frontend_label"
        );
        $options = [['value' => '', 'label' => __('— attribute —')]];
        foreach ($rows as $r) {
            $label = trim((string) $r['frontend_label']) ?: (string) $r['attribute_code'];
            $options[] = ['value' => (string) $r['attribute_code'], 'label' => $label . ' [' . $r['attribute_code'] . ']'];
        }
        return $this->cache = $options;
    }
}

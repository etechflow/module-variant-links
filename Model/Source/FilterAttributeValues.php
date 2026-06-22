<?php
declare(strict_types=1);

namespace ETechFlow\VariantLinks\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\App\ResourceConnection;

/**
 * All option values across filterable attributes, each tagged with its
 * attribute_code so the value <select> can filterBy the chosen attribute
 * (native UI-component dependent select; no AJAX).
 */
class FilterAttributeValues implements OptionSourceInterface
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
        $o   = $this->resource->getTableName('eav_attribute_option');
        $ov  = $this->resource->getTableName('eav_attribute_option_value');

        $rows = $conn->fetchAll(
            "SELECT ea.attribute_code, o.option_id, ov.value
             FROM {$cea} c
             JOIN {$ea} ea ON ea.attribute_id=c.attribute_id
             JOIN {$o} o   ON o.attribute_id=c.attribute_id
             JOIN {$ov} ov ON ov.option_id=o.option_id AND ov.store_id=0
             WHERE ea.entity_type_id=4 AND c.is_filterable IN (1,2)
               AND ea.frontend_input IN ('select','multiselect','swatch_visual','swatch_text')
             ORDER BY ea.attribute_code, ov.value"
        );
        $options = [];
        foreach ($rows as $r) {
            $options[] = [
                'value'     => (string) $r['option_id'],
                'label'     => (string) $r['value'],
                'attribute' => (string) $r['attribute_code'],
            ];
        }
        return $this->cache = $options;
    }
}

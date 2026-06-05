<?php
declare(strict_types=1);

namespace ETechFlow\VariantLinks\Ui\DataProvider\Product\Form\Modifier;

use Magento\Catalog\Model\Locator\LocatorInterface;
use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\AbstractModifier;
use Magento\Framework\App\ResourceConnection;
use Magento\Ui\Component\Form\Element\DataType\Text;
use Magento\Ui\Component\Form\Element\Select;
use Magento\Ui\Component\Form\Field;
use Magento\Ui\Component\Form\Fieldset;
use ETechFlow\VariantLinks\Model\Source\Categories;
use ETechFlow\VariantLinks\Model\Source\FilterAttributes;

/**
 * "Variant Links" product-form section: per button (Options / Finishes / Sizes)
 * a Target-category dropdown + a fixed set of [Attribute][Value] dropdown rows.
 *
 * Every field is preset via its meta `value` (the reliable method — the same
 * one the category uses), NOT via data-source binding, because custom fields in
 * the product form don't re-bind dynamicRows data here. The save observer reads
 * the submitted fields back and serialises them into variant_*_url.
 */
class VariantLinksBuilder extends AbstractModifier
{
    private const BUTTONS = [
        'variant_options_url'  => 'variant_options',
        'variant_finishes_url' => 'variant_finishes',
        'variant_sizes_url'    => 'variant_sizes',
    ];

    /** how many filter rows to render per button */
    public const ROWS = 5;

    public function __construct(
        private readonly LocatorInterface $locator,
        private readonly Categories $categories,
        private readonly FilterAttributes $attributes,
        private readonly \Magento\Backend\Model\UrlInterface $backendUrl,
        private readonly \ETechFlow\VariantLinks\Model\Config $config,
        private readonly ResourceConnection $resource
    ) {
    }

    public function modifyData(array $data): array
    {
        return $data; // all presets are set via meta `value` in modifyMeta
    }

    public function modifyMeta(array $meta): array
    {
        try {
            $children = [];
            $sort = 10;
            $enabled = $this->config->getEnabledButtonKeys();
            foreach (self::BUTTONS as $attr => $labelKey) {
                if (!in_array($labelKey, $enabled, true)) {
                    continue;
                }
                $children[$this->key($attr) . '_group'] = $this->buttonGroup($attr, $this->config->getLabel($labelKey), $sort);
                $sort += 10;
            }
            $meta['etechflow_variant_links'] = [
                'arguments' => ['data' => ['config' => [
                    'label'         => __('Variant Links — "View Other" buttons'),
                    'componentType' => Fieldset::NAME,
                    'collapsible'   => true,
                    'opened'        => false,
                    'sortOrder'     => 900,
                ]]],
                'children' => $children,
            ];
            $this->pruneRawFields($meta);
        } catch (\Throwable $e) {
            // never break the product form
        }
        return $meta;
    }

    private function pruneRawFields(array &$meta): void
    {
        $codes = ['variant_options_url', 'variant_finishes_url', 'variant_sizes_url'];
        foreach (array_keys($meta) as $topKey) {
            if ($topKey === 'etechflow_variant_links') {
                continue;
            }
            $hadFields = $this->pruneSubtree($meta[$topKey], $codes);
            if ($hadFields && is_array($meta[$topKey]) && empty($meta[$topKey]['children'])) {
                unset($meta[$topKey]);
            }
        }
    }

    private function pruneSubtree(array &$node, array $codes): bool
    {
        if (!isset($node['children']) || !is_array($node['children'])) {
            return false;
        }
        $found = false;
        foreach ($codes as $c) {
            if (isset($node['children'][$c])) {
                unset($node['children'][$c]);
                $found = true;
            }
        }
        foreach ($node['children'] as $childKey => &$child) {
            if (!is_array($child)) {
                continue;
            }
            $childHad = $this->pruneSubtree($child, $codes);
            if ($childHad) {
                $found = true;
                if (empty($child['children']) && $childKey !== 'etechflow_variant_links') {
                    unset($node['children'][$childKey]);
                }
            }
        }
        return $found;
    }

    private function buttonGroup(string $attr, string $label, int $sort): array
    {
        $key = $this->key($attr);
        [$cat, $rows] = $this->parse((string) $this->locator->getProduct()->getData($attr));

        $children = [
            $key . '_category' => ['arguments' => ['data' => ['config' => [
                'componentType' => Field::NAME,
                'formElement'   => Select::NAME,
                'dataType'      => Text::NAME,
                'label'         => __('Target category'),
                'dataScope'     => 'product.' . $key . '_category',
                'options'       => $this->categories->toOptionArray(),
                'value'         => $cat,
                'sortOrder'     => 10,
                'notice'        => __('Leave blank to auto-generate this button from the product category + manufacturer + finish/size. Set a Target category here only to override.'),
            ]]]],
        ];

        for ($i = 0; $i < self::ROWS; $i++) {
            $row     = $rows[$i] ?? null;
            $attrVal = $row['attribute'] ?? '';
            $valVal  = $row['value'] ?? '';
            $children[$key . '_f' . $i] = $this->filterRow($key, $i, $attrVal, $valVal, $sort);
        }

        return [
            'arguments' => ['data' => ['config' => [
                'label'         => __($label),
                'componentType' => Fieldset::NAME,
                'collapsible'   => false,
                'sortOrder'     => $sort,
            ]]],
            'children' => $children,
        ];
    }

    private function filterRow(string $key, int $i, string $attrVal, string $valVal, int $sort): array
    {
        return [
            'arguments' => ['data' => ['config' => [
                'formElement'   => 'container',
                'componentType' => 'container',
                'component'     => 'Magento_Ui/js/form/components/group',
                'breakLine'     => false,
                'label'         => $i === 0 ? __('Filters') : '',
                'sortOrder'     => 20 + $i,
            ]]],
            'children' => [
                'attribute' => ['arguments' => ['data' => ['config' => [
                    'componentType' => Field::NAME,
                    'formElement'   => Select::NAME,
                    'dataType'      => Text::NAME,
                    'label'         => __('Attribute'),
                    'dataScope'     => 'product.' . $key . '_f' . $i . '_attr',
                    'options'       => $this->attributes->toOptionArray(),
                    'value'         => $attrVal,
                    'caption'       => __('— attribute —'),
                    'sortOrder'     => 10,
                ]]]],
                'value' => ['arguments' => ['data' => ['config' => [
                    'componentType' => Field::NAME,
                    'formElement'   => Select::NAME,
                    'component'     => 'ETechFlow_VariantLinks/js/form/element/variant-value-select',
                    'dataType'      => Text::NAME,
                    'label'         => __('Value'),
                    'dataScope'     => 'product.' . $key . '_f' . $i . '_val',
                    'options'       => $attrVal !== '' ? $this->loadValues($attrVal) : [],
                    'value'         => $valVal,
                    'optionsUrl'    => $this->backendUrl->getUrl('etechflow_variantlinks/attribute/options'),
                    'caption'       => __('— value —'),
                    'sortOrder'     => 20,
                ]]]],
            ],
        ];
    }

    /** Option values for one attribute (value=option_id, label=text). */
    private function loadValues(string $code): array
    {
        if ($code === '') {
            return [];
        }
        $conn = $this->resource->getConnection();
        $ea = $this->resource->getTableName('eav_attribute');
        $o  = $this->resource->getTableName('eav_attribute_option');
        $ov = $this->resource->getTableName('eav_attribute_option_value');
        $rows = $conn->fetchAll(
            "SELECT o.option_id AS value, ov.value AS label
             FROM {$ea} ea
             JOIN {$o} o   ON o.attribute_id = ea.attribute_id
             JOIN {$ov} ov ON ov.option_id = o.option_id AND ov.store_id = 0
             WHERE ea.entity_type_id = 4 AND ea.attribute_code = ?
             ORDER BY ov.value",
            [$code]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[] = ['value' => (string) $r['value'], 'label' => (string) $r['label']];
        }
        return $out;
    }

    /** "/cat?a=1&b=2" => ["cat", [["attribute"=>"a","value"=>"1"], ...]] */
    private function parse(string $url): array
    {
        $url = trim($url);
        if ($url === '') {
            return ['', []];
        }
        if (preg_match('~^https?://[^/]+(/.*)$~i', $url, $m)) {
            $url = $m[1];
        }
        $path = ltrim($url, '/');
        $query = '';
        if (str_contains($path, '?')) {
            [$path, $query] = explode('?', $path, 2);
        }
        $rows = [];
        if ($query !== '') {
            parse_str($query, $params);
            foreach ($params as $k => $v) {
                if (in_array($k, ['p', 'q', 'page', 'product_list_order', 'rb_categories'], true)) {
                    continue;
                }
                if (is_array($v)) {
                    $v = reset($v);
                }
                $rows[] = ['attribute' => (string) $k, 'value' => (string) $v];
            }
        }
        return [$path, $rows];
    }

    private function key(string $attr): string
    {
        return str_replace('_url', '', $attr);
    }
}

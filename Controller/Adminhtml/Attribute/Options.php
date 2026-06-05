<?php
declare(strict_types=1);

namespace ETechFlow\VariantLinks\Controller\Adminhtml\Attribute;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\Result\JsonFactory;

/**
 * Returns option values for a single product attribute (admin AJAX), so the
 * "Value" dropdown in the Variant Links builder loads only the chosen
 * attribute's options instead of preloading thousands.
 */
class Options extends Action
{
    public const ADMIN_RESOURCE = 'Magento_Catalog::products';

    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly ResourceConnection $resource
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();
        $code = trim((string) $this->getRequest()->getParam('attribute'));
        if ($code === '') {
            return $result->setData([]);
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
             WHERE ea.entity_type_id = 4 AND ea.attribute_code = :code
             ORDER BY ov.value",
            ['code' => $code]
        );

        $options = [];
        foreach ($rows as $r) {
            $options[] = ['value' => (string) $r['value'], 'label' => (string) $r['label']];
        }
        return $result->setData($options);
    }
}

<?php
declare(strict_types=1);

namespace ETechFlow\VariantLinks\Controller\Adminhtml\Import;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;

class Template extends Action
{
    public const ADMIN_RESOURCE = 'Magento_Catalog::products';

    public function __construct(
        Context $context,
        private readonly FileFactory $fileFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $csv = "sku,variant_options_url,variant_finishes_url,variant_sizes_url\n"
            . "EXAMPLE-SKU-1,/euro-double-cylinders-locks?manufacturer=2270,,\n"
            . "EXAMPLE-SKU-2,,/euro-double-cylinders-locks?manufacturer=2270&sizes=7408,/euro-double-cylinders-locks?manufacturer=2270&finish=4430\n";
        return $this->fileFactory->create(
            'variant-links-template.csv',
            $csv,
            DirectoryList::VAR_DIR,
            'text/csv'
        );
    }
}

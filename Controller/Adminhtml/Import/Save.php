<?php
declare(strict_types=1);

namespace ETechFlow\VariantLinks\Controller\Adminhtml\Import;

use ETechFlow\VariantLinks\Model\BulkImporter;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Magento_Catalog::products';

    public function __construct(
        Context $context,
        private readonly BulkImporter $importer
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create()->setPath('etechflow_variantlinks/import/index');
        $file = $this->getRequest()->getFiles('import_file');
        if (empty($file['tmp_name']) || !empty($file['error']) || !is_uploaded_file($file['tmp_name'])) {
            $this->messageManager->addErrorMessage(__('Please choose a CSV file to upload.'));
            return $redirect;
        }
        $dry = (bool) $this->getRequest()->getParam('dry_run');

        try {
            $r = $this->importer->importFile($file['tmp_name'], $dry);
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('Import failed: %1', $e->getMessage()));
            return $redirect;
        }

        if (!empty($r['error'])) {
            $this->messageManager->addErrorMessage(__($r['error']));
            return $redirect;
        }

        $verb = $dry ? __('would be written') : __('written');
        $this->messageManager->addSuccessMessage(__(
            '%1: %2 data rows, %3 products matched, %4 link values %5.',
            $dry ? __('Dry run (nothing saved)') : __('Import complete'),
            $r['rows'], $r['matched'], $r['written'], $verb
        ));
        if (!empty($r['skipped'])) {
            $n = count($r['skipped']);
            $sample = implode(', ', array_slice($r['skipped'], 0, 20));
            $this->messageManager->addWarningMessage(__(
                '%1 SKU(s) not found and skipped: %2%3', $n, $sample, $n > 20 ? ' …' : ''
            ));
        }
        return $redirect;
    }
}

<?php

declare(strict_types=1);

namespace ETechFlow\VariantLinks\Controller\Adminhtml\License;

use ETechFlow\VariantLinks\Model\LicenseValidator;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

/**
 * License-required gate page. Shows plan cards + "Enter License Key".
 * Redirects to the Bulk Import page when the licence is already valid.
 */
class Gate extends Action
{
    public const ADMIN_RESOURCE = 'Magento_Catalog::config_catalog';

    public function __construct(
        Context $context,
        private readonly PageFactory $pageFactory,
        private readonly LicenseValidator $licenseValidator
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        if ($this->licenseValidator->isValid()) {
            $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
            return $redirect->setPath('etechflow_variantlinks/import/index');
        }

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->prepend(__('Variant Links — License Required'));
        return $page;
    }
}

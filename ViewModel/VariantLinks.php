<?php
declare(strict_types=1);

namespace ETechFlow\VariantLinks\ViewModel;

use ETechFlow\VariantLinks\Model\Config;
use ETechFlow\VariantLinks\Model\DynamicLinkBuilder;
use ETechFlow\VariantLinks\Model\FilterUrlBuilder;
use ETechFlow\VariantLinks\Model\LegacyButtonStripper;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class VariantLinks implements ArgumentInterface
{
    /** attribute_code => config label key */
    private const BUTTONS = [
        'variant_options_url'  => 'variant_options',
        'variant_finishes_url' => 'variant_finishes',
        'variant_sizes_url'    => 'variant_sizes',
    ];

    public function __construct(
        private readonly FilterUrlBuilder $urlBuilder,
        private readonly LegacyButtonStripper $stripper,
        private readonly Config $config,
        private readonly DynamicLinkBuilder $dynamic,
        private readonly Registry $registry
    ) {
    }

    /**
     * The product currently being viewed on the PDP, so the storefront template
     * needs no Block of its own — Magento\Catalog registers `current_product` on
     * every product page in both Luma and Hyvä. Returns null off a product page.
     */
    public function getCurrentProduct(): ?ProductInterface
    {
        $product = $this->registry->registry('current_product');
        return $product instanceof ProductInterface ? $product : null;
    }

    /**
     * Built-in buttons (only those ticked in "Buttons to show"; stored override
     * wins, else dynamic), then any admin-defined custom buttons.
     *
     * @return array<int, array{label:string, url:string}>
     */
    public function getButtons(ProductInterface $product): array
    {
        $buttons = [];
        $dynamicOn = $this->config->isDynamicEnabled();
        $enabled = $this->config->getEnabledButtonKeys();
        foreach (self::BUTTONS as $code => $labelKey) {
            if (!in_array($labelKey, $enabled, true)) {
                continue;
            }
            $value = trim((string) $product->getData($code));
            if ($value === '' && $dynamicOn) {
                $value = $this->dynamic->build($product, $code);
            }
            if ($value === '') {
                continue;
            }
            $url = $this->urlBuilder->build($value);
            if ($url === '') {
                continue;
            }
            $buttons[] = ['label' => $this->config->getLabel($labelKey), 'url' => $url];
        }

        // admin-defined custom buttons (always active when defined)
        foreach ($this->config->getCustomButtons() as $def) {
            $value = $this->dynamic->buildCustom($product, $def['hold']);
            if ($value === '') {
                continue;
            }
            $url = $this->urlBuilder->build($value);
            if ($url === '') {
                continue;
            }
            $buttons[] = ['label' => $def['label'], 'url' => $url];
        }

        return $buttons;
    }

    public function cleanDescription(?string $html): string
    {
        return $this->stripper->strip($html);
    }
}

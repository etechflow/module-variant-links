<?php
declare(strict_types=1);

namespace ETechFlow\VariantLinks\Setup\Patch\Data;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Adds three per-product "View Other …" link attributes. Each stores a RELATIVE
 * URL (path + filter query), e.g. "/euro-cylinders?manufacturer=2081". The
 * frontend prepends the correct base URL, so the domain is always right and the
 * value is editable in admin + importable via the standard product CSV (Excel).
 * Blank value = button hidden for that product.
 */
class AddVariantLinkAttributes implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory
    ) {
    }

    public function apply(): self
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);

        $attributes = [
            'variant_options_url'  => 'View Other Options — link',
            'variant_finishes_url' => 'View Other Finishes — link',
            'variant_sizes_url'    => 'View Other Sizes — link',
        ];

        foreach ($attributes as $code => $label) {
            if ($eavSetup->getAttributeId(Product::ENTITY, $code)) {
                continue;
            }
            $eavSetup->addAttribute(Product::ENTITY, $code, [
                'type'                    => 'varchar',
                'label'                   => $label,
                'input'                   => 'text',
                'required'                => false,
                'global'                  => ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible'                 => true,
                'user_defined'            => true,
                'searchable'              => false,
                'filterable'              => false,
                'comparable'              => false,
                'visible_on_front'        => false,
                'used_in_product_listing' => false,
                'is_used_in_grid'         => false,
                'group'                   => 'Variant Links',
                'note'                    => 'Relative URL (path + filters), e.g. /euro-cylinders?manufacturer=2081. Blank = hide this button. Domain added automatically.',
            ]);
        }

        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}

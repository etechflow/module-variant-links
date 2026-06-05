<?php
declare(strict_types=1);

namespace ETechFlow\VariantLinks\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class EnabledButtons implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'variant_finishes', 'label' => __('View Other Finishes')],
            ['value' => 'variant_sizes',    'label' => __('View Other Sizes')],
            ['value' => 'variant_options',  'label' => __('View Other Options')],
        ];
    }
}

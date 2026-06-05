<?php
declare(strict_types=1);

namespace ETechFlow\VariantLinks\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;
use ETechFlow\VariantLinks\Model\CategoryResolver;

/**
 * Category dropdown source for the Variant Links builder.
 * value = category url request path (e.g. "euro-double-cylinders"), so it maps
 * directly to the relative URL we store; label = readable name + path.
 */
class Categories implements OptionSourceInterface
{
    private ?array $cache = null;

    public function __construct(
        private readonly CategoryResolver $resolver
    ) {
    }

    public function toOptionArray(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }
        $options = [['value' => '', 'label' => __('— Select category —')]];
        foreach ($this->resolver->getAllPaths() as $path => $label) {
            $options[] = ['value' => $path, 'label' => $label];
        }
        return $this->cache = $options;
    }
}

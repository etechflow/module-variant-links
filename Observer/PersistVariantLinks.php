<?php
declare(strict_types=1);

namespace ETechFlow\VariantLinks\Observer;

use ETechFlow\VariantLinks\Model\Config;
use ETechFlow\VariantLinks\Ui\DataProvider\Product\Form\Modifier\VariantLinksBuilder;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;

/**
 * On product save from the admin form, read the builder fields
 * (variant_*_category + variant_*_f{i}_attr/_val) and serialise them into the
 * variant_*_url relative URL. Guarded by hasData(category) so import/API saves
 * that set variant_*_url directly are untouched.
 *
 * Filters with no Target category can't build a URL -> the save is BLOCKED with
 * a clear error (the aborted save keeps the entered values on screen).
 */
class PersistVariantLinks implements ObserverInterface
{
    private const MAP = [
        'variant_options'  => 'variant_options_url',
        'variant_finishes' => 'variant_finishes_url',
        'variant_sizes'    => 'variant_sizes_url',
    ];

    public function __construct(
        private readonly ManagerInterface $messageManager,
        private readonly Config $config
    ) {
    }

    public function execute(Observer $observer): void
    {
        $product = $observer->getEvent()->getProduct();
        if (!$product) {
            return;
        }

        foreach (self::MAP as $key => $attr) {
            if (!$product->hasData($key . '_category')) {
                continue; // builder not in this save — leave the attribute as-is
            }
            $cat = trim((string) $product->getData($key . '_category'), "/ \t\n");

            $pairs = [];
            $hasAnyFilterInput = false;
            for ($i = 0; $i < VariantLinksBuilder::ROWS; $i++) {
                $a = trim((string) $product->getData($key . '_f' . $i . '_attr'));
                $v = trim((string) $product->getData($key . '_f' . $i . '_val'));
                if ($a !== '' || $v !== '') {
                    $hasAnyFilterInput = true;
                }
                if ($a !== '' && $v !== '') {
                    $pairs[] = $a . '=' . $v;
                }
                $product->unsetData($key . '_f' . $i . '_attr');
                $product->unsetData($key . '_f' . $i . '_val');
            }

            if ($cat === '' && $hasAnyFilterInput) {
                throw new LocalizedException(
                    __(
                        'Variant Links — “%1”: please pick a Target category. You added '
                        . 'filter(s) but no category, and a filter needs a category to point to. '
                        . 'Select a Target category (or clear the filters), then save again.',
                        $this->config->getLabel($key)
                    )
                );
            }

            $url = $cat !== '' ? '/' . $cat . ($pairs ? '?' . implode('&', $pairs) : '') : '';

            $product->setData($attr, $url);
            $product->unsetData($key . '_category');
        }
    }
}

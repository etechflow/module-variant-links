<?php
declare(strict_types=1);

namespace ETechFlow\VariantLinks\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Per-store config for the "View Other" buttons: which show, their labels, the
 * dynamic (auto-generated) link settings, and admin-defined custom buttons.
 *
 * isLicensed() is the master licence gate — the storefront ViewModel + the
 * legacy-strip plugin both check it, so a valid licence is ALWAYS required for
 * the module to do anything on the storefront.
 */
class Config
{
    private const XML_PATH = [
        'variant_options'  => 'etechflow_variantlinks/buttons/options_label',
        'variant_finishes' => 'etechflow_variantlinks/buttons/finishes_label',
        'variant_sizes'    => 'etechflow_variantlinks/buttons/sizes_label',
    ];
    private const DEFAULTS = [
        'variant_options'  => 'View Other Options',
        'variant_finishes' => 'View Other Finishes',
        'variant_sizes'    => 'View Other Sizes',
    ];
    private const XML_ENABLED_BUTTONS  = 'etechflow_variantlinks/buttons/enabled';
    private const XML_STRIP_LEGACY      = 'etechflow_variantlinks/buttons/strip_legacy';
    private const XML_DYNAMIC_ENABLED   = 'etechflow_variantlinks/dynamic/enabled';
    private const XML_DYNAMIC_SIZE_MAP  = 'etechflow_variantlinks/dynamic/size_attribute_map';
    private const XML_DYNAMIC_SIZE_DEF  = 'etechflow_variantlinks/dynamic/default_size_attribute';
    private const XML_CUSTOM_BUTTONS    = 'etechflow_variantlinks/custom_buttons/definitions';

    /** @var array<string,string>|null */
    private ?array $sizeMap = null;

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LicenseValidator $licenseValidator
    ) {
    }

    /**
     * Master licence gate. A valid licence is ALWAYS required — no environment
     * toggle, no host bypass. The storefront features are inert without it.
     */
    public function isLicensed(): bool
    {
        return $this->licenseValidator->isValid();
    }

    public function getLabel(string $key, $store = null): string
    {
        if (!isset(self::XML_PATH[$key])) {
            return '';
        }
        $value = trim((string) $this->scopeConfig->getValue(self::XML_PATH[$key], ScopeInterface::SCOPE_STORE, $store));
        return $value !== '' ? $value : (self::DEFAULTS[$key] ?? '');
    }

    /** Which built-in button keys are enabled (storefront + admin builder). */
    public function getEnabledButtonKeys($store = null): array
    {
        $raw = (string) $this->scopeConfig->getValue(self::XML_ENABLED_BUTTONS, ScopeInterface::SCOPE_STORE, $store);
        $keys = array_values(array_filter(array_map('trim', explode(',', $raw))));
        return $keys ?: ['variant_finishes', 'variant_sizes'];
    }

    public function isDynamicEnabled($store = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_DYNAMIC_ENABLED, ScopeInterface::SCOPE_STORE, $store);
    }

    /** Whether to strip legacy hand-coded "View Other …" anchors from descriptions at render time. */
    public function isLegacyStrippingEnabled($store = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_STRIP_LEGACY, ScopeInterface::SCOPE_STORE, $store);
    }

    public function getSizeAttribute(string $catPath, $store = null): string
    {
        if ($this->sizeMap === null) {
            $this->sizeMap = [];
            $raw = (string) $this->scopeConfig->getValue(self::XML_DYNAMIC_SIZE_MAP, ScopeInterface::SCOPE_STORE, $store);
            foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || !str_contains($line, '=')) {
                    continue;
                }
                [$c, $a] = explode('=', $line, 2);
                $c = trim($c, "/ \t");
                $a = trim($a);
                if ($c !== '' && $a !== '') {
                    $this->sizeMap[$c] = $a;
                }
            }
        }
        if (!empty($this->sizeMap[$catPath])) {
            return $this->sizeMap[$catPath];
        }
        return trim((string) $this->scopeConfig->getValue(self::XML_DYNAMIC_SIZE_DEF, ScopeInterface::SCOPE_STORE, $store));
    }

    /**
     * Admin-defined extra buttons. One per line: "Label|holdAttr1,holdAttr2".
     * Lines starting with # are comments. `__size__` = the per-category size attr.
     *
     * @return array<int, array{key:string, label:string, hold:string[]}>
     */
    public function getCustomButtons($store = null): array
    {
        $raw = (string) $this->scopeConfig->getValue(self::XML_CUSTOM_BUTTONS, ScopeInterface::SCOPE_STORE, $store);
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $parts = explode('|', $line);
            $label = trim($parts[0] ?? '');
            if ($label === '') {
                continue;
            }
            $hold = [];
            if (!empty($parts[1])) {
                foreach (explode(',', $parts[1]) as $h) {
                    $h = trim($h);
                    if ($h !== '') {
                        $hold[] = $h;
                    }
                }
            }
            $out[] = [
                'key'   => 'custom_' . trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($label)), '_'),
                'label' => $label,
                'hold'  => $hold,
            ];
        }
        return $out;
    }
}

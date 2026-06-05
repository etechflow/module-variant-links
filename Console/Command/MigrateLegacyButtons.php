<?php
declare(strict_types=1);

namespace ETechFlow\VariantLinks\Console\Command;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Extracts the legacy hardcoded "VIEW OTHER OPTIONS/FINISHES/SIZES" anchors from
 * product descriptions and stores their (domain-stripped) hrefs into the
 * variant_*_url attributes. Descriptions are NOT modified. --dry-run reports
 * only.
 */
class MigrateLegacyButtons extends Command
{
    private const LABELS = [
        'variant_options_url'  => 'VIEW OTHER OPTIONS',
        'variant_finishes_url' => 'VIEW OTHER FINISHES',
        'variant_sizes_url'    => 'VIEW OTHER SIZES',
    ];

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly State $state,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('etechflow:variant-links:migrate')
            ->setDescription('Migrate legacy in-description View Other buttons into variant_*_url attributes.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report only; write nothing.')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Process at most N products (testing).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try { $this->state->setAreaCode('adminhtml'); } catch (\Throwable $e) {}
        $dryRun = (bool) $input->getOption('dry-run');
        $limit  = $input->getOption('limit') !== null ? (int) $input->getOption('limit') : 0;

        $conn = $this->resource->getConnection();
        $eav  = $this->resource->getTableName('eav_attribute');
        $cpet = $this->resource->getTableName('catalog_product_entity_text');
        $cpev = $this->resource->getTableName('catalog_product_entity_varchar');
        $idCol = $conn->tableColumnExists($cpet, 'row_id') ? 'row_id' : 'entity_id';

        $descIds = $conn->fetchCol(
            "SELECT attribute_id FROM {$eav} WHERE entity_type_id=4 AND attribute_code IN ('description','short_description')"
        );
        $targetIds = [];
        foreach (array_keys(self::LABELS) as $code) {
            $targetIds[$code] = (int) $conn->fetchOne(
                "SELECT attribute_id FROM {$eav} WHERE entity_type_id=4 AND attribute_code=?",
                [$code]
            );
        }

        $select = $conn->select()
            ->from($cpet, [$idCol, 'value'])
            ->where('attribute_id IN (?)', $descIds)
            ->where('value LIKE ?', '%VIEW OTHER%');
        $rows = $conn->fetchAll($select);

        $perEntity = [];
        foreach ($rows as $r) {
            $id = (int) $r[$idCol];
            $html = (string) $r['value'];
            foreach (self::LABELS as $code => $label) {
                if (isset($perEntity[$id][$code])) {
                    continue;
                }
                $rel = $this->extractRelative($html, $label);
                if ($rel !== null) {
                    $perEntity[$id][$code] = $rel;
                }
            }
        }

        if ($limit > 0) {
            $perEntity = array_slice($perEntity, 0, $limit, true);
        }

        $counts = array_fill_keys(array_keys(self::LABELS), 0);
        foreach ($perEntity as $vals) {
            foreach ($vals as $code => $v) {
                $counts[$code]++;
            }
        }

        $output->writeln(sprintf('Products with >=1 legacy button: <info>%d</info>', count($perEntity)));
        foreach ($counts as $code => $c) {
            $output->writeln(sprintf('  %-22s %d', $code, $c));
        }
        $output->writeln('Sample (first 10):');
        $n = 0;
        foreach ($perEntity as $id => $vals) {
            $output->writeln("  #{$id}  " . json_encode($vals, JSON_UNESCAPED_SLASHES));
            if (++$n >= 10) {
                break;
            }
        }

        if ($dryRun) {
            $output->writeln('<comment>DRY RUN — nothing written.</comment>');
            return Command::SUCCESS;
        }

        $written = 0;
        foreach ($perEntity as $id => $vals) {
            foreach ($vals as $code => $rel) {
                if (!$targetIds[$code]) {
                    continue;
                }
                $conn->insertOnDuplicate($cpev, [
                    'attribute_id' => $targetIds[$code],
                    'store_id'     => 0,
                    $idCol         => $id,
                    'value'        => $rel,
                ], ['value']);
                $written++;
            }
        }
        $output->writeln(sprintf('<info>Wrote %d values across %d products.</info>', $written, count($perEntity)));
        return Command::SUCCESS;
    }

    private function extractRelative(string $html, string $label): ?string
    {
        $labelPat = preg_replace('~\s+~', '\\\\s+', preg_quote($label, '~'));
        if (!preg_match('~<a\b([^>]*)>\s*(?:&nbsp;|\s)*' . $labelPat . '(?:&nbsp;|\s)*\s*</a>~is', $html, $m)) {
            return null;
        }
        // href may be double-quoted, single-quoted, or UNQUOTED (href=https://...>)
        if (!preg_match('~href\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))~i', $m[1], $h)) {
            return null;
        }
        $raw = '';
        foreach ([1, 2, 3] as $g) {
            if (isset($h[$g]) && $h[$g] !== '') {
                $raw = $h[$g];
                break;
            }
        }
        $href = trim(html_entity_decode($raw, ENT_QUOTES | ENT_HTML5));
        if ($href === '' || stripos($href, 'javascript:') === 0) {
            return null;
        }
        if (preg_match('~^https?://[^/]+(/.*)$~i', $href, $mm)) {
            $href = $mm[1];
        }
        if ($href === '' || $href[0] !== '/') {
            $href = '/' . ltrim($href, '/');
        }
        return $href;
    }
}

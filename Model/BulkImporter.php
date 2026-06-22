<?php
declare(strict_types=1);

namespace ETechFlow\VariantLinks\Model;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\File\Csv;

/**
 * Bulk-applies variant link overrides from a CSV. Columns: sku +
 * variant_options_url / variant_finishes_url / variant_sizes_url (any subset).
 * Empty cell = leave that link unchanged; cell value "__CLEAR__" = blank it.
 * Shared by the admin Bulk Import page and the console command.
 */
class BulkImporter
{
    public const COLUMNS = ['variant_options_url', 'variant_finishes_url', 'variant_sizes_url'];

    public function __construct(
        private readonly ResourceConnection $resource,
        private readonly Csv $csv
    ) {
    }

    /** Read a CSV file (first row = header) and import it. */
    public function importFile(string $path, bool $dryRun): array
    {
        $data = $this->csv->getData($path);
        if (count($data) < 2) {
            return $this->result(0, 0, 0, [], $dryRun, 'The CSV is empty or has no data rows.');
        }
        $header = array_map(static fn($h) => trim((string) $h), array_shift($data));
        if (!in_array('sku', $header, true)) {
            return $this->result(0, 0, 0, [], $dryRun, 'CSV must have a "sku" column. Use the template.');
        }
        $rows = [];
        foreach ($data as $line) {
            $row = [];
            foreach ($header as $i => $h) {
                if ($h !== '') {
                    $row[$h] = $line[$i] ?? '';
                }
            }
            $rows[] = $row;
        }
        return $this->importRows($rows, $dryRun);
    }

    /** @param array<int,array<string,string>> $rows header-keyed rows */
    public function importRows(array $rows, bool $dryRun): array
    {
        $conn = $this->resource->getConnection();
        $cpe  = $this->resource->getTableName('catalog_product_entity');
        $cpev = $this->resource->getTableName('catalog_product_entity_varchar');
        $eav  = $this->resource->getTableName('eav_attribute');
        $idCol = $conn->tableColumnExists($cpev, 'row_id') ? 'row_id' : 'entity_id';

        $attrIds = [];
        foreach (self::COLUMNS as $code) {
            $attrIds[$code] = (int) $conn->fetchOne(
                "SELECT attribute_id FROM {$eav} WHERE entity_type_id = 4 AND attribute_code = ?",
                [$code]
            );
        }

        $skus = [];
        foreach ($rows as $r) {
            $s = trim((string) ($r['sku'] ?? ''));
            if ($s !== '') {
                $skus[$s] = true;
            }
        }
        $skus = array_keys($skus);

        $map = [];
        foreach (array_chunk($skus, 1000) as $chunk) {
            if (!$chunk) {
                continue;
            }
            $pairs = $conn->fetchPairs(
                $conn->select()->from($cpe, ['sku', $idCol])->where('sku IN (?)', $chunk)
            );
            foreach ($pairs as $s => $id) {
                $map[mb_strtolower((string) $s)] = $id;
            }
        }

        $seen = 0; $matched = 0; $written = 0; $skipped = []; $writes = [];
        foreach ($rows as $r) {
            $sku = trim((string) ($r['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            $seen++;
            $key = mb_strtolower($sku);
            if (!isset($map[$key])) {
                $skipped[] = $sku;
                continue;
            }
            $id = $map[$key];
            $matched++;
            foreach (self::COLUMNS as $code) {
                if (!array_key_exists($code, $r) || !$attrIds[$code]) {
                    continue;
                }
                $val = trim((string) $r[$code]);
                if ($val === '') {
                    continue; // leave unchanged
                }
                if (strtoupper($val) === '__CLEAR__') {
                    $val = '';
                }
                $writes[] = [
                    'attribute_id' => $attrIds[$code],
                    'store_id'     => 0,
                    $idCol         => $id,
                    'value'        => $val,
                ];
                $written++;
            }
        }

        if (!$dryRun && $writes) {
            foreach (array_chunk($writes, 500) as $chunk) {
                $conn->insertOnDuplicate($cpev, $chunk, ['value']);
            }
        }

        return $this->result($seen, $matched, $written, $skipped, $dryRun, null);
    }

    private function result(int $rows, int $matched, int $written, array $skipped, bool $dryRun, ?string $error): array
    {
        return [
            'rows' => $rows, 'matched' => $matched, 'written' => $written,
            'skipped' => $skipped, 'dryRun' => $dryRun, 'error' => $error,
        ];
    }
}

<?php
declare(strict_types=1);

namespace ETechFlow\VariantLinks\Console\Command;

use ETechFlow\VariantLinks\Model\BulkImporter;
use ETechFlow\VariantLinks\Model\LicenseValidator;
use Magento\Framework\App\State;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Bulk-import variant link overrides from a CSV (sku + variant_*_url columns).
 *   bin/magento etechflow:variant-links:import path/to/file.csv [--dry-run]
 */
class ImportVariantLinks extends Command
{
    public function __construct(
        private readonly State $state,
        private readonly BulkImporter $importer,
        private readonly LicenseValidator $licenseValidator,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('etechflow:variant-links:import')
            ->setDescription('Bulk-import variant link overrides from a CSV (sku + variant_*_url columns).')
            ->addArgument('file', InputArgument::REQUIRED, 'Path to the CSV file.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview only; write nothing.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!$this->licenseValidator->isValid()) {
            $output->writeln('<error>Variant Links is not licensed for this host. Enter a valid licence key in Stores > Config > eTechFlow > Variant Links.</error>');
            return Command::FAILURE;
        }
        try { $this->state->setAreaCode('adminhtml'); } catch (\Throwable $e) {}
        $file = (string) $input->getArgument('file');
        if (!is_file($file) || !is_readable($file)) {
            $output->writeln("<error>File not found or unreadable: {$file}</error>");
            return Command::FAILURE;
        }
        $dry = (bool) $input->getOption('dry-run');
        $r = $this->importer->importFile($file, $dry);

        if ($r['error']) {
            $output->writeln("<error>{$r['error']}</error>");
            return Command::FAILURE;
        }
        $verb = $dry ? 'would be written' : 'written';
        $output->writeln(sprintf(
            '%s: <info>%d</info> data rows, <info>%d</info> products matched, <info>%d</info> link values %s.',
            $dry ? 'DRY RUN' : 'Import', $r['rows'], $r['matched'], $r['written'], $verb
        ));
        if ($r['skipped']) {
            $n = count($r['skipped']);
            $sample = implode(', ', array_slice($r['skipped'], 0, 20));
            $output->writeln("<comment>{$n} SKU(s) not found: {$sample}" . ($n > 20 ? ' …' : '') . '</comment>');
        }
        return Command::SUCCESS;
    }
}

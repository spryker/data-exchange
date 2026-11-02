<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Business\Export\Exporter;

use Generated\Shared\Transfer\ErrorTransfer;
use Generated\Shared\Transfer\ImportJobCriteriaTransfer;
use Generated\Shared\Transfer\ImportJobExportResultTransfer;
use Generated\Shared\Transfer\ImportJobRunFileInfoTransfer;
use Generated\Shared\Transfer\ImportJobTransfer;
use Spryker\Zed\DataExchange\Business\Export\ColumnResolver\ExportColumnResolverInterface;
use Spryker\Zed\DataExchange\Business\Export\Writer\ExportFileWriterInterface;
use Spryker\Zed\DataExchange\Business\SchemaPlugin\SchemaPluginResolverInterface;
use Spryker\Zed\DataExchange\DataExchangeConfig;
use Spryker\Zed\DataExchange\Persistence\DataExchangeRepositoryInterface;
use Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ExportDataProviderInterface;
use Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ExportSchemaPluginInterface;
use Spryker\Zed\DataExchangeExtension\Dependency\Plugin\SchemaPluginInterface;

class Exporter implements ExporterInterface
{
    protected const string ERROR_MESSAGE_IMPORT_JOB_NOT_FOUND = 'No import job matches the given export criteria.';

    protected const string ERROR_MESSAGE_SCHEMA_PLUGIN_NOT_FOUND = 'No schema plugin found for job type "%s".';

    protected const string ERROR_MESSAGE_EXPORT_SCHEMA_PLUGIN_NOT_FOUND = 'No export schema plugin found for job type "%s".';

    public function __construct(
        protected DataExchangeRepositoryInterface $repository,
        protected ExportColumnResolverInterface $exportColumnResolver,
        protected SchemaPluginResolverInterface $schemaPluginResolver,
        protected DataExchangeConfig $config,
        protected ExportFileWriterInterface $exportFileWriter,
    ) {
    }

    public function exportData(ImportJobCriteriaTransfer $criteriaTransfer): ImportJobExportResultTransfer
    {
        $importJob = $this->findImportJob($criteriaTransfer);

        if ($importJob === null) {
            return $this->createErrorResult(static::ERROR_MESSAGE_IMPORT_JOB_NOT_FOUND);
        }

        if ($criteriaTransfer->getIsWithData() === true) {
            return $this->exportWithData($importJob);
        }

        return $this->exportTemplate($importJob);
    }

    protected function exportTemplate(ImportJobTransfer $importJob): ImportJobExportResultTransfer
    {
        $schemaPlugin = $this->schemaPluginResolver->findImportSchemaPlugin($importJob->getTypeOrFail());

        if ($schemaPlugin === null) {
            return $this->createErrorResult(sprintf(static::ERROR_MESSAGE_SCHEMA_PLUGIN_NOT_FOUND, $importJob->getTypeOrFail()));
        }

        return $this->createSuccessResult($importJob, $this->resolveColumnHeaders($importJob, $schemaPlugin));
    }

    protected function exportWithData(ImportJobTransfer $importJob): ImportJobExportResultTransfer
    {
        $schemaPlugin = $this->schemaPluginResolver->findExportSchemaPlugin($importJob->getTypeOrFail());

        if ($schemaPlugin === null) {
            return $this->createErrorResult(sprintf(static::ERROR_MESSAGE_EXPORT_SCHEMA_PLUGIN_NOT_FOUND, $importJob->getTypeOrFail()));
        }

        $columnHeaders = $this->resolveColumnHeaders($importJob, $schemaPlugin);
        $fileInfo = $this->executeBatchExport($schemaPlugin, $columnHeaders, $importJob->getTypeOrFail());

        return $this->createSuccessResult($importJob, $columnHeaders)->setFileInfo($fileInfo);
    }

    protected function createErrorResult(string $message): ImportJobExportResultTransfer
    {
        return (new ImportJobExportResultTransfer())
            ->setIsSuccessful(false)
            ->addError((new ErrorTransfer())->setMessage($message));
    }

    /**
     * @param array<string> $columnHeaders
     */
    protected function createSuccessResult(ImportJobTransfer $importJob, array $columnHeaders): ImportJobExportResultTransfer
    {
        return (new ImportJobExportResultTransfer())
            ->setIsSuccessful(true)
            ->setType($importJob->getTypeOrFail())
            ->setColumns($columnHeaders);
    }

    /**
     * @return array<string>
     */
    protected function resolveColumnHeaders(ImportJobTransfer $importJobTransfer, SchemaPluginInterface $schemaPlugin): array
    {
        return $this->exportColumnResolver->resolveColumnHeaders(
            $importJobTransfer,
            $schemaPlugin->getPlaceholderProvider()->getPlaceholderValues(),
        );
    }

    protected function findImportJob(ImportJobCriteriaTransfer $criteriaTransfer): ?ImportJobTransfer
    {
        return $this->repository
            ->getImportJobCollection($criteriaTransfer)
            ->getImportJobs()
            ->getIterator()
            ->current() ?: null;
    }

    /**
     * @param array<string> $columns
     */
    protected function executeBatchExport(
        ExportSchemaPluginInterface $schemaPlugin,
        array $columns,
        string $jobType,
    ): ImportJobRunFileInfoTransfer {
        try {
            $this->exportFileWriter->openFile($columns);
            $this->writeAllBatches($schemaPlugin, $columns);

            return $this->exportFileWriter->closeAndStore($jobType);
        } finally {
            $this->exportFileWriter->abort();
        }
    }

    /**
     * @param array<string> $columns
     */
    protected function writeAllBatches(ExportSchemaPluginInterface $schemaPlugin, array $columns): void
    {
        $dataProvider = $schemaPlugin->getExportDataProvider();
        $exportSteps = $schemaPlugin->getExportSteps();
        $batchSize = $this->config->getExportCsvBatchSize();
        $lastId = 0;

        while (true) {
            $seedRows = $dataProvider->getBatch($columns, $batchSize, $lastId);

            if ($seedRows === []) {
                return;
            }

            $lastId = (int)$seedRows[array_key_last($seedRows)][ExportDataProviderInterface::INTERNAL_COLUMN_CURSOR_ID];

            foreach ($exportSteps as $step) {
                $seedRows = $step->exportRows($seedRows, $columns);
            }

            $this->exportFileWriter->writeBatch($seedRows, $columns);
        }
    }
}

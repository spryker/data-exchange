<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Business\ImportJobRun\Importer;

use ArrayObject;
use DateTime;
use Generated\Shared\Transfer\EventEntityTransfer;
use Generated\Shared\Transfer\FileSystemStreamTransfer;
use Generated\Shared\Transfer\ImportJobConditionsTransfer;
use Generated\Shared\Transfer\ImportJobCriteriaTransfer;
use Generated\Shared\Transfer\ImportJobRunErrorTransfer;
use Generated\Shared\Transfer\ImportJobRunImportResultTransfer;
use Generated\Shared\Transfer\ImportJobRunTransfer;
use Generated\Shared\Transfer\ImportStepResponseTransfer;
use Propel\Runtime\Propel;
use Spryker\Service\FileSystem\FileSystemServiceInterface;
use Spryker\Zed\DataExchange\Business\ImportJobRun\Importer\Exception\SchemaPluginNotFoundException;
use Spryker\Zed\DataExchange\Business\ImportJobRun\Mapper\CsvHeaderMapperInterface;
use Spryker\Zed\DataExchange\Business\ImportJobRun\Resolver\ImportJobRunClaimSettingsResolverInterface;
use Spryker\Zed\DataExchange\Business\SchemaPlugin\SchemaPluginResolverInterface;
use Spryker\Zed\DataExchange\DataExchangeConfig;
use Spryker\Zed\DataExchange\Persistence\DataExchangeEntityManagerInterface;
use Spryker\Zed\DataExchange\Persistence\DataExchangeRepositoryInterface;
use Spryker\Zed\DataExchangeExtension\Dependency\Plugin\CacheResettableImportStepInterface;
use Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ImportSchemaPluginInterface;
use Spryker\Zed\Event\Business\EventFacadeInterface;
use Spryker\Zed\EventBehavior\EventBehaviorConfig;
use Spryker\Zed\Kernel\Persistence\EntityManager\TransactionTrait;
use Throwable;

class ImportJobRunImporter implements ImportJobRunImporterInterface
{
    use TransactionTrait;

    protected const string DATETIME_FORMAT = 'Y-m-d H:i:s';

    protected const string ERROR_MESSAGE_NO_ROWS_TO_IMPORT = 'The file has no rows to import.';

    protected const string ERROR_MESSAGE_COLUMN_COUNT_MISMATCH = 'Column count mismatch: expected %d columns, got %d.';

    public function __construct(
        protected DataExchangeRepositoryInterface $repository,
        protected DataExchangeEntityManagerInterface $entityManager,
        protected FileSystemServiceInterface $fileSystemService,
        protected EventFacadeInterface $eventFacade,
        protected DataExchangeConfig $config,
        protected CsvHeaderMapperInterface $csvHeaderMapper,
        protected SchemaPluginResolverInterface $schemaPluginResolver,
        protected ImportJobRunClaimSettingsResolverInterface $importJobRunClaimSettingsResolver,
    ) {
    }

    public function importJobRun(ImportJobRunTransfer $importJobRun): bool
    {
        $schemaPlugin = $this->resolveSchemaPluginForJobRun($importJobRun);
        $this->csvHeaderMapper->buildMappingRulesFromSchema($schemaPlugin->getSchema());
        $steps = $schemaPlugin->getImportSteps();
        $stream = $this->openCsvStream($importJobRun);

        // Propel keeps every loaded entity in its instance pool. Disable the pool so memory stays flat.
        $isInstancePoolingEnabled = Propel::disableInstancePooling();

        try {
            $importResult = $this->readAndProcessCsv($stream, $importJobRun, $steps);
        } catch (Throwable $exception) {
            // A step that threw mid-batch leaves its caches filled. The next run of this process must start clean.
            $this->resetStepsCaches($steps);

            throw $exception;
        } finally {
            fclose($stream);
            EventBehaviorConfig::enableEvent();

            if ($isInstancePoolingEnabled) {
                Propel::enableInstancePooling();
            }
        }

        $this->triggerCollectedPublishEvents($importResult->getPublishEvents());

        if ($importResult->getIsLeaseLostOrFail()) {
            return false;
        }

        return $this->getTransactionHandler()->handleTransaction(function () use ($importJobRun, $importResult): bool {
            return $this->executeWriteFinalStatusTransaction($importJobRun, $importResult);
        });
    }

    protected function executeWriteFinalStatusTransaction(ImportJobRunTransfer $importJobRun, ImportJobRunImportResultTransfer $importResult): bool
    {
        $isFinalStatusWritten = $this->updateJobRunWithResults($importJobRun, $importResult);

        if ($isFinalStatusWritten && $importResult->getNumberOfProcessedLinesOrFail() === 0) {
            $this->recordNoRowsToImportError($importJobRun);
        }

        return $isFinalStatusWritten;
    }

    protected function recordNoRowsToImportError(ImportJobRunTransfer $importJobRun): void
    {
        $this->entityManager->createImportJobRunError(
            (new ImportJobRunErrorTransfer())
                ->setFkImportJobRun($importJobRun->getIdImportJobRunOrFail())
                ->setErrorMessage(static::ERROR_MESSAGE_NO_ROWS_TO_IMPORT),
        );
    }

    /**
     * @return resource
     */
    protected function openCsvStream(ImportJobRunTransfer $importJobRun)
    {
        $fileInfo = $importJobRun->getFileInfoOrFail();

        $fileStreamTransfer = (new FileSystemStreamTransfer())
            ->setFileSystemName($fileInfo->getFileSystemNameOrFail())
            ->setPath($fileInfo->getStoredPathOrFail());

        return $this->fileSystemService->readStream($fileStreamTransfer);
    }

    protected function resolveSchemaPluginForJobRun(ImportJobRunTransfer $importJobRun): ImportSchemaPluginInterface
    {
        $criteriaTransfer = (new ImportJobCriteriaTransfer())
            ->setImportJobConditions(
                (new ImportJobConditionsTransfer())->addIdImportJob($importJobRun->getFkImportJobOrFail()),
            );

        $importJob = $this->repository->getImportJobCollection($criteriaTransfer)->getImportJobs()->getIterator()->current();

        if ($importJob === null) {
            throw new SchemaPluginNotFoundException(sprintf('No import job found for ID %d.', $importJobRun->getFkImportJobOrFail()));
        }

        $schemaPlugin = $this->schemaPluginResolver->findImportSchemaPlugin($importJob->getTypeOrFail());

        if ($schemaPlugin === null) {
            throw new SchemaPluginNotFoundException(sprintf('No schema plugin found for job type "%s".', $importJob->getType()));
        }

        return $schemaPlugin;
    }

    /**
     * @param array<int, string|null> $rawRow
     */
    protected function isBlankLine(array $rawRow): bool
    {
        return $rawRow === [null];
    }

    /**
     * Reads the file batch by batch. The row errors of a batch are kept on the result until the progress of that batch
     * is written, so they are stored only while this worker still holds the claim.
     *
     * @param resource $stream
     * @param array<\Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ImportStepInterface> $steps
     */
    protected function readAndProcessCsv($stream, ImportJobRunTransfer $importJobRun, array $steps): ImportJobRunImportResultTransfer
    {
        $headers = null;
        $batch = [];
        $rowNumber = 0;
        $batchSize = $this->config->getImportCsvBatchSize();
        $importResult = (new ImportJobRunImportResultTransfer())
            ->setIsLeaseLost(false)
            ->setNumberOfProcessedLines(0)
            ->setNumberOfSuccessfullyProcessedLines(0)
            ->setNumberOfFailedLines(0)
            ->setNumberOfRecordedErrorRows(0)
            ->setNumberOfOmittedErrorRows(0);
        $idImportJobRun = $importJobRun->getIdImportJobRunOrFail();

        EventBehaviorConfig::disableEvent();

        while (!feof($stream)) {
            $rawRow = fgetcsv($stream);

            if ($rawRow === false) {
                continue;
            }

            $rowNumber++;

            if ($this->isBlankLine($rawRow)) {
                continue;
            }

            if ($headers === null) {
                $headers = array_map(static fn (?string $value): string => (string)$value, $rawRow);
                $this->csvHeaderMapper->mapHeaders($headers);

                continue;
            }

            if (count($headers) !== count($rawRow)) {
                $importResult
                    ->setNumberOfFailedLines($importResult->getNumberOfFailedLinesOrFail() + 1)
                    ->setNumberOfProcessedLines($importResult->getNumberOfProcessedLinesOrFail() + 1);
                $this->recordRowError(
                    (new ImportJobRunErrorTransfer())
                        ->setFkImportJobRun($idImportJobRun)
                        ->setCsvRowNumber($rowNumber)
                        ->setErrorMessage(sprintf(static::ERROR_MESSAGE_COLUMN_COUNT_MISMATCH, count($headers), count($rawRow))),
                    $importResult,
                );

                continue;
            }

            $normalizedRow = array_map(static fn (?string $value): string => (string)$value, $rawRow);
            $batch[$rowNumber] = $this->csvHeaderMapper->mapRow(array_combine($headers, $normalizedRow));

            if (count($batch) < $batchSize) {
                continue;
            }

            $this->processBatch($batch, $idImportJobRun, $steps, $importResult);
            $batch = [];

            if (!$this->writeProgress($stream, $importJobRun, $importResult)) {
                $importResult->setIsLeaseLost(true);

                break;
            }
        }

        if ($batch !== []) {
            $this->processBatch($batch, $idImportJobRun, $steps, $importResult);
            $importResult->setIsLeaseLost(!$this->writeProgress($stream, $importJobRun, $importResult));
        }

        return $importResult->setNumberOfProcessedBytes($this->getProcessedBytes($stream));
    }

    /**
     * Runs one batch through every step, folds its counters, row errors and publish events into the running result
     * and resets the step caches. Only the deduplicated publish events are kept until the end of the run.
     *
     * @param array<int, array<string, string>> $batch
     * @param array<\Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ImportStepInterface> $steps
     */
    protected function processBatch(array $batch, int $idImportJobRun, array $steps, ImportJobRunImportResultTransfer $importResult): void
    {
        $this->executeBatch($batch, $idImportJobRun, $steps, $importResult);

        $this->resetStepsCaches($steps);
    }

    /**
     * Writes the running counters, renews the lease and stores the row errors recorded since the last write in one
     * transaction. Returns `false` and keeps nothing when another worker took over the claim.
     *
     * @param resource $stream
     */
    protected function writeProgress($stream, ImportJobRunTransfer $importJobRunTransfer, ImportJobRunImportResultTransfer $importResult): bool
    {
        $writtenAt = new DateTime();

        $importJobRunTransfer
            ->setNumberOfProcessedLines($importResult->getNumberOfProcessedLinesOrFail())
            ->setNumberOfSuccessfullyProcessedLines($importResult->getNumberOfSuccessfullyProcessedLinesOrFail())
            ->setNumberOfFailedLines($importResult->getNumberOfFailedLinesOrFail())
            ->setNumberOfOmittedErrorRows($importResult->getNumberOfOmittedErrorRowsOrFail())
            ->setNumberOfProcessedBytes($this->getProcessedBytes($stream))
            ->setLeaseExpiresAt($this->importJobRunClaimSettingsResolver->getLeaseExpiresAt())
            ->setUpdatedAt($writtenAt->format(static::DATETIME_FORMAT));

        return $this->getTransactionHandler()->handleTransaction(function () use ($importJobRunTransfer, $importResult): bool {
            return $this->executeWriteProgressTransaction($importJobRunTransfer, $importResult);
        });
    }

    protected function executeWriteProgressTransaction(ImportJobRunTransfer $importJobRunTransfer, ImportJobRunImportResultTransfer $importResult): bool
    {
        if (!$this->entityManager->updateClaimedImportJobRun($importJobRunTransfer)) {
            return false;
        }

        foreach ($importResult->getPendingImportJobRunErrors() as $pendingImportJobRunError) {
            $this->entityManager->createImportJobRunError($pendingImportJobRunError);
        }

        $importResult->setPendingImportJobRunErrors(new ArrayObject());

        return true;
    }

    /**
     * @param array<int, array<string, string>> $batch
     * @param array<\Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ImportStepInterface> $steps
     */
    protected function executeBatch(array $batch, int $idImportJobRun, array $steps, ImportJobRunImportResultTransfer $importResult): void
    {
        $propertyNamesInFile = $this->csvHeaderMapper->getReverseMap();
        // A row rejected by several steps is one failed line, so the row numbers are collected as a set.
        $failedRowNumbers = [];

        foreach ($steps as $step) {
            $stepResponse = $step->executeBatch($batch, $propertyNamesInFile);

            foreach ($stepResponse->getErrors() as $error) {
                $failedRowNumbers[$error->getCsvRowNumberOrFail()] = true;
                $this->recordRowError(
                    (new ImportJobRunErrorTransfer())
                        ->setFkImportJobRun($idImportJobRun)
                        ->setCsvRowNumber($error->getCsvRowNumber())
                        ->setErrorMessage($error->getErrorMessageOrFail()),
                    $importResult,
                );
            }

            foreach ($this->extractPublishEvents($stepResponse) as $eventName => $entityTransfers) {
                $publishEvents = $importResult->getPublishEvents();
                $publishEvents[$eventName] = ($publishEvents[$eventName] ?? []) + $entityTransfers;
                $importResult->setPublishEvents($publishEvents);
            }
        }

        $importResult
            ->setNumberOfProcessedLines($importResult->getNumberOfProcessedLinesOrFail() + count($batch))
            ->setNumberOfSuccessfullyProcessedLines($importResult->getNumberOfSuccessfullyProcessedLinesOrFail() + count($batch) - count($failedRowNumbers))
            ->setNumberOfFailedLines($importResult->getNumberOfFailedLinesOrFail() + count($failedRowNumbers));
    }

    /**
     * Keeps the row error for the next progress write until the run holds the configured number of error rows; past
     * the limit the error is only counted, so the failed line count stays exact while the error table keeps the first rows.
     */
    protected function recordRowError(ImportJobRunErrorTransfer $rowError, ImportJobRunImportResultTransfer $importResult): void
    {
        if ($importResult->getNumberOfRecordedErrorRowsOrFail() >= $this->config->getImportJobRunErrorRowLimit()) {
            $importResult->setNumberOfOmittedErrorRows($importResult->getNumberOfOmittedErrorRowsOrFail() + 1);

            return;
        }

        $importResult
            ->addPendingImportJobRunError($rowError)
            ->setNumberOfRecordedErrorRows($importResult->getNumberOfRecordedErrorRowsOrFail() + 1);
    }

    /**
     * Clears the lookup caches of every step that implements `CacheResettableImportStepInterface`.
     *
     * @param array<\Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ImportStepInterface> $steps
     */
    protected function resetStepsCaches(array $steps): void
    {
        foreach ($steps as $step) {
            if ($step instanceof CacheResettableImportStepInterface) {
                $step->resetCache();
            }
        }
    }

    /**
     * @return array<string, array<int, \Generated\Shared\Transfer\EventEntityTransfer>>
     */
    protected function extractPublishEvents(ImportStepResponseTransfer $stepResponse): array
    {
        $events = [];

        foreach ($stepResponse->getPublishEvents() as $publishEvent) {
            $entityId = $publishEvent->getEntityIdOrFail();
            $events[$publishEvent->getEventNameOrFail()][$entityId] = (new EventEntityTransfer())->setId($entityId);
        }

        return $events;
    }

    /**
     * @param resource $stream
     */
    protected function getProcessedBytes($stream): ?int
    {
        $position = ftell($stream);

        return $position === false ? null : $position;
    }

    /**
     * @param array<string, array<int, \Generated\Shared\Transfer\EventEntityTransfer>> $publishEvents
     */
    protected function triggerCollectedPublishEvents(array $publishEvents): void
    {
        foreach ($publishEvents as $eventName => $eventEntityTransfers) {
            $this->eventFacade->triggerBulk($eventName, array_values($eventEntityTransfers));
        }
    }

    protected function updateJobRunWithResults(ImportJobRunTransfer $importJobRun, ImportJobRunImportResultTransfer $importResult): bool
    {
        $finishedAt = (new DateTime())->format(static::DATETIME_FORMAT);

        $importJobRun
            ->setStatus($this->resolveFinalStatus($importResult->getNumberOfSuccessfullyProcessedLinesOrFail(), $importResult->getNumberOfFailedLinesOrFail()))
            ->setNumberOfProcessedLines($importResult->getNumberOfProcessedLinesOrFail())
            ->setNumberOfSuccessfullyProcessedLines($importResult->getNumberOfSuccessfullyProcessedLinesOrFail())
            ->setNumberOfFailedLines($importResult->getNumberOfFailedLinesOrFail())
            ->setNumberOfOmittedErrorRows($importResult->getNumberOfOmittedErrorRowsOrFail())
            ->setNumberOfProcessedBytes($importResult->getNumberOfProcessedBytes())
            ->setImportFinishedAt($finishedAt)
            ->setUpdatedAt($finishedAt);

        return $this->entityManager->updateClaimedImportJobRun($importJobRun);
    }

    protected function resolveFinalStatus(int $successfulLines, int $failedLines): string
    {
        if ($successfulLines === 0) {
            return $this->config->getImportStatusFailed();
        }

        if ($failedLines > 0) {
            return $this->config->getImportStatusImportedWithErrors();
        }

        return $this->config->getImportStatusDone();
    }
}

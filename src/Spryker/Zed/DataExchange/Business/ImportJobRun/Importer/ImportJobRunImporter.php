<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Business\ImportJobRun\Importer;

use DateTime;
use Generated\Shared\Transfer\EventEntityTransfer;
use Generated\Shared\Transfer\FileSystemStreamTransfer;
use Generated\Shared\Transfer\ImportJobConditionsTransfer;
use Generated\Shared\Transfer\ImportJobCriteriaTransfer;
use Generated\Shared\Transfer\ImportJobRunErrorTransfer;
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

        $this->triggerCollectedPublishEvents($importResult['collectedPublishEvents']);

        if ($importResult['isLeaseLost']) {
            return false;
        }

        return $this->getTransactionHandler()->handleTransaction(function () use ($importJobRun, $importResult): bool {
            return $this->executeWriteFinalStatusTransaction($importJobRun, $importResult);
        });
    }

    /**
     * @phpstan-param array{isLeaseLost: bool, processedBytes: int|null, processedLines: int, successfulLines: int, failedLines: int, collectedPublishEvents: array<string, array<int, \Generated\Shared\Transfer\EventEntityTransfer>>} $importResult
     *
     * @param array<string, mixed> $importResult
     */
    protected function executeWriteFinalStatusTransaction(ImportJobRunTransfer $importJobRun, array $importResult): bool
    {
        $isFinalStatusWritten = $this->updateJobRunWithResults($importJobRun, $importResult['processedLines'], $importResult['successfulLines'], $importResult['failedLines'], $importResult['processedBytes']);

        if ($isFinalStatusWritten && $importResult['processedLines'] === 0) {
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

    protected function recordColumnMismatchError(ImportJobRunTransfer $importJobRun, int $rowNumber, int $expectedCount, int $actualCount): void
    {
        $this->entityManager->createImportJobRunError(
            (new ImportJobRunErrorTransfer())
                ->setFkImportJobRun($importJobRun->getIdImportJobRunOrFail())
                ->setCsvRowNumber($rowNumber)
                ->setErrorMessage(sprintf(
                    'Column count mismatch: expected %d columns, got %d.',
                    $expectedCount,
                    $actualCount,
                )),
        );
    }

    /**
     * @param resource $stream
     * @param array<\Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ImportStepInterface> $steps
     *
     * @return array{isLeaseLost: bool, processedBytes: int|null, processedLines: int, successfulLines: int, failedLines: int, collectedPublishEvents: array<string, array<int, \Generated\Shared\Transfer\EventEntityTransfer>>}
     */
    protected function readAndProcessCsv($stream, ImportJobRunTransfer $importJobRun, array $steps): array
    {
        $headers = null;
        $batch = [];
        $rowNumber = 0;
        $batchSize = $this->config->getImportCsvBatchSize();
        $totals = [
            'processedLines' => 0,
            'successfulLines' => 0,
            'failedLines' => 0,
            'collectedPublishEvents' => [],
        ];
        $isLeaseLost = false;
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
                $totals['failedLines']++;
                $totals['processedLines']++;

                $this->recordColumnMismatchError($importJobRun, $rowNumber, count($headers), count($rawRow));

                continue;
            }

            $normalizedRow = array_map(static fn (?string $value): string => (string)$value, $rawRow);
            $batch[$rowNumber] = $this->csvHeaderMapper->mapRow(array_combine($headers, $normalizedRow));

            if (count($batch) < $batchSize) {
                continue;
            }

            $totals = $this->processBatch($batch, $idImportJobRun, $steps, $totals);
            $batch = [];

            if (!$this->writeProgress($stream, $importJobRun, $totals)) {
                $isLeaseLost = true;

                break;
            }
        }

        if ($batch !== []) {
            $totals = $this->processBatch($batch, $idImportJobRun, $steps, $totals);
            $isLeaseLost = !$this->writeProgress($stream, $importJobRun, $totals);
        }

        return [
            'isLeaseLost' => $isLeaseLost,
            'processedBytes' => $this->getProcessedBytes($stream),
            'processedLines' => $totals['processedLines'],
            'successfulLines' => $totals['successfulLines'],
            'failedLines' => $totals['failedLines'],
            'collectedPublishEvents' => $totals['collectedPublishEvents'],
        ];
    }

    /**
     * Runs one batch through every step, folds its counters and publish events into the running totals and resets
     * the step caches. Only the deduplicated publish events are kept until the end of the run.
     *
     * @phpstan-param array{processedLines: int, successfulLines: int, failedLines: int, collectedPublishEvents: array<string, array<int, \Generated\Shared\Transfer\EventEntityTransfer>>} $totals
     *
     * @param array<int, array<string, string>> $batch
     * @param array<\Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ImportStepInterface> $steps
     * @param array<string, mixed> $totals
     *
     * @return array{processedLines: int, successfulLines: int, failedLines: int, collectedPublishEvents: array<string, array<int, \Generated\Shared\Transfer\EventEntityTransfer>>}
     */
    protected function processBatch(array $batch, int $idImportJobRun, array $steps, array $totals): array
    {
        $batchResult = $this->executeBatch($batch, $idImportJobRun, $steps);

        $totals['processedLines'] += count($batch);
        $totals['successfulLines'] += $batchResult['successful'];
        $totals['failedLines'] += $batchResult['failed'];

        foreach ($batchResult['publishEvents'] as $eventName => $entityTransfers) {
            $totals['collectedPublishEvents'][$eventName] = ($totals['collectedPublishEvents'][$eventName] ?? []) + $entityTransfers;
        }

        $this->resetStepsCaches($steps);

        return $totals;
    }

    /**
     * Writes the running totals and renews the lease; returns `false` when another worker took over the claim.
     *
     * @phpstan-param array{processedLines: int, successfulLines: int, failedLines: int, collectedPublishEvents: array<string, array<int, \Generated\Shared\Transfer\EventEntityTransfer>>} $totals
     *
     * @param resource $stream
     * @param array<string, mixed> $totals
     */
    protected function writeProgress($stream, ImportJobRunTransfer $importJobRunTransfer, array $totals): bool
    {
        $writtenAt = new DateTime();

        $importJobRunTransfer
            ->setNumberOfProcessedLines($totals['processedLines'])
            ->setNumberOfSuccessfullyProcessedLines($totals['successfulLines'])
            ->setNumberOfFailedLines($totals['failedLines'])
            ->setNumberOfProcessedBytes($this->getProcessedBytes($stream))
            ->setLeaseExpiresAt($this->importJobRunClaimSettingsResolver->getLeaseExpiresAt())
            ->setUpdatedAt($writtenAt->format(static::DATETIME_FORMAT));

        return $this->entityManager->updateClaimedImportJobRun($importJobRunTransfer);
    }

    /**
     * @param array<int, array<string, string>> $batch
     * @param array<\Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ImportStepInterface> $steps
     *
     * @return array{successful: int, failed: int, publishEvents: array<string, array<int, \Generated\Shared\Transfer\EventEntityTransfer>>}
     */
    protected function executeBatch(array $batch, int $idImportJobRun, array $steps): array
    {
        $propertyNamesInFile = $this->csvHeaderMapper->getReverseMap();
        $failedRowNumbers = [];
        $publishEvents = [];

        foreach ($steps as $step) {
            $stepResponse = $step->executeBatch($batch, $propertyNamesInFile);
            $failedRowNumbers += $this->persistStepErrorsAndGetFailedRows($stepResponse, $idImportJobRun);

            foreach ($this->extractPublishEvents($stepResponse) as $eventName => $entityTransfers) {
                $publishEvents[$eventName] = ($publishEvents[$eventName] ?? []) + $entityTransfers;
            }
        }

        return [
            'successful' => count($batch) - count($failedRowNumbers),
            'failed' => count($failedRowNumbers),
            'publishEvents' => $publishEvents,
        ];
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
     * @return array<int, bool>
     */
    protected function persistStepErrorsAndGetFailedRows(
        ImportStepResponseTransfer $stepResponse,
        int $idImportJobRun,
    ): array {
        $failedRowNumbers = [];

        foreach ($stepResponse->getErrors() as $error) {
            $failedRowNumbers[$error->getCsvRowNumberOrFail()] = true;

            $this->entityManager->createImportJobRunError(
                (new ImportJobRunErrorTransfer())
                    ->setFkImportJobRun($idImportJobRun)
                    ->setCsvRowNumber($error->getCsvRowNumber())
                    ->setErrorMessage($error->getErrorMessageOrFail()),
            );
        }

        return $failedRowNumbers;
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
     * @param array<string, array<int, \Generated\Shared\Transfer\EventEntityTransfer>> $collectedPublishEvents
     */
    protected function triggerCollectedPublishEvents(array $collectedPublishEvents): void
    {
        foreach ($collectedPublishEvents as $eventName => $eventEntityTransfers) {
            $this->eventFacade->triggerBulk($eventName, array_values($eventEntityTransfers));
        }
    }

    protected function updateJobRunWithResults(
        ImportJobRunTransfer $importJobRun,
        int $processedLines,
        int $successfulLines,
        int $failedLines,
        ?int $processedBytes,
    ): bool {
        $finishedAt = (new DateTime())->format(static::DATETIME_FORMAT);

        $importJobRun
            ->setStatus($this->resolveFinalStatus($successfulLines, $failedLines))
            ->setNumberOfProcessedLines($processedLines)
            ->setNumberOfSuccessfullyProcessedLines($successfulLines)
            ->setNumberOfFailedLines($failedLines)
            ->setNumberOfProcessedBytes($processedBytes)
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

<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Business\ImportJobRun\Importer;

use DateTime;
use DateTimeZone;
use Generated\Shared\Transfer\EventEntityTransfer;
use Generated\Shared\Transfer\FileSystemStreamTransfer;
use Generated\Shared\Transfer\ImportJobConditionsTransfer;
use Generated\Shared\Transfer\ImportJobCriteriaTransfer;
use Generated\Shared\Transfer\ImportJobRunErrorTransfer;
use Generated\Shared\Transfer\ImportJobRunTransfer;
use Generated\Shared\Transfer\ImportStepResponseTransfer;
use Spryker\Service\FileSystem\FileSystemServiceInterface;
use Spryker\Zed\DataExchange\Business\ImportJobRun\Importer\Exception\SchemaPluginNotFoundException;
use Spryker\Zed\DataExchange\Business\ImportJobRun\Mapper\CsvHeaderMapperInterface;
use Spryker\Zed\DataExchange\Business\SchemaPlugin\SchemaPluginResolverInterface;
use Spryker\Zed\DataExchange\DataExchangeConfig;
use Spryker\Zed\DataExchange\Persistence\DataExchangeEntityManagerInterface;
use Spryker\Zed\DataExchange\Persistence\DataExchangeRepositoryInterface;
use Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ImportSchemaPluginInterface;
use Spryker\Zed\Event\Business\EventFacadeInterface;
use Spryker\Zed\EventBehavior\EventBehaviorConfig;

class ImportJobRunImporter implements ImportJobRunImporterInterface
{
    protected const string DATETIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        protected DataExchangeRepositoryInterface $repository,
        protected DataExchangeEntityManagerInterface $entityManager,
        protected FileSystemServiceInterface $fileSystemService,
        protected EventFacadeInterface $eventFacade,
        protected DataExchangeConfig $config,
        protected CsvHeaderMapperInterface $csvHeaderMapper,
        protected SchemaPluginResolverInterface $schemaPluginResolver,
    ) {
    }

    public function importJobRun(ImportJobRunTransfer $importJobRun): bool
    {
        $schemaPlugin = $this->resolveSchemaPluginForJobRun($importJobRun);
        $this->csvHeaderMapper->buildMappingRulesFromSchema($schemaPlugin->getSchema());
        $stream = $this->openCsvStream($importJobRun);

        try {
            $importResult = $this->readAndProcessCsv($stream, $importJobRun, $schemaPlugin->getImportSteps());
        } finally {
            fclose($stream);
            EventBehaviorConfig::enableEvent();
        }

        $this->triggerCollectedPublishEvents($importResult['collectedPublishEvents']);

        if ($importResult['isLeaseLost']) {
            return false;
        }

        return $this->updateJobRunWithResults($importJobRun, $importResult['processedLines'], $importResult['successfulLines'], $importResult['failedLines'], $importResult['processedBytes']);
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
        $processedLines = 0;
        $successfulLines = 0;
        $failedLines = 0;
        $batchSize = $this->config->getImportCsvBatchSize();
        $batchResults = [];
        $collectedPublishEvents = [];
        $isLeaseLost = false;
        $idImportJobRun = $importJobRun->getIdImportJobRunOrFail();

        EventBehaviorConfig::disableEvent();

        while (!feof($stream)) {
            $rawRow = fgetcsv($stream);

            if ($rawRow === false) {
                continue;
            }

            $rowNumber++;

            if ($headers === null) {
                $headers = array_map(static fn (?string $value): string => (string)$value, $rawRow);
                $this->csvHeaderMapper->mapHeaders($headers);

                continue;
            }

            if (count($headers) !== count($rawRow)) {
                $failedLines++;
                $processedLines++;

                $this->recordColumnMismatchError($importJobRun, $rowNumber, count($headers), count($rawRow));

                continue;
            }

            $normalizedRow = array_map(static fn (?string $value): string => (string)$value, $rawRow);
            $batch[$rowNumber] = $this->csvHeaderMapper->mapRow(array_combine($headers, $normalizedRow));

            if (count($batch) < $batchSize) {
                continue;
            }

            $batchResults[] = $this->executeBatch($batch, $idImportJobRun, $steps);
            $processedLines += count($batch);
            $batch = [];

            if (!$this->writeProgress($stream, $importJobRun, $processedLines, $failedLines, $batchResults)) {
                $isLeaseLost = true;

                break;
            }
        }

        if ($batch !== []) {
            $batchResults[] = $this->executeBatch($batch, $idImportJobRun, $steps);
            $processedLines += count($batch);
            $isLeaseLost = !$this->writeProgress($stream, $importJobRun, $processedLines, $failedLines, $batchResults);
        }

        foreach ($batchResults as $batchResult) {
            $successfulLines += $batchResult['successful'];
            $failedLines += $batchResult['failed'];
            foreach ($batchResult['publishEvents'] as $eventName => $entityTransfers) {
                $collectedPublishEvents[$eventName] = ($collectedPublishEvents[$eventName] ?? []) + $entityTransfers;
            }
        }

        return [
            'isLeaseLost' => $isLeaseLost,
            'processedBytes' => $this->getProcessedBytes($stream),
            'processedLines' => $processedLines,
            'successfulLines' => $successfulLines,
            'failedLines' => $failedLines,
            'collectedPublishEvents' => $collectedPublishEvents,
        ];
    }

    /**
     * @param resource $stream
     * @param array<array{successful: int, failed: int, publishEvents: array<string, array<int, \Generated\Shared\Transfer\EventEntityTransfer>>}> $batchResults
     */
    protected function writeProgress($stream, ImportJobRunTransfer $importJobRunTransfer, int $processedLines, int $failedLines, array $batchResults): bool
    {
        $writtenAt = new DateTime();
        $leaseExpiresAt = (new DateTime('now', new DateTimeZone($this->config->getImportJobRunLeaseTimeZone())))
            ->modify(sprintf('+%d minutes', max(1, $this->config->getImportJobRunLeaseDurationInMinutes())));

        $importJobRunTransfer
            ->setNumberOfProcessedLines($processedLines)
            ->setNumberOfSuccessfullyProcessedLines(array_sum(array_column($batchResults, 'successful')))
            ->setNumberOfFailedLines($failedLines + array_sum(array_column($batchResults, 'failed')))
            ->setNumberOfProcessedBytes($this->getProcessedBytes($stream))
            ->setLeaseExpiresAt($leaseExpiresAt->format(static::DATETIME_FORMAT))
            ->setUpdatedAt($writtenAt->format(static::DATETIME_FORMAT));

        return $this->entityManager->updateClaimedImportJobRunProgress($importJobRunTransfer);
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

        $status = $processedLines === 0 || ($successfulLines === 0 && $failedLines > 0)
            ? $this->config->getImportStatusFailed()
            : $this->config->getImportStatusDone();

        $importJobRun
            ->setStatus($status)
            ->setNumberOfProcessedLines($processedLines)
            ->setNumberOfSuccessfullyProcessedLines($successfulLines)
            ->setNumberOfFailedLines($failedLines)
            ->setNumberOfProcessedBytes($processedBytes)
            ->setImportFinishedAt($finishedAt)
            ->setUpdatedAt($finishedAt);

        return $this->entityManager->updateClaimedImportJobRunResult($importJobRun);
    }
}

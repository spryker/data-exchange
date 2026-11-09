<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Business\ImportJobRun\Writer;

use DateTime;
use Generated\Shared\Transfer\ErrorTransfer;
use Generated\Shared\Transfer\ImportJobConditionsTransfer;
use Generated\Shared\Transfer\ImportJobCriteriaTransfer;
use Generated\Shared\Transfer\ImportJobRunCollectionRequestTransfer;
use Generated\Shared\Transfer\ImportJobRunCollectionResponseTransfer;
use Generated\Shared\Transfer\ImportJobRunErrorTransfer;
use Generated\Shared\Transfer\ImportJobRunTransfer;
use Spryker\Shared\Log\LoggerTrait;
use Spryker\Zed\DataExchange\DataExchangeConfig;
use Spryker\Zed\DataExchange\Persistence\DataExchangeEntityManagerInterface;
use Spryker\Zed\DataExchange\Persistence\DataExchangeRepositoryInterface;
use Spryker\Zed\Kernel\Persistence\EntityManager\TransactionTrait;
use Throwable;

class ImportJobRunWriter implements ImportJobRunWriterInterface
{
    use LoggerTrait;
    use TransactionTrait;

    protected const string DATETIME_FORMAT = 'Y-m-d H:i:s';

    protected const string ERROR_MESSAGE_RUN_NO_LONGER_RECEIVING = 'Import job run #%d is no longer receiving its file.';

    protected const string ERROR_MESSAGE_FILE_NOT_STORED = 'The uploaded file could not be stored.';

    public function __construct(
        protected ImportFileWriterInterface $fileWriter,
        protected DataExchangeEntityManagerInterface $entityManager,
        protected DataExchangeRepositoryInterface $repository,
        protected DataExchangeConfig $config,
    ) {
    }

    public function createImportJobRunCollection(ImportJobRunCollectionRequestTransfer $collectionRequestTransfer): ImportJobRunCollectionResponseTransfer
    {
        $response = new ImportJobRunCollectionResponseTransfer();

        foreach ($collectionRequestTransfer->getImportJobRuns() as $importJobRunTransfer) {
            $criteriaTransfer = (new ImportJobCriteriaTransfer())
                ->setImportJobConditions(
                    (new ImportJobConditionsTransfer())->addReference($importJobRunTransfer->getImportJobReferenceOrFail()),
                );

            $importJob = $this->repository->getImportJobCollection($criteriaTransfer)->getImportJobs()->getIterator()->current();

            if ($importJob === null) {
                $response->addError(
                    (new ErrorTransfer())->setMessage(
                        sprintf('Import job with reference "%s" not found.', $importJobRunTransfer->getImportJobReference()),
                    ),
                );

                continue;
            }

            $importJobRunTransfer = $this->entityManager->createImportJobRun(
                $importJobRunTransfer
                    ->setFkImportJob($importJob->getIdImportJobOrFail())
                    ->setStatus($this->config->getImportStatusReceiving()),
            );

            $errorTransfer = $this->storeUploadedFile($importJobRunTransfer, $importJob->getTypeOrFail());

            if ($errorTransfer !== null) {
                $this->failReceivingImportJobRun($importJobRunTransfer, $errorTransfer);
                $response->addError($errorTransfer);

                continue;
            }

            $importJobRunTransfer
                ->setStatus($this->config->getImportStatusPending())
                ->setUpdatedAt((new DateTime())->format(static::DATETIME_FORMAT));

            if (!$this->entityManager->updateReceivingImportJobRun($importJobRunTransfer)) {
                $response->addError(
                    (new ErrorTransfer())->setMessage(
                        sprintf(static::ERROR_MESSAGE_RUN_NO_LONGER_RECEIVING, $importJobRunTransfer->getIdImportJobRunOrFail()),
                    ),
                );

                continue;
            }

            $response->addImportJobRun($importJobRunTransfer);
        }

        return $response;
    }

    protected function failReceivingImportJobRun(ImportJobRunTransfer $importJobRunTransfer, ErrorTransfer $errorTransfer): void
    {
        $failedAt = (new DateTime())->format(static::DATETIME_FORMAT);

        $importJobRunTransfer
            ->setStatus($this->config->getImportStatusFailed())
            ->setImportFinishedAt($failedAt)
            ->setUpdatedAt($failedAt);

        try {
            $this->getTransactionHandler()->handleTransaction(function () use ($importJobRunTransfer, $errorTransfer): void {
                $this->executeFailReceivingImportJobRunTransaction($importJobRunTransfer, $errorTransfer);
            });
        } catch (Throwable $exception) {
            $this->getLogger()->error(
                sprintf('Import job run #%d could not be marked failed after its file could not be stored: %s', $importJobRunTransfer->getIdImportJobRunOrFail(), $exception->getMessage()),
                ['exception' => $exception],
            );
        }
    }

    protected function executeFailReceivingImportJobRunTransaction(ImportJobRunTransfer $importJobRunTransfer, ErrorTransfer $errorTransfer): void
    {
        if (!$this->entityManager->updateReceivingImportJobRun($importJobRunTransfer)) {
            return;
        }

        $this->entityManager->createImportJobRunError(
            (new ImportJobRunErrorTransfer())
                ->setFkImportJobRun($importJobRunTransfer->getIdImportJobRunOrFail())
                ->setErrorMessage($errorTransfer->getMessageOrFail()),
        );
    }

    protected function storeUploadedFile(ImportJobRunTransfer $importJobRunTransfer, string $jobType): ?ErrorTransfer
    {
        $importJobRunFileInfoTransfer = $importJobRunTransfer->getFileInfoOrFail();

        if (!$importJobRunFileInfoTransfer->getUploadedFilePath()) {
            return null;
        }

        try {
            $importJobRunTransfer->setFileInfo($this->fileWriter->writeFile($importJobRunFileInfoTransfer, $jobType));
        } catch (Throwable $exception) {
            $this->getLogger()->error(
                sprintf('The uploaded file of import job run #%d could not be stored: %s', $importJobRunTransfer->getIdImportJobRunOrFail(), $exception->getMessage()),
                ['exception' => $exception],
            );

            return (new ErrorTransfer())->setMessage(static::ERROR_MESSAGE_FILE_NOT_STORED);
        }

        return null;
    }
}

<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Business\ImportJobRun\Writer;

use DateTime;
use Generated\Shared\Transfer\ImportJobRunErrorTransfer;
use Generated\Shared\Transfer\ImportJobRunTransfer;
use Spryker\Shared\Log\LoggerTrait;
use Spryker\Zed\DataExchange\Business\ImportJobRun\Resolver\ImportJobRunClaimSettingsResolverInterface;
use Spryker\Zed\DataExchange\DataExchangeConfig;
use Spryker\Zed\DataExchange\Persistence\DataExchangeEntityManagerInterface;
use Spryker\Zed\DataExchange\Persistence\DataExchangeRepositoryInterface;
use Spryker\Zed\Kernel\Persistence\EntityManager\TransactionTrait;
use Throwable;

class ExhaustedImportJobRunWriter implements ExhaustedImportJobRunWriterInterface
{
    use LoggerTrait;
    use TransactionTrait;

    protected const string DATETIME_FORMAT = 'Y-m-d H:i:s';

    protected const string ERROR_MESSAGE_ATTEMPTS_EXHAUSTED = 'Import stopped after %d attempts: every attempt ended before the run was finished.';

    public function __construct(
        protected DataExchangeRepositoryInterface $repository,
        protected DataExchangeEntityManagerInterface $entityManager,
        protected DataExchangeConfig $config,
        protected ImportJobRunClaimSettingsResolverInterface $importJobRunClaimSettingsResolver,
    ) {
    }

    public function failExhaustedImportJobRuns(): void
    {
        $leaseCheckedAt = $this->importJobRunClaimSettingsResolver->getLeaseCheckedAt();
        $maxAttempts = $this->importJobRunClaimSettingsResolver->getMaxAttempts();
        $importJobRunCollectionTransfer = $this->repository->getExhaustedImportJobRunCollection(
            $leaseCheckedAt,
            $maxAttempts,
            $this->importJobRunClaimSettingsResolver->getExhaustedBatchSize(),
        );

        foreach ($importJobRunCollectionTransfer->getImportJobRuns() as $importJobRunTransfer) {
            $this->failExhaustedImportJobRun($importJobRunTransfer, $leaseCheckedAt, $maxAttempts);
        }
    }

    protected function failExhaustedImportJobRun(ImportJobRunTransfer $importJobRunTransfer, string $leaseCheckedAt, int $maxAttempts): void
    {
        try {
            $this->getTransactionHandler()->handleTransaction(function () use ($importJobRunTransfer, $leaseCheckedAt, $maxAttempts): void {
                $this->executeFailExhaustedImportJobRunTransaction($importJobRunTransfer, $leaseCheckedAt, $maxAttempts);
            });
        } catch (Throwable $exception) {
            $this->getLogger()->error(
                sprintf('Import job run #%d could not be marked failed after its attempts ran out: %s', $importJobRunTransfer->getIdImportJobRun(), $exception->getMessage()),
                ['exception' => $exception],
            );
        }
    }

    protected function executeFailExhaustedImportJobRunTransaction(ImportJobRunTransfer $importJobRunTransfer, string $leaseCheckedAt, int $maxAttempts): void
    {
        $finishedAt = (new DateTime())->format(static::DATETIME_FORMAT);

        $importJobRunTransfer
            ->setStatus($this->config->getImportStatusFailed())
            ->setImportFinishedAt($finishedAt)
            ->setUpdatedAt($finishedAt);

        if (!$this->entityManager->updateExhaustedImportJobRun($importJobRunTransfer, $leaseCheckedAt, $maxAttempts)) {
            return;
        }

        $errorMessage = sprintf(static::ERROR_MESSAGE_ATTEMPTS_EXHAUSTED, $importJobRunTransfer->getAttemptCountOrFail());

        $this->entityManager->createImportJobRunError(
            (new ImportJobRunErrorTransfer())
                ->setFkImportJobRun($importJobRunTransfer->getIdImportJobRunOrFail())
                ->setErrorMessage($errorMessage),
        );

        $this->getLogger()->error(sprintf('Import job run #%d failed: %s', $importJobRunTransfer->getIdImportJobRunOrFail(), $errorMessage));
    }
}

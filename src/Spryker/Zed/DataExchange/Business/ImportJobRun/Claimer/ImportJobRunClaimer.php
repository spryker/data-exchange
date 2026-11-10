<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Business\ImportJobRun\Claimer;

use DateTime;
use Generated\Shared\Transfer\ImportJobRunErrorCollectionDeleteCriteriaTransfer;
use Generated\Shared\Transfer\ImportJobRunErrorConditionsTransfer;
use Generated\Shared\Transfer\ImportJobRunTransfer;
use Spryker\Zed\DataExchange\Business\ImportJobRun\Resolver\ImportJobRunClaimSettingsResolverInterface;
use Spryker\Zed\DataExchange\DataExchangeConfig;
use Spryker\Zed\DataExchange\Persistence\DataExchangeEntityManagerInterface;
use Spryker\Zed\DataExchange\Persistence\DataExchangeRepositoryInterface;
use Spryker\Zed\Kernel\Persistence\EntityManager\TransactionTrait;

class ImportJobRunClaimer implements ImportJobRunClaimerInterface
{
    use TransactionTrait;

    protected const string DATETIME_FORMAT = 'Y-m-d H:i:s';

    protected const string CLAIMED_BY_SEPARATOR = ':';

    protected const string UNKNOWN_HOST = 'unknown-host';

    /**
     * @var int<1, max>
     */
    protected const int CLAIM_TOKEN_LENGTH_IN_BYTES = 8;

    public function __construct(
        protected DataExchangeRepositoryInterface $repository,
        protected DataExchangeEntityManagerInterface $entityManager,
        protected DataExchangeConfig $config,
        protected ImportJobRunClaimSettingsResolverInterface $importJobRunClaimSettingsResolver,
    ) {
    }

    public function claimNextImportJobRun(): ?ImportJobRunTransfer
    {
        $claimedAt = new DateTime();
        $leaseCheckedAt = $this->importJobRunClaimSettingsResolver->getLeaseCheckedAt();
        $maxAttempts = $this->importJobRunClaimSettingsResolver->getMaxAttempts();
        $importJobRunTransfer = $this->repository->findOldestClaimableImportJobRun($leaseCheckedAt, $maxAttempts);

        if ($importJobRunTransfer === null) {
            return null;
        }

        $importJobRunTransfer
            ->setStatus($this->config->getImportStatusProcessing())
            ->setClaimedBy($this->generateClaimedBy())
            ->setAttemptCount($importJobRunTransfer->getAttemptCountOrFail() + 1)
            ->setImportStartedAt($claimedAt->format(static::DATETIME_FORMAT))
            ->setLeaseExpiresAt($this->importJobRunClaimSettingsResolver->getLeaseExpiresAt())
            ->setNumberOfProcessedLines(0)
            ->setNumberOfSuccessfullyProcessedLines(0)
            ->setNumberOfFailedLines(0)
            ->setNumberOfOmittedErrorRows(0)
            ->setNumberOfProcessedBytes(null)
            ->setUpdatedAt($claimedAt->format(static::DATETIME_FORMAT));

        $isClaimed = $this->getTransactionHandler()->handleTransaction(function () use ($importJobRunTransfer, $leaseCheckedAt, $maxAttempts): bool {
            return $this->claimImportJobRun($importJobRunTransfer, $leaseCheckedAt, $maxAttempts);
        });

        return $isClaimed ? $importJobRunTransfer : null;
    }

    /**
     * Claims the run with the conditional UPDATE. A reclaimed run (attempt 2 or later) reads the file from the first row
     * again, so the error rows of the abandoned attempt are deleted with the claim; the counters were reset by the caller.
     */
    protected function claimImportJobRun(ImportJobRunTransfer $importJobRunTransfer, string $leaseCheckedAt, int $maxAttempts): bool
    {
        if (!$this->entityManager->updateClaimableImportJobRun($importJobRunTransfer, $leaseCheckedAt, $maxAttempts)) {
            return false;
        }

        if ($importJobRunTransfer->getAttemptCountOrFail() > 1) {
            $this->entityManager->deleteImportJobRunErrorCollection(
                (new ImportJobRunErrorCollectionDeleteCriteriaTransfer())->setImportJobRunErrorConditions(
                    (new ImportJobRunErrorConditionsTransfer())->addIdImportJobRun($importJobRunTransfer->getIdImportJobRunOrFail()),
                ),
            );
        }

        return true;
    }

    protected function generateClaimedBy(): string
    {
        return (gethostname() ?: static::UNKNOWN_HOST)
            . static::CLAIMED_BY_SEPARATOR . getmypid()
            . static::CLAIMED_BY_SEPARATOR . bin2hex(random_bytes(static::CLAIM_TOKEN_LENGTH_IN_BYTES));
    }
}

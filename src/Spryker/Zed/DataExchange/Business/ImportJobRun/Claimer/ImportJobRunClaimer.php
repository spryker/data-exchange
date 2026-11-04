<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Business\ImportJobRun\Claimer;

use DateTime;
use DateTimeZone;
use Generated\Shared\Transfer\ImportJobRunTransfer;
use Spryker\Zed\DataExchange\DataExchangeConfig;
use Spryker\Zed\DataExchange\Persistence\DataExchangeEntityManagerInterface;
use Spryker\Zed\DataExchange\Persistence\DataExchangeRepositoryInterface;

class ImportJobRunClaimer implements ImportJobRunClaimerInterface
{
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
    ) {
    }

    public function claimNextImportJobRun(): ?ImportJobRunTransfer
    {
        $claimedAt = new DateTime();
        $leaseCheckedAt = new DateTime('now', new DateTimeZone($this->config->getImportJobRunLeaseTimeZone()));
        $maxAttempts = max(1, $this->config->getImportJobRunMaxAttempts());
        $importJobRunTransfer = $this->repository->findOldestClaimableImportJobRun($leaseCheckedAt->format(static::DATETIME_FORMAT), $maxAttempts);

        if ($importJobRunTransfer === null) {
            return null;
        }

        $leaseExpiresAt = (clone $leaseCheckedAt)->modify(sprintf('+%d minutes', max(1, $this->config->getImportJobRunLeaseDurationInMinutes())));

        $importJobRunTransfer
            ->setStatus($this->config->getImportStatusProcessing())
            ->setClaimedBy($this->generateClaimedBy())
            ->setAttemptCount($importJobRunTransfer->getAttemptCountOrFail() + 1)
            ->setImportStartedAt($claimedAt->format(static::DATETIME_FORMAT))
            ->setLeaseExpiresAt($leaseExpiresAt->format(static::DATETIME_FORMAT))
            ->setNumberOfProcessedLines(0)
            ->setNumberOfSuccessfullyProcessedLines(0)
            ->setNumberOfFailedLines(0)
            ->setNumberOfProcessedBytes(null)
            ->setUpdatedAt($claimedAt->format(static::DATETIME_FORMAT));

        if (!$this->entityManager->updateClaimableImportJobRun($importJobRunTransfer, $leaseCheckedAt->format(static::DATETIME_FORMAT), $maxAttempts)) {
            return null;
        }

        return $importJobRunTransfer;
    }

    protected function generateClaimedBy(): string
    {
        return (gethostname() ?: static::UNKNOWN_HOST)
            . static::CLAIMED_BY_SEPARATOR . getmypid()
            . static::CLAIMED_BY_SEPARATOR . bin2hex(random_bytes(static::CLAIM_TOKEN_LENGTH_IN_BYTES));
    }
}

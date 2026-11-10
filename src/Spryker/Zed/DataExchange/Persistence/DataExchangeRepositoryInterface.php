<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Persistence;

use Generated\Shared\Transfer\ImportJobCollectionTransfer;
use Generated\Shared\Transfer\ImportJobCriteriaTransfer;
use Generated\Shared\Transfer\ImportJobRunCollectionTransfer;
use Generated\Shared\Transfer\ImportJobRunCriteriaTransfer;
use Generated\Shared\Transfer\ImportJobRunErrorCollectionResponseTransfer;
use Generated\Shared\Transfer\ImportJobRunErrorCriteriaTransfer;
use Generated\Shared\Transfer\ImportJobRunTransfer;

interface DataExchangeRepositoryInterface
{
    public function getImportJobCollection(ImportJobCriteriaTransfer $criteriaTransfer): ImportJobCollectionTransfer;

    public function getImportJobRunCollection(ImportJobRunCriteriaTransfer $criteriaTransfer): ImportJobRunCollectionTransfer;

    public function findOldestClaimableImportJobRun(string $leaseCheckedAt, int $maxAttempts): ?ImportJobRunTransfer;

    public function getExhaustedImportJobRunCollection(string $leaseCheckedAt, int $maxAttempts, int $limit): ImportJobRunCollectionTransfer;

    public function getImportJobRunErrorCollection(ImportJobRunErrorCriteriaTransfer $criteriaTransfer): ImportJobRunErrorCollectionResponseTransfer;
}

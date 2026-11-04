<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Business\ImportJobRun\Reader;

use Generated\Shared\Transfer\ImportJobRunCollectionTransfer;
use Generated\Shared\Transfer\ImportJobRunCriteriaTransfer;
use Generated\Shared\Transfer\ImportJobRunTransfer;
use Spryker\Zed\DataExchange\Persistence\DataExchangeRepositoryInterface;

class ImportJobRunReader implements ImportJobRunReaderInterface
{
    protected const float MAX_PROGRESS_PERCENTAGE = 100.0;

    protected const int PROGRESS_PERCENTAGE_PRECISION = 2;

    public function __construct(protected DataExchangeRepositoryInterface $repository)
    {
    }

    public function getImportJobRunCollection(ImportJobRunCriteriaTransfer $importJobRunCriteriaTransfer): ImportJobRunCollectionTransfer
    {
        $importJobRunCollectionTransfer = $this->repository->getImportJobRunCollection($importJobRunCriteriaTransfer);

        foreach ($importJobRunCollectionTransfer->getImportJobRuns() as $importJobRunTransfer) {
            $this->expandImportJobRun($importJobRunTransfer);
        }

        return $importJobRunCollectionTransfer;
    }

    protected function expandImportJobRun(ImportJobRunTransfer $importJobRunTransfer): ImportJobRunTransfer
    {
        $importJobRunTransfer->setProgressPercentage($this->calculateProgressPercentage($importJobRunTransfer));

        return $importJobRunTransfer;
    }

    protected function calculateProgressPercentage(ImportJobRunTransfer $importJobRunTransfer): ?float
    {
        $numberOfProcessedBytes = $importJobRunTransfer->getNumberOfProcessedBytes();
        $fileSize = $importJobRunTransfer->getFileInfo()?->getSize();

        if ($numberOfProcessedBytes === null || !$fileSize) {
            return null;
        }

        $progressPercentage = $numberOfProcessedBytes / $fileSize * static::MAX_PROGRESS_PERCENTAGE;

        return round(min(static::MAX_PROGRESS_PERCENTAGE, $progressPercentage), static::PROGRESS_PERCENTAGE_PRECISION);
    }
}

<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Persistence;

use Generated\Shared\Transfer\ImportJobRunErrorCollectionDeleteCriteriaTransfer;
use Generated\Shared\Transfer\ImportJobRunErrorTransfer;
use Generated\Shared\Transfer\ImportJobRunTransfer;
use Generated\Shared\Transfer\ImportJobTransfer;
use Orm\Zed\DataExchange\Persistence\SpyImportJob;
use Orm\Zed\DataExchange\Persistence\SpyImportJobRun;
use Orm\Zed\DataExchange\Persistence\SpyImportJobRunError;
use Spryker\Zed\Kernel\Persistence\AbstractEntityManager;

/**
 * @method \Spryker\Zed\DataExchange\Persistence\DataExchangePersistenceFactory getFactory()
 */
class DataExchangeEntityManager extends AbstractEntityManager implements DataExchangeEntityManagerInterface
{
    public function createImportJob(ImportJobTransfer $importJobTransfer): ImportJobTransfer
    {
        $importJobEntity = $this->getFactory()->createMapper()->mapImportJobTransferToEntity($importJobTransfer, new SpyImportJob());
        $importJobEntity->save();

        return $importJobTransfer->setIdImportJob($importJobEntity->getIdImportJob());
    }

    public function updateImportJob(ImportJobTransfer $importJobTransfer): ImportJobTransfer
    {
        $importJobEntity = $this->getFactory()
            ->createImportJobQuery()
            ->findPk($importJobTransfer->getIdImportJobOrFail());

        $importJobEntity = $this->getFactory()->createMapper()->mapImportJobTransferToEntity($importJobTransfer, $importJobEntity);
        $importJobEntity->save();

        return $importJobTransfer;
    }

    public function createImportJobRun(ImportJobRunTransfer $importJobRunTransfer): ImportJobRunTransfer
    {
        $importJobRunEntity = $this->getFactory()->createMapper()->mapImportJobRunTransferToEntity($importJobRunTransfer, new SpyImportJobRun());
        $importJobRunEntity->save();

        return $importJobRunTransfer->setIdImportJobRun($importJobRunEntity->getIdImportJobRun());
    }

    public function updateReceivingImportJobRun(ImportJobRunTransfer $importJobRunTransfer): bool
    {
        $affectedRowCount = $this->getFactory()
            ->createImportJobRunQuery()
            ->filterByIdImportJobRun($importJobRunTransfer->getIdImportJobRunOrFail())
            ->filterByStatus($this->getFactory()->getConfig()->getImportStatusReceiving())
            ->update([
                'Status' => $importJobRunTransfer->getStatusOrFail(),
                'FileInfo' => $this->getFactory()->createMapper()->mapImportJobRunFileInfoTransferToJson($importJobRunTransfer->getFileInfoOrFail()),
                'ImportFinishedAt' => $importJobRunTransfer->getImportFinishedAt(),
                'UpdatedAt' => $importJobRunTransfer->getUpdatedAtOrFail(),
            ]);

        return $affectedRowCount === 1;
    }

    public function updateClaimableImportJobRun(ImportJobRunTransfer $importJobRunTransfer, string $leaseCheckedAt, int $maxAttempts): bool
    {
        $importJobRunQuery = $this->getFactory()
            ->createImportJobRunQuery()
            ->filterByIdImportJobRun($importJobRunTransfer->getIdImportJobRunOrFail());

        $affectedRowCount = $this->getFactory()
            ->createImportJobRunQueryExpander()
            ->expandWithClaimableCondition($importJobRunQuery, $leaseCheckedAt, $maxAttempts)
            ->update([
                'Status' => $importJobRunTransfer->getStatusOrFail(),
                'ClaimedBy' => $importJobRunTransfer->getClaimedByOrFail(),
                'LeaseExpiresAt' => $importJobRunTransfer->getLeaseExpiresAtOrFail(),
                'AttemptCount' => $importJobRunTransfer->getAttemptCountOrFail(),
                'ImportStartedAt' => $importJobRunTransfer->getImportStartedAtOrFail(),
                'NumberOfProcessedLines' => $importJobRunTransfer->getNumberOfProcessedLinesOrFail(),
                'NumberOfSuccessfullyProcessedLines' => $importJobRunTransfer->getNumberOfSuccessfullyProcessedLinesOrFail(),
                'NumberOfFailedLines' => $importJobRunTransfer->getNumberOfFailedLinesOrFail(),
                'NumberOfOmittedErrorRows' => $importJobRunTransfer->getNumberOfOmittedErrorRowsOrFail(),
                'NumberOfProcessedBytes' => $importJobRunTransfer->getNumberOfProcessedBytes(),
                'UpdatedAt' => $importJobRunTransfer->getUpdatedAtOrFail(),
            ]);

        return $affectedRowCount === 1;
    }

    public function updateClaimedImportJobRun(ImportJobRunTransfer $importJobRunTransfer): bool
    {
        $affectedRowCount = $this->getFactory()
            ->createImportJobRunQuery()
            ->filterByIdImportJobRun($importJobRunTransfer->getIdImportJobRunOrFail())
            ->filterByClaimedBy($importJobRunTransfer->getClaimedByOrFail())
            ->filterByStatus($this->getFactory()->getConfig()->getImportStatusProcessing())
            ->update([
                'Status' => $importJobRunTransfer->getStatusOrFail(),
                'LeaseExpiresAt' => $importJobRunTransfer->getLeaseExpiresAtOrFail(),
                'ImportFinishedAt' => $importJobRunTransfer->getImportFinishedAt(),
                'NumberOfProcessedLines' => $importJobRunTransfer->getNumberOfProcessedLinesOrFail(),
                'NumberOfSuccessfullyProcessedLines' => $importJobRunTransfer->getNumberOfSuccessfullyProcessedLinesOrFail(),
                'NumberOfFailedLines' => $importJobRunTransfer->getNumberOfFailedLinesOrFail(),
                'NumberOfOmittedErrorRows' => $importJobRunTransfer->getNumberOfOmittedErrorRowsOrFail(),
                'NumberOfProcessedBytes' => $importJobRunTransfer->getNumberOfProcessedBytes(),
                'UpdatedAt' => $importJobRunTransfer->getUpdatedAtOrFail(),
            ]);

        return $affectedRowCount === 1;
    }

    public function updateExhaustedImportJobRun(ImportJobRunTransfer $importJobRunTransfer, string $leaseCheckedAt, int $maxAttempts): bool
    {
        $importJobRunQuery = $this->getFactory()
            ->createImportJobRunQuery()
            ->filterByIdImportJobRun($importJobRunTransfer->getIdImportJobRunOrFail());

        $affectedRowCount = $this->getFactory()
            ->createImportJobRunQueryExpander()
            ->expandWithExhaustedCondition($importJobRunQuery, $leaseCheckedAt, $maxAttempts)
            ->update([
                'Status' => $importJobRunTransfer->getStatusOrFail(),
                'ImportFinishedAt' => $importJobRunTransfer->getImportFinishedAtOrFail(),
                'UpdatedAt' => $importJobRunTransfer->getUpdatedAtOrFail(),
            ]);

        return $affectedRowCount === 1;
    }

    public function createImportJobRunError(ImportJobRunErrorTransfer $importJobRunErrorTransfer): ImportJobRunErrorTransfer
    {
        $importJobRunErrorEntity = (new SpyImportJobRunError())
            ->setFkImportJobRun($importJobRunErrorTransfer->getFkImportJobRunOrFail())
            ->setCsvRowNumber($importJobRunErrorTransfer->getCsvRowNumber())
            ->setErrorMessage($importJobRunErrorTransfer->getErrorMessageOrFail());

        $importJobRunErrorEntity->save();

        return $importJobRunErrorTransfer->setIdImportJobRunError($importJobRunErrorEntity->getIdImportJobRunError());
    }

    public function deleteImportJobRunErrorCollection(
        ImportJobRunErrorCollectionDeleteCriteriaTransfer $importJobRunErrorCollectionDeleteCriteriaTransfer
    ): void {
        $importJobRunIds = $importJobRunErrorCollectionDeleteCriteriaTransfer->getImportJobRunErrorConditions()?->getImportJobRunIds() ?? [];

        if ($importJobRunIds === []) {
            return;
        }

        $this->getFactory()
            ->createImportJobRunErrorQuery()
            ->filterByFkImportJobRun_In($importJobRunIds)
            ->delete();
    }
}

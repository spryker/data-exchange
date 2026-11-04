<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\Shared\DataExchange\Helper;

use Codeception\Module;
use Generated\Shared\Transfer\ImportJobRunFileInfoTransfer;
use Generated\Shared\Transfer\ImportJobRunTransfer;
use Generated\Shared\Transfer\ImportJobTransfer;
use Orm\Zed\DataExchange\Persistence\SpyImportJob;
use Orm\Zed\DataExchange\Persistence\SpyImportJobRun;
use Orm\Zed\DataExchange\Persistence\SpyImportJobRunError;
use Orm\Zed\DataExchange\Persistence\SpyImportJobRunErrorQuery;
use Orm\Zed\DataExchange\Persistence\SpyImportJobRunQuery;
use SprykerTest\Shared\Testify\Helper\DataCleanupHelperTrait;

class ImportJobHelper extends Module
{
    use DataCleanupHelperTrait;

    protected const string DATETIME_FORMAT = 'Y-m-d H:i:s';

    /**
     * @param array<string, mixed> $seedData
     */
    public function haveImportJob(array $seedData = []): ImportJobTransfer
    {
        $importJobEntity = new SpyImportJob();
        $importJobEntity->setName($seedData[ImportJobTransfer::NAME] ?? 'Test Job');
        $importJobEntity->setType($seedData[ImportJobTransfer::TYPE] ?? 'demo-csv');
        $importJobEntity->setReference($seedData[ImportJobTransfer::REFERENCE] ?? 'test-job-' . uniqid());
        $definition = $seedData[ImportJobTransfer::DEFINITION] ?? [];
        $importJobEntity->setDefinition(json_encode($definition));
        $importJobEntity->setDescription($seedData[ImportJobTransfer::DESCRIPTION] ?? null);
        $importJobEntity->save();

        $importJobTransfer = (new ImportJobTransfer())
            ->setIdImportJob($importJobEntity->getIdImportJob())
            ->setName($importJobEntity->getName())
            ->setType($importJobEntity->getType())
            ->setReference($importJobEntity->getReference())
            ->setDefinition($definition)
            ->setDescription($importJobEntity->getDescription());

        $this->getDataCleanupHelper()->_addCleanup(function () use ($importJobEntity): void {
            SpyImportJobRunErrorQuery::create()
                ->useSpyImportJobRunQuery()
                    ->filterByFkImportJob($importJobEntity->getIdImportJob())
                ->endUse()
                ->delete();

            SpyImportJobRunQuery::create()
                ->filterByFkImportJob($importJobEntity->getIdImportJob())
                ->delete();

            $importJobEntity->delete();
        });

        return $importJobTransfer;
    }

    /**
     * @param array<string, mixed> $seedData
     */
    public function haveImportJobRun(array $seedData = []): ImportJobRunTransfer
    {
        $importJobRunEntity = new SpyImportJobRun();
        $importJobRunEntity->setFkImportJob($seedData[ImportJobRunTransfer::FK_IMPORT_JOB] ?? $seedData['fkImportJob']);
        $importJobRunEntity->setStatus($seedData[ImportJobRunTransfer::STATUS] ?? 'pending');
        $importJobRunEntity->setNumberOfProcessedLines($seedData[ImportJobRunTransfer::NUMBER_OF_PROCESSED_LINES] ?? 0);
        $importJobRunEntity->setNumberOfSuccessfullyProcessedLines($seedData[ImportJobRunTransfer::NUMBER_OF_SUCCESSFULLY_PROCESSED_LINES] ?? 0);
        $importJobRunEntity->setNumberOfFailedLines($seedData[ImportJobRunTransfer::NUMBER_OF_FAILED_LINES] ?? 0);
        $importJobRunEntity->setNumberOfProcessedBytes($seedData[ImportJobRunTransfer::NUMBER_OF_PROCESSED_BYTES] ?? null);
        $importJobRunEntity->setClaimedBy($seedData[ImportJobRunTransfer::CLAIMED_BY] ?? null);
        $importJobRunEntity->setLeaseExpiresAt($seedData[ImportJobRunTransfer::LEASE_EXPIRES_AT] ?? null);
        $importJobRunEntity->setAttemptCount($seedData[ImportJobRunTransfer::ATTEMPT_COUNT] ?? 0);
        $importJobRunEntity->setImportStartedAt($seedData[ImportJobRunTransfer::IMPORT_STARTED_AT] ?? null);
        $importJobRunEntity->setImportFinishedAt($seedData[ImportJobRunTransfer::IMPORT_FINISHED_AT] ?? null);
        $importJobRunEntity->setCreatedAt($seedData[ImportJobRunTransfer::CREATED_AT] ?? null);

        $fileInfo = $seedData[ImportJobRunTransfer::FILE_INFO] ?? [];
        $importJobRunEntity->setFileInfo(json_encode($fileInfo));

        $importJobRunEntity->save();

        $importJobRunTransfer = (new ImportJobRunTransfer())
            ->setIdImportJobRun($importJobRunEntity->getIdImportJobRun())
            ->setFkImportJob($importJobRunEntity->getFkImportJob())
            ->setStatus($importJobRunEntity->getStatus())
            ->setNumberOfProcessedLines($importJobRunEntity->getNumberOfProcessedLines())
            ->setNumberOfSuccessfullyProcessedLines($importJobRunEntity->getNumberOfSuccessfullyProcessedLines())
            ->setNumberOfFailedLines($importJobRunEntity->getNumberOfFailedLines())
            ->setNumberOfProcessedBytes($importJobRunEntity->getNumberOfProcessedBytes() !== null ? (int)$importJobRunEntity->getNumberOfProcessedBytes() : null)
            ->setFileInfo((new ImportJobRunFileInfoTransfer())->fromArray($fileInfo, true))
            ->setClaimedBy($importJobRunEntity->getClaimedBy())
            ->setLeaseExpiresAt($importJobRunEntity->getLeaseExpiresAt(static::DATETIME_FORMAT))
            ->setAttemptCount($importJobRunEntity->getAttemptCount())
            ->setImportStartedAt($importJobRunEntity->getImportStartedAt(static::DATETIME_FORMAT))
            ->setImportFinishedAt($importJobRunEntity->getImportFinishedAt(static::DATETIME_FORMAT))
            ->setCreatedAt($importJobRunEntity->getCreatedAt(static::DATETIME_FORMAT))
            ->setUpdatedAt($importJobRunEntity->getUpdatedAt(static::DATETIME_FORMAT));

        $this->getDataCleanupHelper()->_addCleanup(function () use ($importJobRunEntity): void {
            SpyImportJobRunErrorQuery::create()
                ->filterByFkImportJobRun($importJobRunEntity->getIdImportJobRun())
                ->delete();

            $importJobRunEntity->delete();
        });

        return $importJobRunTransfer;
    }

    /**
     * @param array<string, mixed> $seedData
     */
    public function haveImportJobRunError(int $idImportJobRun, array $seedData = []): void
    {
        $errorEntity = new SpyImportJobRunError();
        $errorEntity->setFkImportJobRun($idImportJobRun);
        $errorEntity->setCsvRowNumber($seedData['csvRowNumber'] ?? 1);
        $errorEntity->setErrorMessage($seedData['errorMessage'] ?? 'Test error message');
        $errorEntity->save();
    }
}

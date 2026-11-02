<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\Shared\DataExchange\Helper;

use Codeception\Module;
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

        $fileInfo = $seedData[ImportJobRunTransfer::FILE_INFO] ?? [];
        $importJobRunEntity->setFileInfo(json_encode($fileInfo));

        $importJobRunEntity->save();

        $importJobRunTransfer = (new ImportJobRunTransfer())
            ->setIdImportJobRun($importJobRunEntity->getIdImportJobRun())
            ->setFkImportJob($importJobRunEntity->getFkImportJob())
            ->setStatus($importJobRunEntity->getStatus());

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

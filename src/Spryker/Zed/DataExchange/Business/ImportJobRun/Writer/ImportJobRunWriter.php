<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Business\ImportJobRun\Writer;

use Generated\Shared\Transfer\ErrorTransfer;
use Generated\Shared\Transfer\ImportJobConditionsTransfer;
use Generated\Shared\Transfer\ImportJobCriteriaTransfer;
use Generated\Shared\Transfer\ImportJobRunCollectionRequestTransfer;
use Generated\Shared\Transfer\ImportJobRunCollectionResponseTransfer;
use Spryker\Zed\DataExchange\Persistence\DataExchangeEntityManagerInterface;
use Spryker\Zed\DataExchange\Persistence\DataExchangeRepositoryInterface;
use Throwable;

class ImportJobRunWriter implements ImportJobRunWriterInterface
{
    public function __construct(
        protected ImportFileWriterInterface $fileWriter,
        protected DataExchangeEntityManagerInterface $entityManager,
        protected DataExchangeRepositoryInterface $repository,
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

            $importJobRunTransfer->setFkImportJob($importJob->getIdImportJobOrFail());

            $fileInfo = $importJobRunTransfer->getFileInfoOrFail();

            if ($fileInfo->getUploadedFilePath()) {
                try {
                    $fileInfo = $this->fileWriter->writeFile($fileInfo, $importJob->getTypeOrFail());
                    $importJobRunTransfer->setFileInfo($fileInfo);
                } catch (Throwable $exception) {
                    $response->addError(
                        (new ErrorTransfer())->setMessage(
                            sprintf('Failed to store uploaded file: %s', $exception->getMessage()),
                        ),
                    );

                    continue;
                }
            }

            $persistedRun = $this->entityManager->createImportJobRun($importJobRunTransfer);
            $response->addImportJobRun($persistedRun);
        }

        return $response;
    }
}

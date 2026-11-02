<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Communication\Reader;

use Generated\Shared\Transfer\ImportJobConditionsTransfer;
use Generated\Shared\Transfer\ImportJobCriteriaTransfer;
use Generated\Shared\Transfer\ImportJobRunConditionsTransfer;
use Generated\Shared\Transfer\ImportJobRunCriteriaTransfer;
use Generated\Shared\Transfer\ImportJobRunTransfer;
use Generated\Shared\Transfer\ImportJobTransfer;
use Spryker\Zed\DataExchange\Business\DataExchangeFacadeInterface;

class ImportJobReader implements ImportJobReaderInterface
{
    public function __construct(protected DataExchangeFacadeInterface $dataExchangeFacade)
    {
    }

    public function findImportJobById(int $idImportJob): ?ImportJobTransfer
    {
        return $this->findImportJob(
            (new ImportJobCriteriaTransfer())->setImportJobConditions(
                (new ImportJobConditionsTransfer())->addIdImportJob($idImportJob),
            ),
        );
    }

    public function findImportJobByReference(string $importJobReference): ?ImportJobTransfer
    {
        return $this->findImportJob(
            (new ImportJobCriteriaTransfer())->setImportJobConditions(
                (new ImportJobConditionsTransfer())->addReference($importJobReference),
            ),
        );
    }

    public function findImportJobRun(int $idImportJobRun): ?ImportJobRunTransfer
    {
        return $this->dataExchangeFacade->getImportJobRunCollection(
            (new ImportJobRunCriteriaTransfer())->setImportJobRunConditions(
                (new ImportJobRunConditionsTransfer())->addIdImportJobRun($idImportJobRun),
            ),
        )->getImportJobRuns()->getIterator()->current() ?: null;
    }

    protected function findImportJob(ImportJobCriteriaTransfer $importJobCriteriaTransfer): ?ImportJobTransfer
    {
        return $this->dataExchangeFacade
            ->getImportJobCollection($importJobCriteriaTransfer)
            ->getImportJobs()
            ->getIterator()
            ->current() ?: null;
    }
}

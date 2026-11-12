<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Business\ImportJobRun\Pruner;

use DateTime;
use Generated\Shared\Transfer\ImportJobRunConditionsTransfer;
use Generated\Shared\Transfer\ImportJobRunCriteriaTransfer;
use Generated\Shared\Transfer\ImportJobRunDeleteResponseTransfer;
use Generated\Shared\Transfer\PaginationTransfer;
use Generated\Shared\Transfer\SortTransfer;
use Orm\Zed\DataExchange\Persistence\Map\SpyImportJobRunTableMap;
use Spryker\Zed\DataExchange\Business\ImportJobRun\Deleter\ImportJobRunDeleterInterface;
use Spryker\Zed\DataExchange\Business\ImportJobRun\Pruner\Exception\InvalidImportJobRunPruneConfigurationException;
use Spryker\Zed\DataExchange\DataExchangeConfig;
use Spryker\Zed\DataExchange\Persistence\DataExchangeRepositoryInterface;

class ImportJobRunPruner implements ImportJobRunPrunerInterface
{
    protected const string DATETIME_FORMAT = 'Y-m-d H:i:s';

    protected const int MINIMUM_CONFIGURED_VALUE = 1;

    protected const string ERROR_MESSAGE_INVALID_RETENTION = 'DataExchangeConfig::getImportJobRunRetentionInDays() must return at least %d, %d given.';

    protected const string ERROR_MESSAGE_INVALID_BATCH_SIZE = 'DataExchangeConfig::getImportJobRunPruneBatchSize() must return at least %d, %d given.';

    public function __construct(
        protected DataExchangeRepositoryInterface $repository,
        protected ImportJobRunDeleterInterface $importJobRunDeleter,
        protected DataExchangeConfig $config,
    ) {
    }

    public function pruneImportJobRuns(ImportJobRunCriteriaTransfer $importJobRunCriteriaTransfer): ImportJobRunDeleteResponseTransfer
    {
        $importJobRunCriteriaTransfer = $this->createPrunableImportJobRunCriteriaTransfer($importJobRunCriteriaTransfer);
        $importJobRunDeleteResponseTransfer = (new ImportJobRunDeleteResponseTransfer())->setDeletedImportJobRunCount(0);
        $importJobRunConditionsTransfer = $importJobRunCriteriaTransfer->getImportJobRunConditionsOrFail();
        $batchSize = $importJobRunCriteriaTransfer->getPaginationOrFail()->getLimitOrFail();

        do {
            $importJobRunTransfers = $this->repository
                ->getImportJobRunCollection($importJobRunCriteriaTransfer)
                ->getImportJobRuns();

            $importJobRunDeleteResponseTransfer = $this->importJobRunDeleter->deleteImportJobRuns($importJobRunTransfers, $importJobRunDeleteResponseTransfer);

            $importJobRunConditionsTransfer->setIdImportJobRunGreaterThan(
                $importJobRunTransfers->count() > 0
                    ? $importJobRunTransfers->offsetGet($importJobRunTransfers->count() - 1)?->getIdImportJobRunOrFail()
                    : null,
            );
        } while ($importJobRunTransfers->count() === $batchSize);

        return $importJobRunDeleteResponseTransfer;
    }

    protected function createPrunableImportJobRunCriteriaTransfer(ImportJobRunCriteriaTransfer $importJobRunCriteriaTransfer): ImportJobRunCriteriaTransfer
    {
        $retentionInDays = $this->config->getImportJobRunRetentionInDays();
        $batchSize = $this->config->getImportJobRunPruneBatchSize();

        $this->assertPruneConfigurationIsValid($retentionInDays, $batchSize);

        $requestedImportJobRunConditionsTransfer = $importJobRunCriteriaTransfer->getImportJobRunConditions() ?? new ImportJobRunConditionsTransfer();
        $importJobRunConditionsTransfer = (new ImportJobRunConditionsTransfer())
            ->setImportJobRunIds($requestedImportJobRunConditionsTransfer->getImportJobRunIds())
            ->setImportJobIds($requestedImportJobRunConditionsTransfer->getImportJobIds())
            ->setStatuses([...$this->config->getFinishedImportStatuses(), $this->config->getImportStatusReceiving()])
            ->setUpdatedBefore((new DateTime(sprintf('-%d days', $retentionInDays)))->format(static::DATETIME_FORMAT));

        return (new ImportJobRunCriteriaTransfer())
            ->setImportJobRunConditions($importJobRunConditionsTransfer)
            ->addSort((new SortTransfer())->setField(SpyImportJobRunTableMap::COL_ID_IMPORT_JOB_RUN)->setIsAscending(true))
            ->setPagination((new PaginationTransfer())->setLimit($batchSize));
    }

    /**
     * @throws \Spryker\Zed\DataExchange\Business\ImportJobRun\Pruner\Exception\InvalidImportJobRunPruneConfigurationException
     */
    protected function assertPruneConfigurationIsValid(int $retentionInDays, int $batchSize): void
    {
        if ($retentionInDays < static::MINIMUM_CONFIGURED_VALUE) {
            throw new InvalidImportJobRunPruneConfigurationException(
                sprintf(static::ERROR_MESSAGE_INVALID_RETENTION, static::MINIMUM_CONFIGURED_VALUE, $retentionInDays),
            );
        }

        if ($batchSize < static::MINIMUM_CONFIGURED_VALUE) {
            throw new InvalidImportJobRunPruneConfigurationException(
                sprintf(static::ERROR_MESSAGE_INVALID_BATCH_SIZE, static::MINIMUM_CONFIGURED_VALUE, $batchSize),
            );
        }
    }
}

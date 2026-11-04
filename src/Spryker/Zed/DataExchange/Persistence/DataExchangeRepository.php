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
use Generated\Shared\Transfer\ImportJobRunErrorCollectionTransfer;
use Generated\Shared\Transfer\ImportJobRunErrorCriteriaTransfer;
use Generated\Shared\Transfer\ImportJobRunErrorTransfer;
use Generated\Shared\Transfer\ImportJobRunTransfer;
use Orm\Zed\DataExchange\Persistence\SpyImportJobQuery;
use Orm\Zed\DataExchange\Persistence\SpyImportJobRunErrorQuery;
use Orm\Zed\DataExchange\Persistence\SpyImportJobRunQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Spryker\Zed\Kernel\Persistence\AbstractRepository;

/**
 * @method \Spryker\Zed\DataExchange\Persistence\DataExchangePersistenceFactory getFactory()
 */
class DataExchangeRepository extends AbstractRepository implements DataExchangeRepositoryInterface
{
    public function getImportJobCollection(ImportJobCriteriaTransfer $criteriaTransfer): ImportJobCollectionTransfer
    {
        $query = $this->getFactory()->createImportJobQuery();
        $mapper = $this->getFactory()->createMapper();

        $query = $this->applyImportJobConditions($query, $criteriaTransfer);

        $importJobCollectionTransfer = new ImportJobCollectionTransfer();

        /** @var \Propel\Runtime\Collection\ObjectCollection $result */
        $result = $query->find();

        foreach ($mapper->mapImportJobEntitiesToTransfers($result) as $transfer) {
            $importJobCollectionTransfer->addImportJob($transfer);
        }

        return $importJobCollectionTransfer;
    }

    public function getImportJobRunCollection(ImportJobRunCriteriaTransfer $criteriaTransfer): ImportJobRunCollectionTransfer
    {
        $query = $this->getFactory()->createImportJobRunQuery();
        $mapper = $this->getFactory()->createMapper();

        $query = $this->applyImportJobRunConditions($query, $criteriaTransfer);

        $entities = $query->orderByCreatedAt(Criteria::DESC)->find();

        $importJobRunCollectionTransfer = new ImportJobRunCollectionTransfer();

        foreach ($entities as $entity) {
            $importJobRunCollectionTransfer->addImportJobRun($mapper->mapImportJobRunEntityToTransfer($entity, new ImportJobRunTransfer()));
        }

        return $importJobRunCollectionTransfer;
    }

    public function findOldestClaimableImportJobRun(string $leaseCheckedAt, int $maxAttempts): ?ImportJobRunTransfer
    {
        $importJobRunQuery = $this->getFactory()->createImportJobRunQuery();

        $importJobRunEntity = $this->getFactory()
            ->createImportJobRunQueryExpander()
            ->expandWithClaimableCondition($importJobRunQuery, $leaseCheckedAt, $maxAttempts)
            ->orderByCreatedAt(Criteria::ASC)
            ->orderByIdImportJobRun(Criteria::ASC)
            ->findOne();

        if ($importJobRunEntity === null) {
            return null;
        }

        return $this->getFactory()->createMapper()->mapImportJobRunEntityToTransfer($importJobRunEntity, new ImportJobRunTransfer());
    }

    public function getImportJobRunErrorCollection(ImportJobRunErrorCriteriaTransfer $criteriaTransfer): ImportJobRunErrorCollectionTransfer
    {
        $query = $this->getFactory()->createImportJobRunErrorQuery();

        $query = $this->applyImportJobRunErrorConditions($query, $criteriaTransfer);

        $query->orderByCsvRowNumber(Criteria::ASC);

        $collection = new ImportJobRunErrorCollectionTransfer();

        foreach ($query->find() as $entity) {
            $collection->addImportJobRunError(
                $this->getFactory()->createMapper()->mapImportJobRunErrorEntityToTransfer($entity, new ImportJobRunErrorTransfer()),
            );
        }

        return $collection;
    }

    protected function applyImportJobConditions(SpyImportJobQuery $query, ImportJobCriteriaTransfer $criteriaTransfer): SpyImportJobQuery
    {
        $conditions = $criteriaTransfer->getImportJobConditions();

        if ($conditions === null) {
            return $query;
        }

        if ($conditions->getImportJobIds()) {
            $query->filterByIdImportJob_In($conditions->getImportJobIds());
        }

        if ($conditions->getReferences()) {
            $query->filterByReference_In($conditions->getReferences());
        }

        if ($conditions->getTypes()) {
            $query->filterByType_In($conditions->getTypes());
        }

        return $query;
    }

    protected function applyImportJobRunConditions(SpyImportJobRunQuery $query, ImportJobRunCriteriaTransfer $criteriaTransfer): SpyImportJobRunQuery
    {
        $conditions = $criteriaTransfer->getImportJobRunConditions();

        if ($conditions === null) {
            return $query;
        }

        if ($conditions->getImportJobRunIds()) {
            $query->filterByIdImportJobRun_In($conditions->getImportJobRunIds());
        }

        if ($conditions->getImportJobIds()) {
            $query->filterByFkImportJob_In($conditions->getImportJobIds());
        }

        if ($conditions->getStatuses()) {
            $query->filterByStatus_In($conditions->getStatuses());
        }

        return $query;
    }

    protected function applyImportJobRunErrorConditions(
        SpyImportJobRunErrorQuery $query,
        ImportJobRunErrorCriteriaTransfer $criteriaTransfer
    ): SpyImportJobRunErrorQuery {
        $conditions = $criteriaTransfer->getImportJobRunErrorConditions();

        if ($conditions === null || !$conditions->getImportJobRunIds()) {
            return $query;
        }

        $query->filterByFkImportJobRun_In($conditions->getImportJobRunIds());

        return $query;
    }
}

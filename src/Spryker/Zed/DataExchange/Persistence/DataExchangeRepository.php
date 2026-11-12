<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Persistence;

use ArrayObject;
use Generated\Shared\Transfer\ImportJobCollectionTransfer;
use Generated\Shared\Transfer\ImportJobCriteriaTransfer;
use Generated\Shared\Transfer\ImportJobRunCollectionTransfer;
use Generated\Shared\Transfer\ImportJobRunCriteriaTransfer;
use Generated\Shared\Transfer\ImportJobRunErrorCollectionResponseTransfer;
use Generated\Shared\Transfer\ImportJobRunErrorCriteriaTransfer;
use Generated\Shared\Transfer\ImportJobRunErrorTransfer;
use Generated\Shared\Transfer\ImportJobRunTransfer;
use Generated\Shared\Transfer\PaginationTransfer;
use Orm\Zed\DataExchange\Persistence\Map\SpyImportJobRunTableMap;
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

        $query = $this->applyImportJobRunConditions($query, $criteriaTransfer);
        $query = $this->applyImportJobRunSorting($query, $criteriaTransfer->getSortCollection());

        if ($criteriaTransfer->getPagination() !== null) {
            $query = $this->applyPagination($query, $criteriaTransfer->getPagination());
        }

        $entities = $query->find();

        return $this->getFactory()
            ->createMapper()
            ->mapImportJobRunEntitiesToImportJobRunCollectionTransfer($entities, new ImportJobRunCollectionTransfer());
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

    public function getExhaustedImportJobRunCollection(string $leaseCheckedAt, int $maxAttempts, int $limit): ImportJobRunCollectionTransfer
    {
        $importJobRunQuery = $this->getFactory()->createImportJobRunQuery();

        $importJobRunEntities = $this->getFactory()
            ->createImportJobRunQueryExpander()
            ->expandWithExhaustedCondition($importJobRunQuery, $leaseCheckedAt, $maxAttempts)
            ->orderByIdImportJobRun(Criteria::ASC)
            ->setLimit($limit)
            ->find();

        return $this->getFactory()
            ->createMapper()
            ->mapImportJobRunEntitiesToImportJobRunCollectionTransfer($importJobRunEntities, new ImportJobRunCollectionTransfer());
    }

    public function getImportJobRunErrorCollection(ImportJobRunErrorCriteriaTransfer $criteriaTransfer): ImportJobRunErrorCollectionResponseTransfer
    {
        $query = $this->getFactory()->createImportJobRunErrorQuery();

        $paginationTransfer = $criteriaTransfer->getPaginationOrFail();

        $query = $this->applyImportJobRunErrorConditions($query, $criteriaTransfer);

        $query->orderByCsvRowNumber(Criteria::ASC)->orderByIdImportJobRunError(Criteria::ASC);

        $importJobRunErrorCollectionResponseTransfer = (new ImportJobRunErrorCollectionResponseTransfer())
            ->setNumberOfOmittedErrorRows($this->getNumberOfOmittedErrorRows($criteriaTransfer))
            ->setPagination(
                (new PaginationTransfer())
                    ->setLimit($paginationTransfer->getLimitOrFail())
                    ->setOffset($paginationTransfer->getOffset() ?? 0)
                    ->setNbResults($query->count()),
            );

        $query->setLimit($paginationTransfer->getLimitOrFail())->setOffset($paginationTransfer->getOffset() ?? 0);

        foreach ($query->find() as $entity) {
            $importJobRunErrorCollectionResponseTransfer->addImportJobRunError(
                $this->getFactory()->createMapper()->mapImportJobRunErrorEntityToTransfer($entity, new ImportJobRunErrorTransfer()),
            );
        }

        return $importJobRunErrorCollectionResponseTransfer;
    }

    /**
     * Sums the omitted error rows of the runs the criteria selects, so the caller gets the counter together with the stored errors.
     */
    protected function getNumberOfOmittedErrorRows(ImportJobRunErrorCriteriaTransfer $criteriaTransfer): int
    {
        $importJobRunIds = $criteriaTransfer->getImportJobRunErrorConditions()?->getImportJobRunIds() ?? [];

        if ($importJobRunIds === []) {
            return 0;
        }

        $numbersOfOmittedErrorRows = $this->getFactory()
            ->createImportJobRunQuery()
            ->filterByIdImportJobRun_In($importJobRunIds)
            ->select([SpyImportJobRunTableMap::COL_NUMBER_OF_OMITTED_ERROR_ROWS])
            ->find()
            ->getData();

        return (int)array_sum(array_map('intval', $numbersOfOmittedErrorRows));
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

        if ($conditions->getUpdatedBefore() !== null) {
            $query->filterByUpdatedAt($conditions->getUpdatedBefore(), Criteria::LESS_THAN);
        }

        if ($conditions->getIdImportJobRunGreaterThan() !== null) {
            $query->filterByIdImportJobRun($conditions->getIdImportJobRunGreaterThan(), Criteria::GREATER_THAN);
        }

        return $query;
    }

    /**
     * @param \ArrayObject<int, \Generated\Shared\Transfer\SortTransfer> $sortTransfers
     */
    protected function applyImportJobRunSorting(SpyImportJobRunQuery $query, ArrayObject $sortTransfers): SpyImportJobRunQuery
    {
        if ($sortTransfers->count() === 0) {
            return $query->orderByCreatedAt(Criteria::DESC);
        }

        foreach ($sortTransfers as $sortTransfer) {
            $query->orderBy($sortTransfer->getFieldOrFail(), $sortTransfer->getIsAscending() ? Criteria::ASC : Criteria::DESC);
        }

        return $query;
    }

    protected function applyPagination(SpyImportJobRunQuery $query, PaginationTransfer $paginationTransfer): SpyImportJobRunQuery
    {
        if ($paginationTransfer->getLimit() !== null) {
            $query->setLimit($paginationTransfer->getLimit());
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

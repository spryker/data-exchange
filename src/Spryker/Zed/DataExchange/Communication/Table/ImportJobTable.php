<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Communication\Table;

use Orm\Zed\DataExchange\Persistence\Map\SpyImportJobRunTableMap;
use Orm\Zed\DataExchange\Persistence\Map\SpyImportJobTableMap;
use Orm\Zed\DataExchange\Persistence\SpyImportJobQuery;
use Spryker\Service\UtilText\Model\Url\Url;
use Spryker\Zed\DataExchange\DataExchangeConfig;
use Spryker\Zed\Gui\Communication\Table\AbstractTable;
use Spryker\Zed\Gui\Communication\Table\TableConfiguration;

class ImportJobTable extends AbstractTable
{
    protected const string COL_ID = SpyImportJobTableMap::COL_ID_IMPORT_JOB;

    protected const string COL_NAME = SpyImportJobTableMap::COL_NAME;

    protected const string COL_TYPE = SpyImportJobTableMap::COL_TYPE;

    protected const string COL_DESCRIPTION = SpyImportJobTableMap::COL_DESCRIPTION;

    protected const string COL_REFERENCE = SpyImportJobTableMap::COL_REFERENCE;

    protected const string COL_SUCCESS_RUNS = 'success_runs';

    protected const string COL_PROCESSING_RUNS = 'processing_runs';

    protected const string COL_PENDING_RUNS = 'pending_runs';

    protected const string COL_FAILED_RUNS = 'failed_runs';

    protected const string COL_CREATED_AT = SpyImportJobTableMap::COL_CREATED_AT;

    protected const string COL_ACTIONS = 'actions';

    protected const string ROUTE_RUN_INDEX = '/data-exchange/run/index';

    protected const string ROUTE_RUN_CREATE = '/data-exchange/run/create';

    public function __construct(
        protected SpyImportJobQuery $query,
        protected DataExchangeConfig $dataExchangeConfig,
    ) {
    }

    protected function configure(TableConfiguration $config): TableConfiguration
    {
        $config->setHeader([
            static::COL_ID => '#',
            static::COL_NAME => 'Name',
            static::COL_REFERENCE => 'Reference',
            static::COL_TYPE => 'Type',
            static::COL_DESCRIPTION => 'Description',
            static::COL_SUCCESS_RUNS => 'Success Runs',
            static::COL_PROCESSING_RUNS => 'Processing Runs',
            static::COL_PENDING_RUNS => 'Pending Runs',
            static::COL_FAILED_RUNS => 'Failed Runs',
            static::COL_ACTIONS => 'Actions',
        ]);

        $config->setSearchable([
            static::COL_NAME,
            static::COL_REFERENCE,
            static::COL_TYPE,
        ]);

        $config->setSortable([
            static::COL_ID,
            static::COL_NAME,
            static::COL_REFERENCE,
            static::COL_TYPE,
        ]);

        $config->setRawColumns([static::COL_ACTIONS]);
        $config->setDefaultSortField(static::COL_ID, TableConfiguration::SORT_DESC);

        return $config;
    }

    /**
     * @return array<array<string, mixed>>
     */
    protected function prepareData(TableConfiguration $config): array
    {
        $this->addRunCountSubqueries();

        $queryResults = $this->runQuery($this->query, $config);
        $results = [];

        foreach ($queryResults as $item) {
            $results[] = [
                static::COL_ID => $item[static::COL_ID],
                static::COL_NAME => $item[static::COL_NAME],
                static::COL_REFERENCE => $item[static::COL_REFERENCE],
                static::COL_TYPE => $item[static::COL_TYPE],
                static::COL_DESCRIPTION => $item[static::COL_DESCRIPTION],
                static::COL_SUCCESS_RUNS => (int)$item[static::COL_SUCCESS_RUNS],
                static::COL_PROCESSING_RUNS => (int)$item[static::COL_PROCESSING_RUNS],
                static::COL_PENDING_RUNS => (int)$item[static::COL_PENDING_RUNS],
                static::COL_FAILED_RUNS => (int)$item[static::COL_FAILED_RUNS],
                static::COL_ACTIONS => $this->buildActionButtons($item),
            ];
        }

        return $results;
    }

    protected function addRunCountSubqueries(): void
    {
        $this->query
            ->withColumn(
                sprintf(
                    '(SELECT COUNT(*) FROM %s WHERE %s = %s AND %s = \'%s\')',
                    SpyImportJobRunTableMap::TABLE_NAME,
                    SpyImportJobRunTableMap::COL_FK_IMPORT_JOB,
                    SpyImportJobTableMap::COL_ID_IMPORT_JOB,
                    SpyImportJobRunTableMap::COL_STATUS,
                    $this->dataExchangeConfig->getImportStatusDone(),
                ),
                static::COL_SUCCESS_RUNS,
            )
            ->withColumn(
                sprintf(
                    '(SELECT COUNT(*) FROM %s WHERE %s = %s AND %s = \'%s\')',
                    SpyImportJobRunTableMap::TABLE_NAME,
                    SpyImportJobRunTableMap::COL_FK_IMPORT_JOB,
                    SpyImportJobTableMap::COL_ID_IMPORT_JOB,
                    SpyImportJobRunTableMap::COL_STATUS,
                    $this->dataExchangeConfig->getImportStatusProcessing(),
                ),
                static::COL_PROCESSING_RUNS,
            )
            ->withColumn(
                sprintf(
                    '(SELECT COUNT(*) FROM %s WHERE %s = %s AND %s = \'%s\')',
                    SpyImportJobRunTableMap::TABLE_NAME,
                    SpyImportJobRunTableMap::COL_FK_IMPORT_JOB,
                    SpyImportJobTableMap::COL_ID_IMPORT_JOB,
                    SpyImportJobRunTableMap::COL_STATUS,
                    $this->dataExchangeConfig->getImportStatusPending(),
                ),
                static::COL_PENDING_RUNS,
            )
            ->withColumn(
                sprintf(
                    '(SELECT COUNT(*) FROM %s WHERE %s = %s AND %s = \'%s\')',
                    SpyImportJobRunTableMap::TABLE_NAME,
                    SpyImportJobRunTableMap::COL_FK_IMPORT_JOB,
                    SpyImportJobTableMap::COL_ID_IMPORT_JOB,
                    SpyImportJobRunTableMap::COL_STATUS,
                    $this->dataExchangeConfig->getImportStatusFailed(),
                ),
                static::COL_FAILED_RUNS,
            );
    }

    /**
     * @param array<string, mixed> $item
     */
    protected function buildActionButtons(array $item): string
    {
        $idJob = $item[static::COL_ID];
        $reference = $item[static::COL_REFERENCE];

        $runsUrl = Url::generate(static::ROUTE_RUN_INDEX, ['idImportJob' => $idJob]);
        $createRunUrl = Url::generate(static::ROUTE_RUN_CREATE, ['importJobReference' => $reference]);

        return $this->generateViewButton((string)$runsUrl, 'Runs')
            . ' '
            . $this->generateCreateButton((string)$createRunUrl, 'Create Run');
    }
}

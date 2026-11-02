<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Communication\Table;

use Orm\Zed\DataExchange\Persistence\Map\SpyImportJobRunTableMap;
use Orm\Zed\DataExchange\Persistence\SpyImportJobRunQuery;
use Spryker\Service\UtilDateTime\UtilDateTimeServiceInterface;
use Spryker\Service\UtilText\Model\Url\Url;
use Spryker\Zed\Gui\Communication\Table\AbstractTable;
use Spryker\Zed\Gui\Communication\Table\TableConfiguration;

class ImportJobRunTable extends AbstractTable
{
    protected const string COL_ID = SpyImportJobRunTableMap::COL_ID_IMPORT_JOB_RUN;

    protected const string COL_STATUS = SpyImportJobRunTableMap::COL_STATUS;

    protected const string COL_PROCESSED = SpyImportJobRunTableMap::COL_NUMBER_OF_PROCESSED_LINES;

    protected const string COL_SUCCESSFUL = SpyImportJobRunTableMap::COL_NUMBER_OF_SUCCESSFULLY_PROCESSED_LINES;

    protected const string COL_FAILED = SpyImportJobRunTableMap::COL_NUMBER_OF_FAILED_LINES;

    protected const string COL_STARTED_AT = SpyImportJobRunTableMap::COL_IMPORT_STARTED_AT;

    protected const string COL_FINISHED_AT = SpyImportJobRunTableMap::COL_IMPORT_FINISHED_AT;

    protected const string COL_CREATED_AT = SpyImportJobRunTableMap::COL_CREATED_AT;

    protected const string COL_ACTIONS = 'actions';

    protected const string ROUTE_RUN_DETAIL = '/data-exchange/run/detail';

    public function __construct(
        protected int $idImportJob,
        protected UtilDateTimeServiceInterface $utilDateTimeService,
    ) {
    }

    protected function configure(TableConfiguration $config): TableConfiguration
    {
        $config->setUrl(sprintf('table?idImportJob=%d', $this->idImportJob));

        $config->setHeader([
            static::COL_ID => '#',
            static::COL_STATUS => 'Status',
            static::COL_PROCESSED => 'Processed',
            static::COL_SUCCESSFUL => 'Successful',
            static::COL_FAILED => 'Failed',
            static::COL_STARTED_AT => 'Started At',
            static::COL_FINISHED_AT => 'Finished At',
            static::COL_CREATED_AT => 'Uploaded At',
            static::COL_ACTIONS => 'Actions',
        ]);

        $config->setSortable([
            static::COL_ID,
            static::COL_STATUS,
            static::COL_CREATED_AT,
        ]);

        $config->setRawColumns([static::COL_ACTIONS]);
        $config->setDefaultSortField(static::COL_CREATED_AT, TableConfiguration::SORT_DESC);

        return $config;
    }

    /**
     * @return array<array<string, mixed>>
     */
    protected function prepareData(TableConfiguration $config): array
    {
        $query = SpyImportJobRunQuery::create()->filterByFkImportJob($this->idImportJob);
        $queryResults = $this->runQuery($query, $config);
        $results = [];

        foreach ($queryResults as $item) {
            $results[] = [
                static::COL_ID => $item[static::COL_ID],
                static::COL_STATUS => $item[static::COL_STATUS],
                static::COL_PROCESSED => $item[static::COL_PROCESSED],
                static::COL_SUCCESSFUL => $item[static::COL_SUCCESSFUL],
                static::COL_FAILED => $item[static::COL_FAILED],
                static::COL_STARTED_AT => $item[static::COL_STARTED_AT] ? $this->utilDateTimeService->formatDateTime($item[static::COL_STARTED_AT]) : null,
                static::COL_FINISHED_AT => $item[static::COL_FINISHED_AT] ? $this->utilDateTimeService->formatDateTime($item[static::COL_FINISHED_AT]) : null,
                static::COL_CREATED_AT => $this->utilDateTimeService->formatDateTime($item[static::COL_CREATED_AT]),
                static::COL_ACTIONS => $this->buildActionButtons($item),
            ];
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $item
     */
    protected function buildActionButtons(array $item): string
    {
        $idRun = $item[static::COL_ID];
        $detailUrl = Url::generate(static::ROUTE_RUN_DETAIL, ['idImportJobRun' => $idRun]);

        return $this->generateViewButton((string)$detailUrl, 'Details');
    }
}

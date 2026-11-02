<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Persistence;

use Orm\Zed\DataExchange\Persistence\SpyImportJobQuery;
use Orm\Zed\DataExchange\Persistence\SpyImportJobRunErrorQuery;
use Orm\Zed\DataExchange\Persistence\SpyImportJobRunQuery;
use Spryker\Service\UtilEncoding\UtilEncodingServiceInterface;
use Spryker\Zed\DataExchange\DataExchangeDependencyProvider;
use Spryker\Zed\DataExchange\Persistence\Propel\Mapper\DataExchangeMapper;
use Spryker\Zed\Kernel\Persistence\AbstractPersistenceFactory;

/**
 * @method \Spryker\Zed\DataExchange\DataExchangeConfig getConfig()
 * @method \Spryker\Zed\DataExchange\Persistence\DataExchangeEntityManagerInterface getEntityManager()
 * @method \Spryker\Zed\DataExchange\Persistence\DataExchangeRepositoryInterface getRepository()
 */
class DataExchangePersistenceFactory extends AbstractPersistenceFactory
{
    public function createImportJobQuery(): SpyImportJobQuery
    {
        return SpyImportJobQuery::create();
    }

    public function createImportJobRunQuery(): SpyImportJobRunQuery
    {
        return SpyImportJobRunQuery::create();
    }

    public function createImportJobRunErrorQuery(): SpyImportJobRunErrorQuery
    {
        return SpyImportJobRunErrorQuery::create();
    }

    public function createMapper(): DataExchangeMapper
    {
        return new DataExchangeMapper($this->getUtilEncodingService());
    }

    public function getUtilEncodingService(): UtilEncodingServiceInterface
    {
        return $this->getProvidedDependency(DataExchangeDependencyProvider::SERVICE_UTIL_ENCODING);
    }
}

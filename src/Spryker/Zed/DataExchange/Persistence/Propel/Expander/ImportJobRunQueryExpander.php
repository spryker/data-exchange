<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Persistence\Propel\Expander;

use Orm\Zed\DataExchange\Persistence\SpyImportJobRunQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Spryker\Zed\DataExchange\DataExchangeConfig;

class ImportJobRunQueryExpander
{
    protected const string CONDITION_IS_PENDING = 'isPending';

    protected const string CONDITION_IS_PROCESSING = 'isProcessing';

    protected const string CONDITION_IS_LEASE_MISSING = 'isLeaseMissing';

    protected const string CONDITION_IS_LEASE_EXPIRED = 'isLeaseExpired';

    protected const string CONDITION_IS_LEASE_LAPSED = 'isLeaseLapsed';

    protected const string CONDITION_IS_ABANDONED = 'isAbandoned';

    public function __construct(protected DataExchangeConfig $config)
    {
    }

    public function expandWithClaimableCondition(
        SpyImportJobRunQuery $importJobRunQuery,
        string $leaseCheckedAt,
        int $maxAttempts,
    ): SpyImportJobRunQuery {
        return $importJobRunQuery
            ->filterByAttemptCount($maxAttempts, Criteria::LESS_THAN)
            ->condition(static::CONDITION_IS_PENDING, 'SpyImportJobRun.Status = ?', $this->config->getImportStatusPending())
            ->condition(static::CONDITION_IS_PROCESSING, 'SpyImportJobRun.Status = ?', $this->config->getImportStatusProcessing())
            ->condition(static::CONDITION_IS_LEASE_MISSING, 'SpyImportJobRun.LeaseExpiresAt IS NULL')
            ->condition(static::CONDITION_IS_LEASE_EXPIRED, 'SpyImportJobRun.LeaseExpiresAt < ?', $leaseCheckedAt)
            ->combine([static::CONDITION_IS_LEASE_MISSING, static::CONDITION_IS_LEASE_EXPIRED], Criteria::LOGICAL_OR, static::CONDITION_IS_LEASE_LAPSED)
            ->combine([static::CONDITION_IS_PROCESSING, static::CONDITION_IS_LEASE_LAPSED], Criteria::LOGICAL_AND, static::CONDITION_IS_ABANDONED)
            ->where([static::CONDITION_IS_PENDING, static::CONDITION_IS_ABANDONED], Criteria::LOGICAL_OR);
    }
}

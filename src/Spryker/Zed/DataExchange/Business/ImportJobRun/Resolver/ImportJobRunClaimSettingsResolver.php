<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Business\ImportJobRun\Resolver;

use DateTime;
use DateTimeZone;
use Spryker\Zed\DataExchange\DataExchangeConfig;

class ImportJobRunClaimSettingsResolver implements ImportJobRunClaimSettingsResolverInterface
{
    protected const string DATETIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(protected DataExchangeConfig $config)
    {
    }

    public function getMaxAttempts(): int
    {
        return max(1, $this->config->getImportJobRunMaxAttempts());
    }

    public function getExhaustedBatchSize(): int
    {
        return max(1, $this->config->getImportJobRunExhaustedBatchSize());
    }

    public function getLeaseCheckedAt(): string
    {
        return $this->createLeaseTime()->format(static::DATETIME_FORMAT);
    }

    public function getLeaseExpiresAt(): string
    {
        $leaseDurationInMinutes = max(1, $this->config->getImportJobRunLeaseDurationInMinutes());

        return $this->createLeaseTime()
            ->modify(sprintf('+%d minutes', $leaseDurationInMinutes))
            ->format(static::DATETIME_FORMAT);
    }

    protected function createLeaseTime(): DateTime
    {
        return new DateTime('now', new DateTimeZone($this->config->getImportJobRunLeaseTimeZone()));
    }
}

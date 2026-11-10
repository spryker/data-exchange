<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\Zed\DataExchange\Business;

use Codeception\Test\Unit;
use Spryker\Zed\DataExchange\DataExchangeConfig;
use Spryker\Zed\DataExchange\Exception\InvalidImportJobRunErrorRowLimitException;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Zed
 * @group DataExchange
 * @group Business
 * @group DataExchangeConfigTest
 * Add your own group annotations below this line
 */
class DataExchangeConfigTest extends Unit
{
    protected const int DEFAULT_IMPORT_JOB_RUN_ERROR_ROW_LIMIT = 1000;

    protected const string CONFIGURED_IMPORT_JOB_RUN_ERROR_ROW_LIMIT = '250';

    public function testReturnsTheDefaultErrorRowLimitWhenNoValueIsConfigured(): void
    {
        // Act
        $errorRowLimit = (new DataExchangeConfig())->getImportJobRunErrorRowLimit();

        // Assert
        $this->assertSame(static::DEFAULT_IMPORT_JOB_RUN_ERROR_ROW_LIMIT, $errorRowLimit);
    }

    /**
     * The Configuration module hands stored values over as strings, so the getter has to cast.
     */
    public function testReturnsTheErrorRowLimitConfiguredInTheBackOffice(): void
    {
        // Act
        $errorRowLimit = $this->createConfigWithModuleConfigValue(static::CONFIGURED_IMPORT_JOB_RUN_ERROR_ROW_LIMIT)->getImportJobRunErrorRowLimit();

        // Assert
        $this->assertSame((int)static::CONFIGURED_IMPORT_JOB_RUN_ERROR_ROW_LIMIT, $errorRowLimit);
    }

    public function testThrowsWhenTheConfiguredErrorRowLimitIsBelowOne(): void
    {
        // Assert
        $this->expectException(InvalidImportJobRunErrorRowLimitException::class);
        $this->expectExceptionMessage('The import job run error row limit must be 1 or greater, 0 given.');

        // Act
        $this->createConfigWithModuleConfigValue('0')->getImportJobRunErrorRowLimit();
    }

    protected function createConfigWithModuleConfigValue(string $value): DataExchangeConfig
    {
        return new class ($value) extends DataExchangeConfig {
            public function __construct(protected string $value)
            {
            }

            /**
             * @param mixed|null $default
             * @param array<\Generated\Shared\Transfer\ConfigurationScopeTransfer> $configurationScopes
             */
            protected function getModuleConfig(string $key, $default = null, array $configurationScopes = []): mixed
            {
                return $this->value;
            }
        };
    }
}

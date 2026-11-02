<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\Zed\DataExchange\Plugin;

use Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ColumnPlaceholderProviderInterface;
use Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ExportDataProviderInterface;
use Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ExportSchemaPluginInterface;
use Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ImportSchemaPluginInterface;

/**
 * Fictitious job type with no domain dependency: two columns, `sku` and `name.{locale}`.
 */
class DemoCsvImportSchemaPlugin implements ImportSchemaPluginInterface, ExportSchemaPluginInterface
{
    public const string TYPE = 'demo-csv';

    /**
     * @param array<\Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ImportStepInterface> $importSteps
     * @param array<\Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ExportStepInterface> $exportSteps
     */
    public function __construct(
        protected array $importSteps = [],
        protected ?ColumnPlaceholderProviderInterface $placeholderProvider = null,
        protected ?ExportDataProviderInterface $exportDataProvider = null,
        protected array $exportSteps = [],
    ) {
    }

    public function getType(): string
    {
        return static::TYPE;
    }

    /**
     * {@inheritDoc}
     */
    public function getImportSteps(): array
    {
        return $this->importSteps;
    }

    /**
     * {@inheritDoc}
     */
    public function getSchema(): array
    {
        return [
            ['property_name_in_file' => 'sku', 'system_property_name' => 'sku'],
            ['property_name_in_file' => 'name.{locale}', 'system_property_name' => 'name.{locale}'],
        ];
    }

    public function getPlaceholderProvider(): ColumnPlaceholderProviderInterface
    {
        return $this->placeholderProvider ?? new DemoColumnPlaceholderProvider();
    }

    /**
     * {@inheritDoc}
     */
    public function getExportSteps(): array
    {
        return $this->exportSteps;
    }

    public function getExportDataProvider(): ExportDataProviderInterface
    {
        return $this->exportDataProvider ?? new DemoExportDataProvider();
    }
}

<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Business\SchemaPlugin;

use Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ExportSchemaPluginInterface;
use Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ImportSchemaPluginInterface;

class SchemaPluginResolver implements SchemaPluginResolverInterface
{
    /**
     * @param array<\Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ImportSchemaPluginInterface> $importSchemaPlugins
     * @param array<\Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ExportSchemaPluginInterface> $exportSchemaPlugins
     */
    public function __construct(
        protected array $importSchemaPlugins,
        protected array $exportSchemaPlugins,
    ) {
    }

    public function findImportSchemaPlugin(string $type): ?ImportSchemaPluginInterface
    {
        foreach ($this->importSchemaPlugins as $schemaPlugin) {
            if ($schemaPlugin->getType() === $type) {
                return $schemaPlugin;
            }
        }

        return null;
    }

    public function findExportSchemaPlugin(string $type): ?ExportSchemaPluginInterface
    {
        foreach ($this->exportSchemaPlugins as $schemaPlugin) {
            if ($schemaPlugin->getType() === $type) {
                return $schemaPlugin;
            }
        }

        return null;
    }
}

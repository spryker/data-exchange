<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Business\SchemaPlugin;

use Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ExportSchemaPluginInterface;
use Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ImportSchemaPluginInterface;

interface SchemaPluginResolverInterface
{
    public function findImportSchemaPlugin(string $type): ?ImportSchemaPluginInterface;

    public function findExportSchemaPlugin(string $type): ?ExportSchemaPluginInterface;
}

<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\Zed\DataExchange\Plugin;

use Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ExportStepInterface;

/**
 * Fills every `name.<locale>` column with `<sku>-<locale>` so an export test can assert the step ran.
 */
class DemoExportStep implements ExportStepInterface
{
    /**
     * {@inheritDoc}
     */
    public function exportRows(array $rows, array $columns): array
    {
        foreach ($rows as $index => $row) {
            foreach ($columns as $column) {
                if (!str_starts_with($column, 'name.')) {
                    continue;
                }

                $rows[$index][$column] = sprintf('%s-%s', $row['sku'] ?? '', substr($column, strlen('name.')));
            }
        }

        return $rows;
    }
}

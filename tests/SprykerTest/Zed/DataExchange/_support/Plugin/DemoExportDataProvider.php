<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\Zed\DataExchange\Plugin;

use Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ExportDataProviderInterface;

class DemoExportDataProvider implements ExportDataProviderInterface
{
    /**
     * @param array<int, array<string, string>> $rows Rows keyed by cursor id, each one a column => value map.
     */
    public function __construct(protected array $rows = [])
    {
    }

    /**
     * {@inheritDoc}
     */
    public function getBatch(array $columns, int $limit, int $lastId = 0): array
    {
        $batch = [];

        foreach ($this->rows as $cursorId => $row) {
            if ($cursorId <= $lastId) {
                continue;
            }

            $batch[] = [static::INTERNAL_COLUMN_CURSOR_ID => (string)$cursorId] + $row;

            if (count($batch) === $limit) {
                break;
            }
        }

        return $batch;
    }
}

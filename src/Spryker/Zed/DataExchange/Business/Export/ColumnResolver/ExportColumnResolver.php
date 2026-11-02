<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Business\Export\ColumnResolver;

use Generated\Shared\Transfer\ImportJobTransfer;

class ExportColumnResolver implements ExportColumnResolverInterface
{
    /**
     * {@inheritDoc}
     */
    public function resolveColumnHeaders(ImportJobTransfer $importJobTransfer, array $placeholderValues): array
    {
        $columns = [];
        $schemaDefinition = $importJobTransfer->getDefinition();

        foreach ($schemaDefinition as $entry) {
            $columnPattern = $entry['property_name_in_file'];

            if (!str_contains($columnPattern, '{')) {
                $columns[] = $columnPattern;

                continue;
            }

            preg_match_all('/\{(\w+)\}/', $columnPattern, $matches);
            $placeholderNames = $matches[1];

            $valueSets = [];

            foreach ($placeholderNames as $placeholderName) {
                $valueSets[] = $placeholderValues[$placeholderName] ?? [];
            }

            foreach ($this->cartesianProduct($valueSets) as $combination) {
                $column = $columnPattern;

                foreach ($placeholderNames as $index => $placeholderName) {
                    $column = str_replace(sprintf('{%s}', $placeholderName), $combination[$index], $column);
                }

                $columns[] = $column;
            }
        }

        return $columns;
    }

    /**
     * @param array<int, array<string>> $sets
     *
     * @return array<int, array<string>>
     */
    protected function cartesianProduct(array $sets): array
    {
        if ($sets === [] || in_array([], $sets, true)) {
            return [];
        }

        $result = [[]];

        foreach ($sets as $values) {
            $newResult = [];

            foreach ($result as $existing) {
                foreach ($values as $value) {
                    $newResult[] = [...$existing, $value];
                }
            }

            $result = $newResult;
        }

        return $result;
    }
}

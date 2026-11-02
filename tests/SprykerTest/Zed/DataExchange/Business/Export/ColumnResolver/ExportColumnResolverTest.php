<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\Zed\DataExchange\Business\Export\ColumnResolver;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\ImportJobTransfer;
use Spryker\Zed\DataExchange\Business\Export\ColumnResolver\ExportColumnResolver;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Zed
 * @group DataExchange
 * @group Business
 * @group Export
 * @group ColumnResolver
 * @group ExportColumnResolverTest
 * Add your own group annotations below this line
 */
class ExportColumnResolverTest extends Unit
{
    public function testExpandsPlaceholdersIntoTheCartesianProductOfTheirValuesInSchemaOrder(): void
    {
        // Arrange
        $importJobTransfer = (new ImportJobTransfer())->setDefinition([
            ['property_name_in_file' => 'sku', 'system_property_name' => 'sku'],
            ['property_name_in_file' => 'price.{store}.{currency}', 'system_property_name' => 'price.{store}.{currency}'],
            ['property_name_in_file' => 'name.{locale}', 'system_property_name' => 'name.{locale}'],
        ]);

        // Act
        $columns = (new ExportColumnResolver())->resolveColumnHeaders($importJobTransfer, [
            'store' => ['DE', 'AT'],
            'currency' => ['EUR', 'CHF'],
            'locale' => ['de_DE'],
        ]);

        // Assert
        $this->assertSame(
            ['sku', 'price.DE.EUR', 'price.DE.CHF', 'price.AT.EUR', 'price.AT.CHF', 'name.de_DE'],
            $columns,
        );
    }

    public function testDropsAColumnPatternWhenOneOfItsPlaceholdersHasNoValues(): void
    {
        // Arrange
        $importJobTransfer = (new ImportJobTransfer())->setDefinition([
            ['property_name_in_file' => 'sku', 'system_property_name' => 'sku'],
            ['property_name_in_file' => 'price.{store}.{currency}', 'system_property_name' => 'price.{store}.{currency}'],
        ]);

        // Act
        $columns = (new ExportColumnResolver())->resolveColumnHeaders($importJobTransfer, ['store' => ['DE']]);

        // Assert
        $this->assertSame(['sku'], $columns);
    }
}

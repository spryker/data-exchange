<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\Zed\DataExchange\Business\ImportJobRun\Mapper;

use Codeception\Test\Unit;
use Spryker\Zed\DataExchange\Business\ImportJobRun\Mapper\CsvHeaderMapper;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Zed
 * @group DataExchange
 * @group Business
 * @group ImportJobRun
 * @group Mapper
 * @group CsvHeaderMapperTest
 * Add your own group annotations below this line
 */
class CsvHeaderMapperTest extends Unit
{
    /**
     * @var array<int, array<string, string>>
     */
    protected const array SCHEMA = [
        ['property_name_in_file' => 'Abstract SKU', 'system_property_name' => 'abstract_sku'],
        ['property_name_in_file' => 'Name ({locale})', 'system_property_name' => 'name.{locale}'],
        ['property_name_in_file' => 'Price {price_type} ({store}/{currency})', 'system_property_name' => 'price.{price_type}.{store}.{currency}'],
    ];

    public function testMapsExactAndPlaceholderHeadersToSystemNamesAndDropsUnknownOnes(): void
    {
        // Arrange
        $mapper = new CsvHeaderMapper();
        $mapper->buildMappingRulesFromSchema(static::SCHEMA);

        // Act
        $mapper->mapHeaders(['Abstract SKU', 'Name (de-DE)', 'Price DEFAULT (DE/EUR)', 'Unknown']);
        $row = $mapper->mapRow(['Abstract SKU' => 'sku-1', 'Name (de-DE)' => 'Name', 'Price DEFAULT (DE/EUR)' => '10', 'Unknown' => 'x']);

        // Assert
        $this->assertSame(['abstract_sku' => 'sku-1', 'name.de_de' => 'Name', 'price.default.de.eur' => '10'], $row);
        $this->assertSame(
            ['abstract_sku' => 'Abstract SKU', 'name.de_de' => 'Name (de-DE)', 'price.default.de.eur' => 'Price DEFAULT (DE/EUR)'],
            $mapper->getReverseMap(),
        );
    }

    public function testRebuildingRulesAndRemappingHeadersDiscardsPreviousState(): void
    {
        // Arrange
        $mapper = new CsvHeaderMapper();
        $mapper->buildMappingRulesFromSchema(static::SCHEMA);
        $mapper->mapHeaders(['Abstract SKU']);

        // Act
        $mapper->buildMappingRulesFromSchema([['property_name_in_file' => 'sku', 'system_property_name' => 'sku']]);
        $mapper->mapHeaders(['sku']);

        // Assert
        $this->assertSame(['sku' => 'sku'], $mapper->getReverseMap());
    }
}

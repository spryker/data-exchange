<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\Zed\DataExchange\Business\Facade;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\ImportJobConditionsTransfer;
use Generated\Shared\Transfer\ImportJobCriteriaTransfer;
use Generated\Shared\Transfer\ImportJobTransfer;
use Spryker\Zed\DataExchange\DataExchangeDependencyProvider;
use SprykerTest\Zed\DataExchange\DataExchangeBusinessTester;
use SprykerTest\Zed\DataExchange\Plugin\DemoColumnPlaceholderProvider;
use SprykerTest\Zed\DataExchange\Plugin\DemoCsvImportSchemaPlugin;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Zed
 * @group DataExchange
 * @group Business
 * @group Facade
 * @group ExportByJobCriteriaTest
 * Add your own group annotations below this line
 */
class ExportByJobCriteriaTest extends Unit
{
    protected const string REFERENCE_NON_EXISTENT = 'non-existent-import-job-reference';

    protected const string ERROR_MESSAGE_IMPORT_JOB_NOT_FOUND = 'No import job matches the given export criteria.';

    protected DataExchangeBusinessTester $tester;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tester->setDependency(
            DataExchangeDependencyProvider::PLUGINS_IMPORT_SCHEMA,
            [new DemoCsvImportSchemaPlugin()],
        );
    }

    public function testReturnsColumnsWithoutDataWhenIsWithDataIsFalse(): void
    {
        // Arrange
        $this->haveDemoImportJob('export-test-job');

        $criteriaTransfer = (new ImportJobCriteriaTransfer())
            ->setImportJobConditions(
                (new ImportJobConditionsTransfer())->addReference('export-test-job'),
            )
            ->setIsWithData(false);

        // Act
        $exportResult = $this->tester->getFacade()->exportData($criteriaTransfer);

        // Assert
        $this->assertTrue($exportResult->getIsSuccessful());
        $this->assertSame(DemoCsvImportSchemaPlugin::TYPE, $exportResult->getType());
        $this->assertNull($exportResult->getFileInfo(), 'File should not be generated when isWithData is false');
        $this->assertSame(['sku', 'name.de_DE', 'name.en_US'], $exportResult->getColumns());
    }

    public function testExportColumnsContainExpandedPlaceholders(): void
    {
        // Arrange
        $this->tester->setDependency(
            DataExchangeDependencyProvider::PLUGINS_IMPORT_SCHEMA,
            [new DemoCsvImportSchemaPlugin([], new DemoColumnPlaceholderProvider(['locale' => ['fr_FR']]))],
        );
        $this->haveDemoImportJob('placeholder-job');

        $criteriaTransfer = (new ImportJobCriteriaTransfer())
            ->setImportJobConditions(
                (new ImportJobConditionsTransfer())->addReference('placeholder-job'),
            );

        // Act
        $exportResult = $this->tester->getFacade()->exportData($criteriaTransfer);

        // Assert
        $this->assertSame(['sku', 'name.fr_FR'], $exportResult->getColumns());
    }

    public function testDropsPlaceholderColumnsWhenProviderHasNoValues(): void
    {
        // Arrange
        $this->tester->setDependency(
            DataExchangeDependencyProvider::PLUGINS_IMPORT_SCHEMA,
            [new DemoCsvImportSchemaPlugin([], new DemoColumnPlaceholderProvider([]))],
        );
        $this->haveDemoImportJob('no-placeholder-values-job');

        $criteriaTransfer = (new ImportJobCriteriaTransfer())
            ->setImportJobConditions(
                (new ImportJobConditionsTransfer())->addReference('no-placeholder-values-job'),
            );

        // Act
        $exportResult = $this->tester->getFacade()->exportData($criteriaTransfer);

        // Assert
        $this->assertSame(['sku'], $exportResult->getColumns());
    }

    public function testExportDataReturnsUnsuccessfulResultWhenImportJobDoesNotExist(): void
    {
        // Arrange
        $criteriaTransfer = (new ImportJobCriteriaTransfer())
            ->setImportJobConditions(
                (new ImportJobConditionsTransfer())->addReference(static::REFERENCE_NON_EXISTENT),
            );

        // Act
        $exportResult = $this->tester->getFacade()->exportData($criteriaTransfer);

        // Assert
        $this->assertFalse($exportResult->getIsSuccessful());
        $this->assertCount(1, $exportResult->getErrors());
        $this->assertSame(
            static::ERROR_MESSAGE_IMPORT_JOB_NOT_FOUND,
            $exportResult->getErrors()->getIterator()->current()->getMessage(),
        );
    }

    public function testExportDataReturnsUnsuccessfulResultWhenNoSchemaPluginIsRegisteredForJobType(): void
    {
        // Arrange
        $this->tester->haveImportJob([
            ImportJobTransfer::REFERENCE => 'orphan-type-job',
            ImportJobTransfer::TYPE => 'type-without-plugin',
            ImportJobTransfer::DEFINITION => (new DemoCsvImportSchemaPlugin())->getSchema(),
        ]);

        $criteriaTransfer = (new ImportJobCriteriaTransfer())
            ->setImportJobConditions(
                (new ImportJobConditionsTransfer())->addReference('orphan-type-job'),
            );

        // Act
        $exportResult = $this->tester->getFacade()->exportData($criteriaTransfer);

        // Assert
        $this->assertFalse($exportResult->getIsSuccessful());
        $this->assertSame(
            'No schema plugin found for job type "type-without-plugin".',
            $exportResult->getErrors()->getIterator()->current()->getMessage(),
        );
    }

    protected function haveDemoImportJob(string $reference): ImportJobTransfer
    {
        return $this->tester->haveImportJob([
            ImportJobTransfer::REFERENCE => $reference,
            ImportJobTransfer::TYPE => DemoCsvImportSchemaPlugin::TYPE,
            ImportJobTransfer::DEFINITION => (new DemoCsvImportSchemaPlugin())->getSchema(),
        ]);
    }
}

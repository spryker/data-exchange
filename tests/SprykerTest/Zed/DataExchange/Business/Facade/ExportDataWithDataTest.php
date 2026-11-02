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
use RuntimeException;
use Spryker\Zed\DataExchange\DataExchangeDependencyProvider;
use Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ExportStepInterface;
use SprykerTest\Zed\DataExchange\DataExchangeBusinessTester;
use SprykerTest\Zed\DataExchange\Plugin\DemoColumnPlaceholderProvider;
use SprykerTest\Zed\DataExchange\Plugin\DemoCsvImportSchemaPlugin;
use SprykerTest\Zed\DataExchange\Plugin\DemoExportDataProvider;
use SprykerTest\Zed\DataExchange\Plugin\DemoExportStep;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Zed
 * @group DataExchange
 * @group Business
 * @group Facade
 * @group ExportDataWithDataTest
 * Add your own group annotations below this line
 */
class ExportDataWithDataTest extends Unit
{
    protected const string REFERENCE = 'export-with-data-job';

    protected DataExchangeBusinessTester $tester;

    protected string $rootDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rootDirectory = $this->tester->configureLocalFileSystems();
    }

    public function testWritesHeaderAndAllRowsFromTheExportStepsToTheExportFileSystem(): void
    {
        // Arrange
        $this->tester->mockConfigMethod('getExportCsvBatchSize', 2);
        $this->registerSchemaPlugin([1 => ['sku' => 'sku-1'], 2 => ['sku' => 'sku-2'], 3 => ['sku' => 'sku-3']]);
        $this->haveDemoImportJob();

        // Act
        $exportResult = $this->tester->getFacade()->exportData($this->createCriteria());

        // Assert
        $this->assertTrue($exportResult->getIsSuccessful());
        $this->assertSame(['sku', 'name.de_DE', 'name.en_US'], $exportResult->getColumns());

        $fileInfo = $exportResult->getFileInfo();
        $this->assertNotNull($fileInfo);
        $this->assertSame(DataExchangeBusinessTester::FILE_SYSTEM_NAME_EXPORTS, $fileInfo->getFileSystemName());
        $this->assertStringStartsWith(DemoCsvImportSchemaPlugin::TYPE . '/export_', $fileInfo->getStoredPathOrFail());
        $this->assertSame('text/csv', $fileInfo->getContentType());
        $this->assertGreaterThan(0, $fileInfo->getSize());

        $this->assertSame(
            [
                ['sku', 'name.de_DE', 'name.en_US'],
                ['sku-1', 'sku-1-de_DE', 'sku-1-en_US'],
                ['sku-2', 'sku-2-de_DE', 'sku-2-en_US'],
                ['sku-3', 'sku-3-de_DE', 'sku-3-en_US'],
            ],
            $this->tester->readCsvRows($this->rootDirectory, $fileInfo->getStoredPathOrFail()),
            'Three seed rows over two batches of two must all reach the file in order',
        );
    }

    public function testWritesHeaderOnlyWhenTheDataProviderHasNoRows(): void
    {
        // Arrange
        $this->registerSchemaPlugin([]);
        $this->haveDemoImportJob();

        // Act
        $exportResult = $this->tester->getFacade()->exportData($this->createCriteria());

        // Assert
        $this->assertTrue($exportResult->getIsSuccessful());
        $this->assertSame(
            [['sku', 'name.de_DE', 'name.en_US']],
            $this->tester->readCsvRows($this->rootDirectory, $exportResult->getFileInfoOrFail()->getStoredPathOrFail()),
        );
    }

    public function testReturnsUnsuccessfulResultWhenTheTypeHasNoExportSchemaPlugin(): void
    {
        // Arrange
        $this->tester->setDependency(DataExchangeDependencyProvider::PLUGINS_IMPORT_SCHEMA, [new DemoCsvImportSchemaPlugin()]);
        $this->tester->setDependency(DataExchangeDependencyProvider::PLUGINS_EXPORT_SCHEMA, []);
        $this->haveDemoImportJob();

        // Act
        $exportResult = $this->tester->getFacade()->exportData($this->createCriteria());

        // Assert
        $this->assertFalse($exportResult->getIsSuccessful());
        $this->assertNull($exportResult->getFileInfo());
        $this->assertSame(
            sprintf('No export schema plugin found for job type "%s".', DemoCsvImportSchemaPlugin::TYPE),
            $exportResult->getErrors()->getIterator()->current()->getMessage(),
        );
    }

    public function testRemovesTheTemporaryFileWhenAnExportStepThrows(): void
    {
        // Arrange
        $this->registerSchemaPlugin([1 => ['sku' => 'sku-1']], $this->createThrowingExportStep());
        $this->haveDemoImportJob();
        $tempFileCountBefore = $this->countExportTempFiles();

        // Act
        $caughtException = null;

        try {
            $this->tester->getFacade()->exportData($this->createCriteria());
        } catch (RuntimeException $exception) {
            $caughtException = $exception;
        }

        // Assert
        $this->assertNotNull($caughtException, 'The step exception must propagate to the caller');
        $this->assertSame($tempFileCountBefore, $this->countExportTempFiles(), 'The temp file of the aborted export must be removed');
    }

    /**
     * @param array<int, array<string, string>> $seedRows
     */
    protected function registerSchemaPlugin(array $seedRows, ?ExportStepInterface $exportStep = null): void
    {
        $schemaPlugin = new DemoCsvImportSchemaPlugin([], new DemoColumnPlaceholderProvider(), new DemoExportDataProvider($seedRows), [$exportStep ?? new DemoExportStep()]);
        $this->tester->setDependency(DataExchangeDependencyProvider::PLUGINS_IMPORT_SCHEMA, [$schemaPlugin]);
        $this->tester->setDependency(DataExchangeDependencyProvider::PLUGINS_EXPORT_SCHEMA, [$schemaPlugin]);
    }

    protected function createThrowingExportStep(): ExportStepInterface
    {
        return new class implements ExportStepInterface {
            /**
             * {@inheritDoc}
             *
             * @throws \RuntimeException
             */
            public function exportRows(array $rows, array $columns): array
            {
                throw new RuntimeException('Export step failed');
            }
        };
    }

    protected function countExportTempFiles(): int
    {
        return count(glob(sys_get_temp_dir() . '/data_exchange_export_*') ?: []);
    }

    protected function haveDemoImportJob(): ImportJobTransfer
    {
        return $this->tester->haveImportJob([
            ImportJobTransfer::REFERENCE => static::REFERENCE,
            ImportJobTransfer::TYPE => DemoCsvImportSchemaPlugin::TYPE,
            ImportJobTransfer::DEFINITION => (new DemoCsvImportSchemaPlugin())->getSchema(),
        ]);
    }

    protected function createCriteria(): ImportJobCriteriaTransfer
    {
        return (new ImportJobCriteriaTransfer())
            ->setImportJobConditions((new ImportJobConditionsTransfer())->addReference(static::REFERENCE))
            ->setIsWithData(true);
    }
}

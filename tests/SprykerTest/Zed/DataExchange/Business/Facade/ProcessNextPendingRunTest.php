<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\Zed\DataExchange\Business\Facade;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\ImportJobRunConditionsTransfer;
use Generated\Shared\Transfer\ImportJobRunCriteriaTransfer;
use Generated\Shared\Transfer\ImportJobRunErrorConditionsTransfer;
use Generated\Shared\Transfer\ImportJobRunErrorCriteriaTransfer;
use Generated\Shared\Transfer\ImportJobRunTransfer;
use Generated\Shared\Transfer\ImportJobTransfer;
use Generated\Shared\Transfer\ImportStepResponseTransfer;
use RuntimeException;
use Spryker\Zed\DataExchange\DataExchangeDependencyProvider;
use Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ImportPostProcessorPluginInterface;
use Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ImportPreProcessorPluginInterface;
use Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ImportStepInterface;
use Spryker\Zed\Event\Business\EventFacadeInterface;
use Spryker\Zed\EventBehavior\EventBehaviorConfig;
use SprykerTest\Zed\DataExchange\DataExchangeBusinessTester;
use SprykerTest\Zed\DataExchange\Plugin\DemoCsvImportSchemaPlugin;
use SprykerTest\Zed\DataExchange\Plugin\DemoCsvImportStep;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Zed
 * @group DataExchange
 * @group Business
 * @group Facade
 * @group ProcessNextPendingRunTest
 * Add your own group annotations below this line
 */
class ProcessNextPendingRunTest extends Unit
{
    protected const string STATUS_PENDING = 'pending';

    protected const string STATUS_DONE = 'done';

    protected const string STATUS_FAILED = 'failed';

    protected const string STORED_PATH = 'demo-csv/import.csv';

    protected DataExchangeBusinessTester $tester;

    protected string $rootDirectory;

    protected DemoCsvImportStep $demoStep;

    /**
     * @var array<string, array<\Generated\Shared\Transfer\EventEntityTransfer>>
     */
    protected array $triggeredEvents = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->rootDirectory = $this->tester->configureLocalFileSystems();
        $this->demoStep = new DemoCsvImportStep();
        $this->triggeredEvents = [];

        $this->registerSchemaPlugin([$this->demoStep]);

        $eventFacadeMock = $this->createMock(EventFacadeInterface::class);
        $eventFacadeMock->method('triggerBulk')->willReturnCallback(function (string $eventName, array $transfers): void {
            $this->triggeredEvents[$eventName] = $transfers;
        });
        $this->tester->setDependency(DataExchangeDependencyProvider::FACADE_EVENT, $eventFacadeMock);
    }

    public function testImportsEveryRowOfAPendingRunThroughTheSchemaPluginSteps(): void
    {
        // Arrange
        $importJobRun = $this->havePendingRunWithCsv([
            ['sku', 'name.de-DE', 'ignored column'],
            ['sku-1', 'Name 1', 'x'],
            ['sku-2', 'Name 2', 'y'],
            ['sku-3', 'Name 3', 'z'],
        ]);

        // Act
        $this->tester->getFacade()->processNextPendingRun();

        // Assert
        $importJobRun = $this->findRun($importJobRun->getIdImportJobRunOrFail());
        $this->assertSame(static::STATUS_DONE, $importJobRun->getStatus());
        $this->assertSame(3, $importJobRun->getNumberOfProcessedLines());
        $this->assertSame(3, $importJobRun->getNumberOfSuccessfullyProcessedLines());
        $this->assertSame(0, $importJobRun->getNumberOfFailedLines());
        $this->assertNotNull($importJobRun->getImportStartedAt());
        $this->assertNotNull($importJobRun->getImportFinishedAt());

        $this->assertSame(
            [[
                2 => ['sku' => 'sku-1', 'name.de_de' => 'Name 1'],
                3 => ['sku' => 'sku-2', 'name.de_de' => 'Name 2'],
                4 => ['sku' => 'sku-3', 'name.de_de' => 'Name 3'],
            ]],
            $this->demoStep->getReceivedBatches(),
            'Rows are keyed by file row number, headers mapped to system names and unknown columns dropped',
        );
        $this->assertSame(
            [['sku' => 'sku', 'name.de_de' => 'name.de-DE']],
            $this->demoStep->getReceivedPropertyNamesInFile(),
        );
        $this->assertArrayHasKey(DemoCsvImportStep::EVENT_NAME, $this->triggeredEvents);
        $this->assertCount(3, $this->triggeredEvents[DemoCsvImportStep::EVENT_NAME]);
        $this->assertFalse(EventBehaviorConfig::isEventBehaviorDisabled());
        $this->assertFalse(class_exists('SprykerFeature\Zed\ProductExperienceManagement\Communication\Plugin\ProductCsvImportSchemaPlugin', false), 'The engine must run without any product class');
    }

    public function testDoesNothingWhenNoRunIsPending(): void
    {
        // Arrange
        $importJob = $this->tester->haveImportJob([ImportJobTransfer::TYPE => DemoCsvImportSchemaPlugin::TYPE]);
        $this->tester->haveImportJobRun([
            ImportJobRunTransfer::FK_IMPORT_JOB => $importJob->getIdImportJob(),
            ImportJobRunTransfer::STATUS => static::STATUS_DONE,
        ]);

        // Act
        $this->tester->getFacade()->processNextPendingRun();

        // Assert
        $this->assertSame([], $this->demoStep->getReceivedBatches());
    }

    public function testProcessesTheOldestPendingRunFirst(): void
    {
        // Arrange
        $olderRun = $this->havePendingRunWithCsv([['sku'], ['sku-old']], 'demo-csv/old.csv');
        $newerRun = $this->havePendingRunWithCsv([['sku'], ['sku-new']], 'demo-csv/new.csv');

        // Act
        $this->tester->getFacade()->processNextPendingRun();

        // Assert
        $this->assertSame(static::STATUS_DONE, $this->findRun($olderRun->getIdImportJobRunOrFail())->getStatus());
        $this->assertSame(static::STATUS_PENDING, $this->findRun($newerRun->getIdImportJobRunOrFail())->getStatus());
    }

    public function testSplitsRowsIntoBatchesOfTheConfiguredSizeIncludingThePartialLastOne(): void
    {
        // Arrange
        $this->tester->mockConfigMethod('getImportCsvBatchSize', 2);
        $this->havePendingRunWithCsv([['sku'], ['sku-1'], ['sku-2'], ['sku-3']]);

        // Act
        $this->tester->getFacade()->processNextPendingRun();

        // Assert
        $this->assertCount(2, $this->demoStep->getReceivedBatches());
        $this->assertCount(2, $this->demoStep->getReceivedBatches()[0]);
        $this->assertCount(1, $this->demoStep->getReceivedBatches()[1]);
    }

    public function testRecordsStepErrorsAndKeepsTheRunDoneWhenSomeRowsSucceed(): void
    {
        // Arrange
        $importJobRun = $this->havePendingRunWithCsv([['sku'], ['sku-1'], ['fail-2'], ['sku-3']]);

        // Act
        $this->tester->getFacade()->processNextPendingRun();

        // Assert
        $importJobRun = $this->findRun($importJobRun->getIdImportJobRunOrFail());
        $this->assertSame(static::STATUS_DONE, $importJobRun->getStatus());
        $this->assertSame(3, $importJobRun->getNumberOfProcessedLines());
        $this->assertSame(2, $importJobRun->getNumberOfSuccessfullyProcessedLines());
        $this->assertSame(1, $importJobRun->getNumberOfFailedLines());

        $errors = $this->findErrors($importJobRun->getIdImportJobRunOrFail());
        $this->assertCount(1, $errors);
        $this->assertSame(3, $errors[0]->getCsvRowNumber());
        $this->assertSame('Row 3 rejected by demo step.', $errors[0]->getErrorMessage());
    }

    public function testMarksTheRunFailedWhenEveryRowFails(): void
    {
        // Arrange
        $importJobRun = $this->havePendingRunWithCsv([['sku'], ['fail-1'], ['fail-2']]);

        // Act
        $this->tester->getFacade()->processNextPendingRun();

        // Assert
        $importJobRun = $this->findRun($importJobRun->getIdImportJobRunOrFail());
        $this->assertSame(static::STATUS_FAILED, $importJobRun->getStatus());
        $this->assertSame(2, $importJobRun->getNumberOfFailedLines());
    }

    public function testMarksTheRunFailedWhenTheFileHasOnlyAHeaderRow(): void
    {
        // Arrange
        $importJobRun = $this->havePendingRunWithCsv([['sku', 'name.en-US']]);

        // Act
        $this->tester->getFacade()->processNextPendingRun();

        // Assert
        $importJobRun = $this->findRun($importJobRun->getIdImportJobRunOrFail());
        $this->assertSame(static::STATUS_FAILED, $importJobRun->getStatus());
        $this->assertSame(0, $importJobRun->getNumberOfProcessedLines());
    }

    public function testRecordsAColumnCountMismatchAsARowErrorAndContinues(): void
    {
        // Arrange
        $importJobRun = $this->havePendingRunWithCsv([['sku', 'name.en-US'], ['sku-1', 'Name 1'], ['sku-2'], ['sku-3', 'Name 3']]);

        // Act
        $this->tester->getFacade()->processNextPendingRun();

        // Assert
        $importJobRun = $this->findRun($importJobRun->getIdImportJobRunOrFail());
        $this->assertSame(static::STATUS_DONE, $importJobRun->getStatus());
        $this->assertSame(3, $importJobRun->getNumberOfProcessedLines());
        $this->assertSame(2, $importJobRun->getNumberOfSuccessfullyProcessedLines());
        $this->assertSame(1, $importJobRun->getNumberOfFailedLines());

        $errors = $this->findErrors($importJobRun->getIdImportJobRunOrFail());
        $this->assertCount(1, $errors);
        $this->assertSame(3, $errors[0]->getCsvRowNumber());
        $this->assertSame('Column count mismatch: expected 2 columns, got 1.', $errors[0]->getErrorMessage());
    }

    public function testMarksTheRunFailedAndReEnablesEventsWhenAStepThrows(): void
    {
        // Arrange
        $throwingStep = $this->createMock(ImportStepInterface::class);
        $throwingStep->method('executeBatch')->willThrowException(new RuntimeException('Step exploded.'));
        $this->registerSchemaPlugin([$throwingStep]);
        $importJobRun = $this->havePendingRunWithCsv([['sku'], ['sku-1']]);

        // Act
        $this->tester->getFacade()->processNextPendingRun();

        // Assert
        $importJobRun = $this->findRun($importJobRun->getIdImportJobRunOrFail());
        $this->assertSame(static::STATUS_FAILED, $importJobRun->getStatus());
        $this->assertNotNull($importJobRun->getImportFinishedAt());
        $this->assertFalse(EventBehaviorConfig::isEventBehaviorDisabled(), 'Events must be re-enabled even when the import throws');
        $this->assertSame([], $this->triggeredEvents);

        $errors = $this->findErrors($importJobRun->getIdImportJobRunOrFail());
        $this->assertCount(1, $errors);
        $this->assertSame('Unhandled exception during import: Step exploded.', $errors[0]->getErrorMessage());
    }

    public function testMarksTheRunFailedWhenNoSchemaPluginIsRegisteredForTheJobType(): void
    {
        // Arrange
        $this->tester->setDependency(DataExchangeDependencyProvider::PLUGINS_IMPORT_SCHEMA, []);
        $importJobRun = $this->havePendingRunWithCsv([['sku'], ['sku-1']]);

        // Act
        $this->tester->getFacade()->processNextPendingRun();

        // Assert
        $importJobRun = $this->findRun($importJobRun->getIdImportJobRunOrFail());
        $this->assertSame(static::STATUS_FAILED, $importJobRun->getStatus());

        $errors = $this->findErrors($importJobRun->getIdImportJobRunOrFail());
        $this->assertCount(1, $errors);
        $this->assertSame(
            sprintf('Unhandled exception during import: No schema plugin found for job type "%s".', DemoCsvImportSchemaPlugin::TYPE),
            $errors[0]->getErrorMessage(),
        );
    }

    public function testExecutesPreAndPostProcessorPluginsAroundTheImport(): void
    {
        // Arrange
        $calls = [];
        $preProcessorMock = $this->createMock(ImportPreProcessorPluginInterface::class);
        $preProcessorMock->method('preProcess')->willReturnCallback(function () use (&$calls): void {
            $calls[] = 'pre';
        });
        $postProcessorMock = $this->createMock(ImportPostProcessorPluginInterface::class);
        $postProcessorMock->method('postProcess')->willReturnCallback(function () use (&$calls): void {
            $calls[] = 'post';
        });
        $this->tester->setDependency(DataExchangeDependencyProvider::PLUGINS_IMPORT_PRE_PROCESSOR, [$preProcessorMock]);
        $this->tester->setDependency(DataExchangeDependencyProvider::PLUGINS_IMPORT_POST_PROCESSOR, [$postProcessorMock]);

        $recordingStep = $this->createMock(ImportStepInterface::class);
        $recordingStep->method('executeBatch')->willReturnCallback(function () use (&$calls): ImportStepResponseTransfer {
            $calls[] = 'step';

            return (new ImportStepResponseTransfer())->setIsSuccessful(true);
        });
        $this->registerSchemaPlugin([$recordingStep]);
        $this->havePendingRunWithCsv([['sku'], ['sku-1']]);

        // Act
        $this->tester->getFacade()->processNextPendingRun();

        // Assert
        $this->assertSame(['pre', 'step', 'post'], $calls);
    }

    /**
     * @param array<\Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ImportStepInterface> $steps
     */
    protected function registerSchemaPlugin(array $steps): void
    {
        $this->tester->setDependency(
            DataExchangeDependencyProvider::PLUGINS_IMPORT_SCHEMA,
            [new DemoCsvImportSchemaPlugin($steps)],
        );
    }

    /**
     * @param array<int, array<int, string>> $rows
     */
    protected function havePendingRunWithCsv(array $rows, string $storedPath = self::STORED_PATH): ImportJobRunTransfer
    {
        $fileInfoTransfer = $this->tester->haveImportCsvFile($this->rootDirectory, $storedPath, $rows);

        $importJob = $this->tester->haveImportJob([
            ImportJobTransfer::TYPE => DemoCsvImportSchemaPlugin::TYPE,
            ImportJobTransfer::DEFINITION => (new DemoCsvImportSchemaPlugin())->getSchema(),
        ]);

        return $this->tester->haveImportJobRun([
            ImportJobRunTransfer::FK_IMPORT_JOB => $importJob->getIdImportJob(),
            ImportJobRunTransfer::STATUS => static::STATUS_PENDING,
            ImportJobRunTransfer::FILE_INFO => $fileInfoTransfer->toArray(true, true),
        ]);
    }

    protected function findRun(int $idImportJobRun): ImportJobRunTransfer
    {
        $criteriaTransfer = (new ImportJobRunCriteriaTransfer())
            ->setImportJobRunConditions((new ImportJobRunConditionsTransfer())->addIdImportJobRun($idImportJobRun));

        return $this->tester->getFacade()->getImportJobRunCollection($criteriaTransfer)->getImportJobRuns()->getIterator()->current();
    }

    /**
     * @return array<int, \Generated\Shared\Transfer\ImportJobRunErrorTransfer>
     */
    protected function findErrors(int $idImportJobRun): array
    {
        $criteriaTransfer = (new ImportJobRunErrorCriteriaTransfer())
            ->setImportJobRunErrorConditions((new ImportJobRunErrorConditionsTransfer())->addIdImportJobRun($idImportJobRun));

        return array_values(
            $this->tester->getFacade()->getImportJobRunErrorCollection($criteriaTransfer)->getImportJobRunErrors()->getArrayCopy(),
        );
    }
}

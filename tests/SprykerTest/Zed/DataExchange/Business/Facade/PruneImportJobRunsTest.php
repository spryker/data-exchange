<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\Zed\DataExchange\Business\Facade;

use Codeception\Test\Unit;
use DateTime;
use Generated\Shared\Transfer\FileSystemDeleteTransfer;
use Generated\Shared\Transfer\ImportJobRunConditionsTransfer;
use Generated\Shared\Transfer\ImportJobRunCriteriaTransfer;
use Generated\Shared\Transfer\ImportJobRunTransfer;
use Orm\Zed\DataExchange\Persistence\Map\SpyImportJobRunTableMap;
use Orm\Zed\DataExchange\Persistence\SpyImportJobRunErrorQuery;
use Orm\Zed\DataExchange\Persistence\SpyImportJobRunQuery;
use RuntimeException;
use Spryker\Service\FileSystem\FileSystemServiceInterface;
use Spryker\Zed\DataExchange\Business\ImportJobRun\Deleter\ImportJobRunDeleter;
use Spryker\Zed\DataExchange\Business\ImportJobRun\Pruner\Exception\InvalidImportJobRunPruneConfigurationException;
use Spryker\Zed\DataExchange\Business\ImportJobRun\Pruner\ImportJobRunPruner;
use Spryker\Zed\DataExchange\DataExchangeDependencyProvider;
use Spryker\Zed\DataExchange\Persistence\DataExchangeEntityManagerInterface;
use Spryker\Zed\DataExchange\Persistence\DataExchangeRepository;
use SprykerTest\Zed\DataExchange\DataExchangeBusinessTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Zed
 * @group DataExchange
 * @group Business
 * @group Facade
 * @group PruneImportJobRunsTest
 * Add your own group annotations below this line
 */
class PruneImportJobRunsTest extends Unit
{
    protected const int RETENTION_IN_DAYS = 30;

    protected const string DATETIME_FORMAT = 'Y-m-d H:i:s';

    protected DataExchangeBusinessTester $tester;

    protected string $rootDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rootDirectory = $this->tester->configureLocalFileSystems();
        $this->tester->mockConfigMethod('getImportJobRunRetentionInDays', static::RETENTION_IN_DAYS);
    }

    /**
     * @dataProvider providePrunableStatuses
     */
    public function testDeletesARunOlderThanTheRetentionWithItsFileAndItsErrors(string $status): void
    {
        // Arrange
        $importJobRunTransfer = $this->haveImportJobRunUpdatedDaysAgo($status, static::RETENTION_IN_DAYS + 1);
        $this->tester->haveImportJobRunError($importJobRunTransfer->getIdImportJobRunOrFail());

        // Act
        $importJobRunDeleteResponseTransfer = $this->tester->getFacade()->pruneImportJobRuns(new ImportJobRunCriteriaTransfer());

        // Assert
        $this->assertCount(0, $importJobRunDeleteResponseTransfer->getErrors());
        $this->assertNull(SpyImportJobRunQuery::create()->findPk($importJobRunTransfer->getIdImportJobRunOrFail()));
        $this->assertSame(0, SpyImportJobRunErrorQuery::create()->filterByFkImportJobRun($importJobRunTransfer->getIdImportJobRunOrFail())->count());
        $this->assertFileDoesNotExist($this->getStoredFilePath($importJobRunTransfer));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function providePrunableStatuses(): array
    {
        return [
            'done' => [DataExchangeBusinessTester::STATUS_DONE],
            'imported with errors' => [DataExchangeBusinessTester::STATUS_IMPORTED_WITH_ERRORS],
            'failed' => [DataExchangeBusinessTester::STATUS_FAILED],
        ];
    }

    public function testKeepsAFinishedRunYoungerThanTheRetention(): void
    {
        // Arrange
        $importJobRunTransfer = $this->haveImportJobRunUpdatedDaysAgo(DataExchangeBusinessTester::STATUS_DONE, static::RETENTION_IN_DAYS - 1);

        // Act
        $this->tester->getFacade()->pruneImportJobRuns(new ImportJobRunCriteriaTransfer());

        // Assert
        $this->assertNotNull(SpyImportJobRunQuery::create()->findPk($importJobRunTransfer->getIdImportJobRunOrFail()));
        $this->assertFileExists($this->getStoredFilePath($importJobRunTransfer));
    }

    /**
     * @dataProvider provideHeldStatuses
     */
    public function testNeverDeletesAPendingOrProcessingRunWhateverTheRetention(string $status): void
    {
        // Arrange
        $this->tester->mockConfigMethod('getImportJobRunRetentionInDays', 1);
        $importJobRunTransfer = $this->haveImportJobRunUpdatedDaysAgo($status, 365);

        // Act
        $this->tester->getFacade()->pruneImportJobRuns(new ImportJobRunCriteriaTransfer());

        // Assert
        $this->assertNotNull(SpyImportJobRunQuery::create()->findPk($importJobRunTransfer->getIdImportJobRunOrFail()));
        $this->assertFileExists($this->getStoredFilePath($importJobRunTransfer));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function provideHeldStatuses(): array
    {
        return [
            'pending' => [DataExchangeBusinessTester::STATUS_PENDING],
            'processing' => [DataExchangeBusinessTester::STATUS_PROCESSING],
        ];
    }

    public function testKeepsAReceivingRunUpdatedLessThanADayAgoWhenTheRetentionIsOneDay(): void
    {
        // Arrange
        $this->tester->mockConfigMethod('getImportJobRunRetentionInDays', 1);
        $importJobRunTransfer = $this->haveImportJobRunUpdatedAt(DataExchangeBusinessTester::STATUS_RECEIVING, $this->formatDateTime('-2 hours'));

        // Act
        $this->tester->getFacade()->pruneImportJobRuns(new ImportJobRunCriteriaTransfer());

        // Assert
        $this->assertNotNull(SpyImportJobRunQuery::create()->findPk($importJobRunTransfer->getIdImportJobRunOrFail()));
    }

    /**
     * @dataProvider provideInvalidConfiguration
     */
    public function testThrowsAndDeletesNothingWhenTheConfigurationIsBelowOne(string $configMethodName, int $configuredValue): void
    {
        // Arrange
        $this->tester->mockConfigMethod($configMethodName, $configuredValue);
        $importJobRunTransfer = $this->haveImportJobRunUpdatedDaysAgo(DataExchangeBusinessTester::STATUS_DONE, 365);

        // Act
        try {
            $this->tester->getFacade()->pruneImportJobRuns(new ImportJobRunCriteriaTransfer());
        } catch (InvalidImportJobRunPruneConfigurationException) {
            // Assert
            $this->assertNotNull(SpyImportJobRunQuery::create()->findPk($importJobRunTransfer->getIdImportJobRunOrFail()));
            $this->assertFileExists($this->getStoredFilePath($importJobRunTransfer));

            return;
        }

        $this->fail('InvalidImportJobRunPruneConfigurationException was not thrown.');
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function provideInvalidConfiguration(): array
    {
        return [
            'retention of 0 days' => ['getImportJobRunRetentionInDays', 0],
            'negative retention' => ['getImportJobRunRetentionInDays', -1],
            'batch size of 0' => ['getImportJobRunPruneBatchSize', 0],
        ];
    }

    public function testDeletesTheRunWithoutAnErrorWhenItsStoredFileIsAlreadyMissing(): void
    {
        // Arrange
        $importJobRunTransfer = $this->haveImportJobRunUpdatedDaysAgo(DataExchangeBusinessTester::STATUS_FAILED, static::RETENTION_IN_DAYS + 1);
        unlink($this->getStoredFilePath($importJobRunTransfer));

        // Act
        $importJobRunDeleteResponseTransfer = $this->tester->getFacade()->pruneImportJobRuns(new ImportJobRunCriteriaTransfer());

        // Assert
        $this->assertCount(0, $importJobRunDeleteResponseTransfer->getErrors());
        $this->assertNull(SpyImportJobRunQuery::create()->findPk($importJobRunTransfer->getIdImportJobRunOrFail()));
    }

    public function testDeletesAReceivingRunThatHasNoStoredFile(): void
    {
        // Arrange
        $importJobRunTransfer = $this->haveImportJobRunUpdatedDaysAgo(DataExchangeBusinessTester::STATUS_RECEIVING, static::RETENTION_IN_DAYS + 1, [
            ImportJobRunTransfer::FILE_INFO => ['original_file_name' => 'upload.csv'],
        ]);

        // Act
        $importJobRunDeleteResponseTransfer = $this->tester->getFacade()->pruneImportJobRuns(new ImportJobRunCriteriaTransfer());

        // Assert
        $this->assertCount(0, $importJobRunDeleteResponseTransfer->getErrors());
        $this->assertNull(SpyImportJobRunQuery::create()->findPk($importJobRunTransfer->getIdImportJobRunOrFail()));
    }

    /**
     * @dataProvider provideKeptAndPrunedRunUpdates
     */
    public function testKeepsTheRunAndReportsAnErrorWhenItsFileCannotBeDeletedAndPrunesTheOthers(int $batchSize, int $keptDaysAgo, int $prunedDaysAgo): void
    {
        // Arrange
        $this->tester->mockConfigMethod('getImportJobRunPruneBatchSize', $batchSize);
        $now = new DateTime();
        $keptImportJobRunTransfer = $this->haveImportJobRunUpdatedAt(DataExchangeBusinessTester::STATUS_DONE, $this->formatDaysBefore($now, $keptDaysAgo));
        $prunedImportJobRunTransfer = $this->haveImportJobRunUpdatedAt(DataExchangeBusinessTester::STATUS_DONE, $this->formatDaysBefore($now, $prunedDaysAgo), [], 'demo-csv/other.csv');
        $this->mockFileSystemServiceFailingToDelete($keptImportJobRunTransfer->getFileInfoOrFail()->getStoredPathOrFail());

        // Act
        $importJobRunDeleteResponseTransfer = $this->tester->getFacade()->pruneImportJobRuns(new ImportJobRunCriteriaTransfer());

        // Assert
        $this->assertCount(1, $importJobRunDeleteResponseTransfer->getErrors());
        $this->assertSame(
            sprintf('Import job run #%d could not be deleted.', $keptImportJobRunTransfer->getIdImportJobRunOrFail()),
            $importJobRunDeleteResponseTransfer->getErrors()->getIterator()->current()->getMessage(),
        );
        $this->assertNotNull(SpyImportJobRunQuery::create()->findPk($keptImportJobRunTransfer->getIdImportJobRunOrFail()));
        $this->assertNull(SpyImportJobRunQuery::create()->findPk($prunedImportJobRunTransfer->getIdImportJobRunOrFail()));
    }

    /**
     * @return array<string, array{int, int, int}>
     */
    public static function provideKeptAndPrunedRunUpdates(): array
    {
        return [
            'one batch' => [100, static::RETENTION_IN_DAYS + 2, static::RETENTION_IN_DAYS + 1],
            'one run per batch' => [1, static::RETENTION_IN_DAYS + 2, static::RETENTION_IN_DAYS + 1],
            'one run per batch, both updated at the same time' => [1, static::RETENTION_IN_DAYS + 1, static::RETENTION_IN_DAYS + 1],
        ];
    }

    public function testKeepsEveryRunOfTheBatchAndReportsAnErrorPerRunWhenTheRowsCannotBeDeleted(): void
    {
        // Arrange
        $firstImportJobRunTransfer = $this->haveImportJobRunUpdatedDaysAgo(DataExchangeBusinessTester::STATUS_DONE, static::RETENTION_IN_DAYS + 1);
        $secondImportJobRunTransfer = $this->haveImportJobRunUpdatedDaysAgo(DataExchangeBusinessTester::STATUS_FAILED, static::RETENTION_IN_DAYS + 1, [], 'demo-csv/other.csv');
        $dataExchangeEntityManagerMock = $this->createMock(DataExchangeEntityManagerInterface::class);
        $dataExchangeEntityManagerMock->method('deleteImportJobRuns')->willThrowException(new RuntimeException('The database refused the delete.'));
        /** @var \Spryker\Zed\DataExchange\DataExchangeConfig $dataExchangeConfig */
        $dataExchangeConfig = $this->tester->getModuleConfig();
        $importJobRunDeleter = new ImportJobRunDeleter($dataExchangeEntityManagerMock, $this->tester->getLocator()->fileSystem()->service(), $dataExchangeConfig);
        $importJobRunPruner = new ImportJobRunPruner(new DataExchangeRepository(), $importJobRunDeleter, $dataExchangeConfig);

        // Act
        $importJobRunDeleteResponseTransfer = $importJobRunPruner->pruneImportJobRuns(new ImportJobRunCriteriaTransfer());

        // Assert
        $this->assertSame(0, $importJobRunDeleteResponseTransfer->getDeletedImportJobRunCountOrFail());
        $this->assertCount(2, $importJobRunDeleteResponseTransfer->getErrors());
        $this->assertNotNull(SpyImportJobRunQuery::create()->findPk($firstImportJobRunTransfer->getIdImportJobRunOrFail()));
        $this->assertNotNull(SpyImportJobRunQuery::create()->findPk($secondImportJobRunTransfer->getIdImportJobRunOrFail()));
    }

    public function testReportsTheNumberOfDeletedRuns(): void
    {
        // Arrange
        $this->tester->getFacade()->pruneImportJobRuns(new ImportJobRunCriteriaTransfer());
        $this->haveImportJobRunUpdatedDaysAgo(DataExchangeBusinessTester::STATUS_DONE, static::RETENTION_IN_DAYS + 1);
        $this->haveImportJobRunUpdatedDaysAgo(DataExchangeBusinessTester::STATUS_FAILED, static::RETENTION_IN_DAYS + 1, [], 'demo-csv/other.csv');

        // Act
        $importJobRunDeleteResponseTransfer = $this->tester->getFacade()->pruneImportJobRuns(new ImportJobRunCriteriaTransfer());

        // Assert
        $this->assertSame(2, $importJobRunDeleteResponseTransfer->getDeletedImportJobRunCountOrFail());
        $this->assertCount(0, $importJobRunDeleteResponseTransfer->getErrors());
    }

    public function testDeletesNothingAndReportsNoErrorWhenItRunsASecondTime(): void
    {
        // Arrange
        $this->haveImportJobRunUpdatedDaysAgo(DataExchangeBusinessTester::STATUS_DONE, static::RETENTION_IN_DAYS + 1);
        $this->tester->getFacade()->pruneImportJobRuns(new ImportJobRunCriteriaTransfer());

        // Act
        $importJobRunDeleteResponseTransfer = $this->tester->getFacade()->pruneImportJobRuns(new ImportJobRunCriteriaTransfer());

        // Assert
        $this->assertSame(0, $importJobRunDeleteResponseTransfer->getDeletedImportJobRunCountOrFail());
        $this->assertCount(0, $importJobRunDeleteResponseTransfer->getErrors());
    }

    /**
     * @dataProvider provideBatchSizes
     */
    public function testDeletesEveryRunOlderThanTheRetentionWhateverTheBatchSize(int $batchSize): void
    {
        // Arrange
        $this->tester->mockConfigMethod('getImportJobRunPruneBatchSize', $batchSize);
        $importJobRunIds = [];

        foreach (['demo-csv/first.csv', 'demo-csv/second.csv', 'demo-csv/third.csv'] as $storedPath) {
            $importJobRunIds[] = $this->haveImportJobRunUpdatedDaysAgo(DataExchangeBusinessTester::STATUS_DONE, static::RETENTION_IN_DAYS + 1, [], $storedPath)->getIdImportJobRunOrFail();
        }

        // Act
        $this->tester->getFacade()->pruneImportJobRuns(new ImportJobRunCriteriaTransfer());

        // Assert
        $this->assertSame(0, SpyImportJobRunQuery::create()->filterByIdImportJobRun_In($importJobRunIds)->count());
    }

    /**
     * @return array<string, array{int}>
     */
    public static function provideBatchSizes(): array
    {
        return [
            'batch size 2' => [2],
            'batch size 1' => [1],
        ];
    }

    public function testPrunesOnlyTheRequestedRunsAndKeepsTheOthers(): void
    {
        // Arrange
        $requestedImportJobRunTransfer = $this->haveImportJobRunUpdatedDaysAgo(DataExchangeBusinessTester::STATUS_DONE, static::RETENTION_IN_DAYS + 1);
        $otherImportJobRunTransfer = $this->haveImportJobRunUpdatedDaysAgo(DataExchangeBusinessTester::STATUS_DONE, static::RETENTION_IN_DAYS + 1, [], 'demo-csv/other.csv');
        $importJobRunCriteriaTransfer = (new ImportJobRunCriteriaTransfer())->setImportJobRunConditions(
            (new ImportJobRunConditionsTransfer())->addIdImportJobRun($requestedImportJobRunTransfer->getIdImportJobRunOrFail()),
        );

        // Act
        $importJobRunDeleteResponseTransfer = $this->tester->getFacade()->pruneImportJobRuns($importJobRunCriteriaTransfer);

        // Assert
        $this->assertSame(1, $importJobRunDeleteResponseTransfer->getDeletedImportJobRunCount());
        $this->assertNull(SpyImportJobRunQuery::create()->findPk($requestedImportJobRunTransfer->getIdImportJobRunOrFail()));
        $this->assertNotNull(SpyImportJobRunQuery::create()->findPk($otherImportJobRunTransfer->getIdImportJobRunOrFail()));
    }

    public function testNeverPrunesARequestedRunThatIsNotPrunable(): void
    {
        // Arrange
        $importJobRunTransfer = $this->haveImportJobRunUpdatedDaysAgo(DataExchangeBusinessTester::STATUS_PENDING, static::RETENTION_IN_DAYS + 1);
        $importJobRunCriteriaTransfer = (new ImportJobRunCriteriaTransfer())->setImportJobRunConditions(
            (new ImportJobRunConditionsTransfer())
                ->addIdImportJobRun($importJobRunTransfer->getIdImportJobRunOrFail())
                ->addStatus(DataExchangeBusinessTester::STATUS_PENDING),
        );

        // Act
        $this->tester->getFacade()->pruneImportJobRuns($importJobRunCriteriaTransfer);

        // Assert
        $this->assertNotNull(SpyImportJobRunQuery::create()->findPk($importJobRunTransfer->getIdImportJobRunOrFail()));
    }

    /**
     * @param array<string, mixed> $seedData
     */
    protected function haveImportJobRunUpdatedDaysAgo(
        string $status,
        int $days,
        array $seedData = [],
        string $storedPath = DataExchangeBusinessTester::CSV_STORED_PATH
    ): ImportJobRunTransfer {
        return $this->haveImportJobRunUpdatedAt($status, $this->formatDateTime(sprintf('-%d days', $days)), $seedData, $storedPath);
    }

    /**
     * @param array<string, mixed> $seedData
     */
    protected function haveImportJobRunUpdatedAt(
        string $status,
        string $updatedAt,
        array $seedData = [],
        string $storedPath = DataExchangeBusinessTester::CSV_STORED_PATH
    ): ImportJobRunTransfer {
        $importJobRunTransfer = $this->tester->havePendingImportJobRunWithCsvFile($this->rootDirectory, [['sku'], ['sku-1']], $storedPath, $seedData + [
            ImportJobRunTransfer::STATUS => $status,
        ]);

        SpyImportJobRunQuery::create()
            ->filterByIdImportJobRun($importJobRunTransfer->getIdImportJobRunOrFail())
            ->update(['UpdatedAt' => $updatedAt]);
        SpyImportJobRunTableMap::clearInstancePool();

        return $importJobRunTransfer;
    }

    protected function formatDateTime(string $modifier): string
    {
        return (new DateTime($modifier))->format(static::DATETIME_FORMAT);
    }

    protected function formatDaysBefore(DateTime $now, int $days): string
    {
        return (clone $now)->modify(sprintf('-%d days', $days))->format(static::DATETIME_FORMAT);
    }

    protected function getStoredFilePath(ImportJobRunTransfer $importJobRunTransfer): string
    {
        return $this->rootDirectory . '/' . $importJobRunTransfer->getFileInfoOrFail()->getStoredPathOrFail();
    }

    protected function mockFileSystemServiceFailingToDelete(string $failingStoredPath): void
    {
        $fileSystemService = $this->tester->getLocator()->fileSystem()->service();
        $fileSystemServiceMock = $this->createMock(FileSystemServiceInterface::class);
        $fileSystemServiceMock->method('delete')->willReturnCallback(function (FileSystemDeleteTransfer $fileSystemDeleteTransfer) use ($failingStoredPath, $fileSystemService): void {
            if ($fileSystemDeleteTransfer->getPath() === $failingStoredPath) {
                throw new RuntimeException('The storage refused the delete.');
            }

            $fileSystemService->delete($fileSystemDeleteTransfer);
        });
        $this->tester->setDependency(DataExchangeDependencyProvider::SERVICE_FILE_SYSTEM, $fileSystemServiceMock);
    }
}

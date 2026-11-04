<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\Zed\DataExchange\Business\Facade;

use Codeception\Test\Unit;
use DateTime;
use DateTimeZone;
use Generated\Shared\Transfer\ImportJobRunConditionsTransfer;
use Generated\Shared\Transfer\ImportJobRunCriteriaTransfer;
use Generated\Shared\Transfer\ImportJobRunErrorConditionsTransfer;
use Generated\Shared\Transfer\ImportJobRunErrorCriteriaTransfer;
use Generated\Shared\Transfer\ImportJobRunTransfer;
use Generated\Shared\Transfer\ImportJobTransfer;
use Generated\Shared\Transfer\ImportStepResponseTransfer;
use Orm\Zed\DataExchange\Persistence\Map\SpyImportJobRunTableMap;
use Orm\Zed\DataExchange\Persistence\SpyImportJobRunQuery;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Spryker\Service\FileSystem\FileSystemServiceInterface;
use Spryker\Zed\DataExchange\Business\ImportJobRun\Claimer\ImportJobRunClaimer;
use Spryker\Zed\DataExchange\Business\ImportJobRun\Manager\ImportJobRunManager;
use Spryker\Zed\DataExchange\DataExchangeDependencyProvider;
use Spryker\Zed\DataExchange\Persistence\DataExchangeEntityManager;
use Spryker\Zed\DataExchange\Persistence\DataExchangeRepositoryInterface;
use Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ImportPostProcessorPluginInterface;
use Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ImportPreProcessorPluginInterface;
use Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ImportStepInterface;
use Spryker\Zed\Event\Business\EventFacadeInterface;
use Spryker\Zed\EventBehavior\EventBehaviorConfig;
use SprykerTest\Zed\DataExchange\DataExchangeBusinessTester;
use SprykerTest\Zed\DataExchange\Plugin\DemoCsvImportSchemaPlugin;
use SprykerTest\Zed\DataExchange\Plugin\DemoCsvImportStep;
use Stringable;

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

    protected const string STATUS_PROCESSING = 'processing';

    protected const string STATUS_RECEIVING = 'receiving';

    protected const string FIRST_WORKER = 'first-host:1';

    protected const string SECOND_WORKER = 'second-host:2';

    protected const string DEAD_WORKER = 'dead-host:1';

    protected const string LIVE_WORKER = 'live-host:2';

    protected const string LIVE_WORKER_FINISHED_AT = '2026-01-01 10:00:00';

    protected const int LIVE_WORKER_NUMBER_OF_LINES = 7;

    protected const string DATETIME_FORMAT = 'Y-m-d H:i:s';

    protected const int LEASE_DURATION_IN_MINUTES = 30;

    protected const string TIME_ZONE_AHEAD_OF_LEASE_TIME_ZONE = 'Pacific/Kiritimati';

    protected const array CLAIMED_COLUMNS = ['status', 'claimed_by', 'lease_expires_at', 'attempt_count', 'import_started_at'];

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
        $importJobRun = $this->tester->havePendingImportJobRunWithCsvFile($this->rootDirectory, [
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
        $newerRun = $this->tester->havePendingImportJobRunWithCsvFile($this->rootDirectory, [['sku'], ['sku-new']], 'demo-csv/new.csv');
        $olderRun = $this->tester->havePendingImportJobRunWithCsvFile($this->rootDirectory, [['sku'], ['sku-old']], 'demo-csv/old.csv', [
            ImportJobRunTransfer::CREATED_AT => (new DateTime('-1 day'))->format(static::DATETIME_FORMAT),
        ]);

        // Act
        $this->tester->getFacade()->processNextPendingRun();

        // Assert
        $this->assertSame(static::STATUS_DONE, $this->findRun($olderRun->getIdImportJobRunOrFail())->getStatus());
        $this->assertSame(static::STATUS_PENDING, $this->findRun($newerRun->getIdImportJobRunOrFail())->getStatus());
    }

    public function testClaimsThePendingRunWithALeaseBeforeImportingIt(): void
    {
        // Arrange
        $importJobRun = $this->tester->havePendingImportJobRunWithCsvFile($this->rootDirectory, [['sku'], ['sku-1']]);

        // Act
        $this->tester->getFacade()->processNextPendingRun();

        // Assert
        $importJobRun = $this->findRun($importJobRun->getIdImportJobRunOrFail());
        $this->assertSame(static::STATUS_DONE, $importJobRun->getStatus());
        $this->assertSame(1, $importJobRun->getAttemptCount());
        $this->assertNotEmpty($importJobRun->getClaimedBy());
        $this->assertNotNull($importJobRun->getLeaseExpiresAt());
        $this->assertGreaterThan($this->formatLeaseTime('now'), $importJobRun->getLeaseExpiresAt());
    }

    public function testReclaimsAProcessingRunWhoseLeaseHasExpired(): void
    {
        // Arrange
        $importJobRun = $this->tester->havePendingImportJobRunWithCsvFile($this->rootDirectory, [['sku'], ['sku-1']], DataExchangeBusinessTester::CSV_STORED_PATH, [
            ImportJobRunTransfer::STATUS => static::STATUS_PROCESSING,
            ImportJobRunTransfer::CLAIMED_BY => static::DEAD_WORKER,
            ImportJobRunTransfer::LEASE_EXPIRES_AT => $this->formatLeaseTime('-1 minute'),
            ImportJobRunTransfer::ATTEMPT_COUNT => 1,
        ]);

        // Act
        $this->tester->getFacade()->processNextPendingRun();

        // Assert
        $importJobRun = $this->findRun($importJobRun->getIdImportJobRunOrFail());
        $this->assertSame(static::STATUS_DONE, $importJobRun->getStatus());
        $this->assertSame(2, $importJobRun->getAttemptCount());
        $this->assertNotSame(static::DEAD_WORKER, $importJobRun->getClaimedBy());
        $this->assertSame([[2 => ['sku' => 'sku-1']]], $this->demoStep->getReceivedBatches());
    }

    public function testResetsTheProgressOfTheAbandonedAttemptWhenItReclaimsARun(): void
    {
        // Arrange
        $importJobRunTransfer = $this->tester->havePendingImportJobRunWithCsvFile($this->rootDirectory, [['sku'], ['sku-1']], DataExchangeBusinessTester::CSV_STORED_PATH, [
            ImportJobRunTransfer::STATUS => static::STATUS_PROCESSING,
            ImportJobRunTransfer::CLAIMED_BY => static::DEAD_WORKER,
            ImportJobRunTransfer::LEASE_EXPIRES_AT => $this->formatLeaseTime('-1 minute'),
            ImportJobRunTransfer::ATTEMPT_COUNT => 1,
            ImportJobRunTransfer::NUMBER_OF_PROCESSED_LINES => 5,
            ImportJobRunTransfer::NUMBER_OF_SUCCESSFULLY_PROCESSED_LINES => 3,
            ImportJobRunTransfer::NUMBER_OF_FAILED_LINES => 2,
            ImportJobRunTransfer::NUMBER_OF_PROCESSED_BYTES => 100,
        ]);
        $idImportJobRun = $importJobRunTransfer->getIdImportJobRunOrFail();
        $importJobRunTransferBeforeFirstBatch = null;
        $this->registerSchemaPlugin([$this->createStepRunningBeforeEachBatch(function () use ($idImportJobRun, &$importJobRunTransferBeforeFirstBatch): void {
            $importJobRunTransferBeforeFirstBatch ??= $this->findRun($idImportJobRun);
        })]);

        // Act
        $this->tester->getFacade()->processNextPendingRun();

        // Assert
        $this->assertSame(0, $importJobRunTransferBeforeFirstBatch->getNumberOfProcessedLines());
        $this->assertSame(0, $importJobRunTransferBeforeFirstBatch->getNumberOfSuccessfullyProcessedLines());
        $this->assertSame(0, $importJobRunTransferBeforeFirstBatch->getNumberOfFailedLines());
        $this->assertNull($importJobRunTransferBeforeFirstBatch->getNumberOfProcessedBytes());
    }

    public function testDoesNotClaimAProcessingRunWhoseLeaseIsStillActive(): void
    {
        // Arrange
        $importJobRun = $this->tester->havePendingImportJobRunWithCsvFile($this->rootDirectory, [['sku'], ['sku-1']], DataExchangeBusinessTester::CSV_STORED_PATH, [
            ImportJobRunTransfer::STATUS => static::STATUS_PROCESSING,
            ImportJobRunTransfer::CLAIMED_BY => static::LIVE_WORKER,
            ImportJobRunTransfer::LEASE_EXPIRES_AT => $this->formatLeaseTime('+1 minute'),
            ImportJobRunTransfer::ATTEMPT_COUNT => 1,
        ]);

        // Act
        $this->tester->getFacade()->processNextPendingRun();

        // Assert
        $importJobRun = $this->findRun($importJobRun->getIdImportJobRunOrFail());
        $this->assertSame(static::STATUS_PROCESSING, $importJobRun->getStatus());
        $this->assertSame(static::LIVE_WORKER, $importJobRun->getClaimedBy());
        $this->assertSame(1, $importJobRun->getAttemptCount());
        $this->assertSame([], $this->demoStep->getReceivedBatches());
    }

    public function testDoesNotClaimARunWhoseLeaseIsStillActiveWhenThePhpDefaultTimeZoneIsAheadOfTheLeaseTimeZone(): void
    {
        // Arrange
        $importJobRun = $this->tester->havePendingImportJobRunWithCsvFile($this->rootDirectory, [['sku'], ['sku-1']], DataExchangeBusinessTester::CSV_STORED_PATH, [
            ImportJobRunTransfer::STATUS => static::STATUS_PROCESSING,
            ImportJobRunTransfer::CLAIMED_BY => static::LIVE_WORKER,
            ImportJobRunTransfer::LEASE_EXPIRES_AT => $this->formatLeaseTime('+1 minute'),
            ImportJobRunTransfer::ATTEMPT_COUNT => 1,
        ]);
        $defaultTimeZone = date_default_timezone_get();
        date_default_timezone_set(static::TIME_ZONE_AHEAD_OF_LEASE_TIME_ZONE);

        // Act
        try {
            $this->tester->getFacade()->processNextPendingRun();
        } finally {
            date_default_timezone_set($defaultTimeZone);
        }

        // Assert
        $importJobRun = $this->findRun($importJobRun->getIdImportJobRunOrFail());
        $this->assertSame(static::STATUS_PROCESSING, $importJobRun->getStatus());
        $this->assertSame(static::LIVE_WORKER, $importJobRun->getClaimedBy());
        $this->assertSame([], $this->demoStep->getReceivedBatches());
    }

    public function testDoesNotClaimARunThatReachedTheMaximumNumberOfAttempts(): void
    {
        // Arrange
        $this->tester->mockConfigMethod('getImportJobRunMaxAttempts', 2);
        $importJobRun = $this->tester->havePendingImportJobRunWithCsvFile($this->rootDirectory, [['sku'], ['sku-1']], DataExchangeBusinessTester::CSV_STORED_PATH, [
            ImportJobRunTransfer::STATUS => static::STATUS_PROCESSING,
            ImportJobRunTransfer::CLAIMED_BY => static::DEAD_WORKER,
            ImportJobRunTransfer::LEASE_EXPIRES_AT => $this->formatLeaseTime('-1 minute'),
            ImportJobRunTransfer::ATTEMPT_COUNT => 2,
        ]);

        // Act
        $this->tester->getFacade()->processNextPendingRun();

        // Assert
        $importJobRun = $this->findRun($importJobRun->getIdImportJobRunOrFail());
        $this->assertSame(static::STATUS_PROCESSING, $importJobRun->getStatus());
        $this->assertSame(2, $importJobRun->getAttemptCount());
        $this->assertSame([], $this->demoStep->getReceivedBatches());
    }

    public function testClaimsAFreshPendingRunWhoseLeaseIsNotSet(): void
    {
        // Arrange
        $importJobRun = $this->tester->havePendingImportJobRunWithCsvFile($this->rootDirectory, [['sku'], ['sku-1']], DataExchangeBusinessTester::CSV_STORED_PATH, [
            ImportJobRunTransfer::LEASE_EXPIRES_AT => null,
        ]);

        // Act
        $this->tester->getFacade()->processNextPendingRun();

        // Assert
        $importJobRun = $this->findRun($importJobRun->getIdImportJobRunOrFail());
        $this->assertSame(static::STATUS_DONE, $importJobRun->getStatus());
        $this->assertSame(1, $importJobRun->getAttemptCount());
    }

    public function testReclaimsAProcessingRunWhoseLeaseIsNotSet(): void
    {
        // Arrange
        $importJobRun = $this->tester->havePendingImportJobRunWithCsvFile($this->rootDirectory, [['sku'], ['sku-1']], DataExchangeBusinessTester::CSV_STORED_PATH, [
            ImportJobRunTransfer::STATUS => static::STATUS_PROCESSING,
            ImportJobRunTransfer::CLAIMED_BY => static::DEAD_WORKER,
            ImportJobRunTransfer::LEASE_EXPIRES_AT => null,
            ImportJobRunTransfer::ATTEMPT_COUNT => 1,
        ]);

        // Act
        $this->tester->getFacade()->processNextPendingRun();

        // Assert
        $importJobRun = $this->findRun($importJobRun->getIdImportJobRunOrFail());
        $this->assertSame(static::STATUS_DONE, $importJobRun->getStatus());
        $this->assertSame(2, $importJobRun->getAttemptCount());
    }

    public function testClaimsARunOneAttemptBelowTheMaximumNumberOfAttempts(): void
    {
        // Arrange
        $this->tester->mockConfigMethod('getImportJobRunMaxAttempts', 2);
        $importJobRun = $this->tester->havePendingImportJobRunWithCsvFile($this->rootDirectory, [['sku'], ['sku-1']], DataExchangeBusinessTester::CSV_STORED_PATH, [
            ImportJobRunTransfer::ATTEMPT_COUNT => 1,
        ]);

        // Act
        $this->tester->getFacade()->processNextPendingRun();

        // Assert
        $importJobRun = $this->findRun($importJobRun->getIdImportJobRunOrFail());
        $this->assertSame(static::STATUS_DONE, $importJobRun->getStatus());
        $this->assertSame(2, $importJobRun->getAttemptCount());
    }

    public function testDoesNotClaimAPendingRunThatReachedTheMaximumNumberOfAttempts(): void
    {
        // Arrange
        $this->tester->mockConfigMethod('getImportJobRunMaxAttempts', 2);
        $importJobRun = $this->tester->havePendingImportJobRunWithCsvFile($this->rootDirectory, [['sku'], ['sku-1']], DataExchangeBusinessTester::CSV_STORED_PATH, [
            ImportJobRunTransfer::ATTEMPT_COUNT => 2,
        ]);

        // Act
        $this->tester->getFacade()->processNextPendingRun();

        // Assert
        $importJobRun = $this->findRun($importJobRun->getIdImportJobRunOrFail());
        $this->assertSame(static::STATUS_PENDING, $importJobRun->getStatus());
        $this->assertSame(2, $importJobRun->getAttemptCount());
        $this->assertSame([], $this->demoStep->getReceivedBatches());
    }

    public function testClaimsTheRunWithOneUpdateThatRechecksTheClaimableCondition(): void
    {
        // Arrange
        $this->tester->havePendingImportJobRunWithCsvFile($this->rootDirectory, [['sku'], ['sku-1']]);

        // Act
        $executedQueries = $this->captureExecutedQueries(fn () => $this->tester->getFactory()->createImportJobRunClaimer()->claimNextImportJobRun());

        // Assert
        $updateQueries = array_values(array_filter($executedQueries, static fn (string $query): bool => stripos($query, 'UPDATE') === 0));
        $this->assertCount(1, $updateQueries);
        [$setClause, $whereClause] = explode(' WHERE ', $updateQueries[0], 2);
        foreach (static::CLAIMED_COLUMNS as $claimedColumn) {
            $this->assertStringContainsString($claimedColumn . '=', $setClause);
        }
        $this->assertStringContainsString('lease_expires_at IS NULL', $whereClause);
        $this->assertStringContainsString('attempt_count<', $whereClause);
    }

    public function testNeverClaimsAReceivingRunEvenWhenItIsTheOldestRun(): void
    {
        // Arrange
        $receivingRun = $this->tester->havePendingImportJobRunWithCsvFile($this->rootDirectory, [['sku'], ['sku-receiving']], 'demo-csv/receiving.csv', [
            ImportJobRunTransfer::STATUS => static::STATUS_RECEIVING,
            ImportJobRunTransfer::LEASE_EXPIRES_AT => $this->formatLeaseTime('-1 day'),
        ]);
        $pendingRun = $this->tester->havePendingImportJobRunWithCsvFile($this->rootDirectory, [['sku'], ['sku-pending']], 'demo-csv/pending.csv');

        // Act
        $this->tester->getFacade()->processNextPendingRun();
        $this->tester->getFacade()->processNextPendingRun();

        // Assert
        $receivingRun = $this->findRun($receivingRun->getIdImportJobRunOrFail());
        $this->assertSame(static::STATUS_RECEIVING, $receivingRun->getStatus());
        $this->assertSame(0, $receivingRun->getAttemptCount());
        $this->assertSame(static::STATUS_DONE, $this->findRun($pendingRun->getIdImportJobRunOrFail())->getStatus());
        $this->assertSame([[2 => ['sku' => 'sku-pending']]], $this->demoStep->getReceivedBatches());
    }

    /**
     * @disableTransaction
     */
    public function testAWorkerWithAStalePendingSelectOnAnotherConnectionDoesNotClaimOrImportTheRunClaimedFirst(): void
    {
        // Arrange
        $importJobRun = $this->tester->havePendingImportJobRunWithCsvFile($this->rootDirectory, [['sku'], ['sku-1']]);
        $stalePendingImportJobRunTransfer = $this->findRun($importJobRun->getIdImportJobRunOrFail());
        $secondWorkerConnection = $this->createSecondDatabaseConnection();
        $this->mockFileSystemServiceExpectingNoRead();
        $secondImportJobRunManager = $this->createImportJobRunManagerSeeingClaimableRun($stalePendingImportJobRunTransfer);

        // Act
        $isClaimedByFirstWorker = (new DataExchangeEntityManager())->updateClaimableImportJobRun($this->createClaim($importJobRun, static::FIRST_WORKER), $this->formatLeaseTime('now'), $this->getMaxAttempts());
        $this->executeOnConnection($secondWorkerConnection, fn () => $secondImportJobRunManager->processNextPendingRun());

        // Assert
        $this->assertTrue($isClaimedByFirstWorker);
        $this->assertNotSame(Propel::getConnection(SpyImportJobRunTableMap::DATABASE_NAME), $secondWorkerConnection);
        $claimedImportJobRunTransfer = $this->findRun($importJobRun->getIdImportJobRunOrFail());
        $this->assertSame(static::FIRST_WORKER, $claimedImportJobRunTransfer->getClaimedBy());
        $this->assertSame(1, $claimedImportJobRunTransfer->getAttemptCount());
        $this->assertSame([], $this->demoStep->getReceivedBatches());
    }

    /**
     * @dataProvider provideBatchSizes
     */
    public function testImportsEveryRowWhateverTheConfiguredBatchSize(int $batchSize): void
    {
        // Arrange
        $this->tester->mockConfigMethod('getImportCsvBatchSize', $batchSize);
        $this->tester->havePendingImportJobRunWithCsvFile($this->rootDirectory, [['sku'], ['sku-1'], ['sku-2'], ['sku-3']]);

        // Act
        $this->tester->getFacade()->processNextPendingRun();

        // Assert
        $this->assertSame(
            [2 => ['sku' => 'sku-1'], 3 => ['sku' => 'sku-2'], 4 => ['sku' => 'sku-3']],
            array_replace([], ...$this->demoStep->getReceivedBatches()),
        );
    }

    /**
     * @return array<string, array{int}>
     */
    public static function provideBatchSizes(): array
    {
        return [
            'batch size smaller than the file' => [2],
            'batch size zero' => [0],
        ];
    }

    public function testWritesTheProgressOfTheRunAfterEveryBatch(): void
    {
        // Arrange
        $this->tester->mockConfigMethod('getImportCsvBatchSize', 1);
        $importJobRunTransfer = $this->tester->havePendingImportJobRunWithCsvFile($this->rootDirectory, [['sku'], ['sku-1'], ['sku-2'], ['sku-3']]);
        $idImportJobRun = $importJobRunTransfer->getIdImportJobRunOrFail();
        $processedLinesSeenByBatch = [];
        $this->registerSchemaPlugin([$this->createStepRunningBeforeEachBatch(function () use ($idImportJobRun, &$processedLinesSeenByBatch): void {
            $processedLinesSeenByBatch[] = $this->findRun($idImportJobRun)->getNumberOfProcessedLines();
        })]);

        // Act
        $this->tester->getFacade()->processNextPendingRun();

        // Assert
        $this->assertSame([0, 1, 2], $processedLinesSeenByBatch);
        $importJobRunTransfer = $this->findRun($idImportJobRun);
        $this->assertSame(static::STATUS_DONE, $importJobRunTransfer->getStatus());
        $this->assertSame(3, $importJobRunTransfer->getNumberOfProcessedLines());
        $this->assertSame($importJobRunTransfer->getFileInfoOrFail()->getSize(), $importJobRunTransfer->getNumberOfProcessedBytes());
        $this->assertSame(100.0, $importJobRunTransfer->getProgressPercentage());
    }

    public function testRenewsTheLeaseByTheConfiguredDurationAfterEveryBatch(): void
    {
        // Arrange
        $this->tester->mockConfigMethod('getImportCsvBatchSize', 1);
        $this->tester->mockConfigMethod('getImportJobRunLeaseDurationInMinutes', static::LEASE_DURATION_IN_MINUTES);
        $importJobRunTransfer = $this->tester->havePendingImportJobRunWithCsvFile($this->rootDirectory, [['sku'], ['sku-1'], ['sku-2'], ['sku-3']]);
        $idImportJobRun = $importJobRunTransfer->getIdImportJobRunOrFail();
        $leaseExpiresAtSeenByBatch = [];
        $this->registerSchemaPlugin([$this->createStepRunningBeforeEachBatch(function () use ($idImportJobRun, &$leaseExpiresAtSeenByBatch): void {
            $leaseExpiresAtSeenByBatch[] = $this->findRun($idImportJobRun)->getLeaseExpiresAt();
            SpyImportJobRunQuery::create()
                ->filterByIdImportJobRun($idImportJobRun)
                ->update(['LeaseExpiresAt' => $this->formatLeaseTime('+1 minute')]);
        })]);
        $earliestLeaseExpiresAt = $this->formatLeaseTime('+' . static::LEASE_DURATION_IN_MINUTES . ' minutes');

        // Act
        $this->tester->getFacade()->processNextPendingRun();

        // Assert
        $latestLeaseExpiresAt = $this->formatLeaseTime('+' . static::LEASE_DURATION_IN_MINUTES . ' minutes');
        $leaseExpiresAtSeenByBatch[] = $this->findRun($idImportJobRun)->getLeaseExpiresAt();
        $this->assertCount(4, $leaseExpiresAtSeenByBatch);
        foreach ($leaseExpiresAtSeenByBatch as $leaseExpiresAt) {
            $this->assertGreaterThanOrEqual($earliestLeaseExpiresAt, $leaseExpiresAt);
            $this->assertLessThanOrEqual($latestLeaseExpiresAt, $leaseExpiresAt);
        }
    }

    public function testStopsTheImportWhenAnotherWorkerTookOverTheClaim(): void
    {
        // Arrange
        $this->tester->mockConfigMethod('getImportCsvBatchSize', 1);
        $importJobRunTransfer = $this->tester->havePendingImportJobRunWithCsvFile($this->rootDirectory, [['sku'], ['sku-1'], ['sku-2'], ['sku-3']]);
        $idImportJobRun = $importJobRunTransfer->getIdImportJobRunOrFail();
        $numberOfExecutedBatches = 0;
        $this->registerSchemaPlugin([$this->createStepRunningBeforeEachBatch(function () use ($idImportJobRun, &$numberOfExecutedBatches): void {
            $numberOfExecutedBatches++;
            SpyImportJobRunQuery::create()
                ->filterByIdImportJobRun($idImportJobRun)
                ->update(['ClaimedBy' => static::LIVE_WORKER]);
        })]);
        $postProcessorMock = $this->createMock(ImportPostProcessorPluginInterface::class);
        $postProcessorMock->expects($this->never())->method('postProcess');
        $this->tester->setDependency(DataExchangeDependencyProvider::PLUGINS_IMPORT_POST_PROCESSOR, [$postProcessorMock]);

        // Act
        $this->tester->getFacade()->processNextPendingRun();

        // Assert
        $importJobRunTransfer = $this->findRun($idImportJobRun);
        $this->assertSame(1, $numberOfExecutedBatches);
        $this->assertSame(static::STATUS_PROCESSING, $importJobRunTransfer->getStatus());
        $this->assertSame(static::LIVE_WORKER, $importJobRunTransfer->getClaimedBy());
        $this->assertSame(0, $importJobRunTransfer->getNumberOfProcessedLines());
        $this->assertNull($importJobRunTransfer->getImportFinishedAt());
    }

    public function testKeepsTheFinalStateOfTheOtherWorkerWhenItTookOverTheClaimDuringThePartialLastBatch(): void
    {
        // Arrange
        $this->tester->mockConfigMethod('getImportCsvBatchSize', 2);
        $importJobRunTransfer = $this->tester->havePendingImportJobRunWithCsvFile($this->rootDirectory, [['sku'], ['sku-1'], ['sku-2'], ['sku-3']]);
        $idImportJobRun = $importJobRunTransfer->getIdImportJobRunOrFail();
        $numberOfExecutedBatches = 0;
        $this->registerSchemaPlugin([$this->createStepRunningBeforeEachBatch(function () use ($idImportJobRun, &$numberOfExecutedBatches): void {
            $numberOfExecutedBatches++;

            if ($numberOfExecutedBatches < 2) {
                return;
            }

            $this->finishRunAsTheLiveWorker($idImportJobRun);
        })]);
        $postProcessorMock = $this->createMock(ImportPostProcessorPluginInterface::class);
        $postProcessorMock->expects($this->never())->method('postProcess');
        $this->tester->setDependency(DataExchangeDependencyProvider::PLUGINS_IMPORT_POST_PROCESSOR, [$postProcessorMock]);

        // Act
        $this->tester->getFacade()->processNextPendingRun();

        // Assert
        $importJobRunTransfer = $this->findRun($idImportJobRun);
        $this->assertSame(2, $numberOfExecutedBatches);
        $this->assertSame(static::STATUS_DONE, $importJobRunTransfer->getStatus());
        $this->assertSame(static::LIVE_WORKER, $importJobRunTransfer->getClaimedBy());
        $this->assertSame(static::LIVE_WORKER_FINISHED_AT, $importJobRunTransfer->getImportFinishedAt());
        $this->assertSame(static::LIVE_WORKER_NUMBER_OF_LINES, $importJobRunTransfer->getNumberOfProcessedLines());
        $this->assertSame(static::LIVE_WORKER_NUMBER_OF_LINES, $importJobRunTransfer->getNumberOfSuccessfullyProcessedLines());
    }

    public function testKeepsTheFinalStateOfTheOtherWorkerWhenItTookOverTheClaimWhileThePublishEventsWereTriggered(): void
    {
        // Arrange
        $importJobRunTransfer = $this->tester->havePendingImportJobRunWithCsvFile($this->rootDirectory, [['sku'], ['sku-1']]);
        $idImportJobRun = $importJobRunTransfer->getIdImportJobRunOrFail();
        $eventFacadeMock = $this->createMock(EventFacadeInterface::class);
        $eventFacadeMock->method('triggerBulk')->willReturnCallback(function () use ($idImportJobRun): void {
            $this->finishRunAsTheLiveWorker($idImportJobRun);
        });
        $this->tester->setDependency(DataExchangeDependencyProvider::FACADE_EVENT, $eventFacadeMock);
        $postProcessorMock = $this->createMock(ImportPostProcessorPluginInterface::class);
        $postProcessorMock->expects($this->never())->method('postProcess');
        $this->tester->setDependency(DataExchangeDependencyProvider::PLUGINS_IMPORT_POST_PROCESSOR, [$postProcessorMock]);

        // Act
        $this->tester->getFacade()->processNextPendingRun();

        // Assert
        $importJobRunTransfer = $this->findRun($idImportJobRun);
        $this->assertSame(static::STATUS_DONE, $importJobRunTransfer->getStatus());
        $this->assertSame(static::LIVE_WORKER, $importJobRunTransfer->getClaimedBy());
        $this->assertSame(static::LIVE_WORKER_FINISHED_AT, $importJobRunTransfer->getImportFinishedAt());
        $this->assertSame(static::LIVE_WORKER_NUMBER_OF_LINES, $importJobRunTransfer->getNumberOfProcessedLines());
    }

    public function testKeepsTheClaimOfTheOtherWorkerAndRecordsNoErrorWhenAStepThrowsAfterTheClaimWasTakenOver(): void
    {
        // Arrange
        $importJobRunTransfer = $this->tester->havePendingImportJobRunWithCsvFile($this->rootDirectory, [['sku'], ['sku-1']]);
        $idImportJobRun = $importJobRunTransfer->getIdImportJobRunOrFail();
        $this->registerSchemaPlugin([$this->createStepRunningBeforeEachBatch(function () use ($idImportJobRun): void {
            SpyImportJobRunQuery::create()
                ->filterByIdImportJobRun($idImportJobRun)
                ->update(['ClaimedBy' => static::LIVE_WORKER]);

            throw new RuntimeException('Step exploded.');
        })]);

        // Act
        $this->tester->getFacade()->processNextPendingRun();

        // Assert
        $importJobRunTransfer = $this->findRun($idImportJobRun);
        $this->assertSame(static::STATUS_PROCESSING, $importJobRunTransfer->getStatus());
        $this->assertSame(static::LIVE_WORKER, $importJobRunTransfer->getClaimedBy());
        $this->assertNull($importJobRunTransfer->getImportFinishedAt());
        $this->assertSame([], $this->findErrors($idImportJobRun));
    }

    public function testRecordsStepErrorsAndKeepsTheRunDoneWhenSomeRowsSucceed(): void
    {
        // Arrange
        $importJobRun = $this->tester->havePendingImportJobRunWithCsvFile($this->rootDirectory, [['sku'], ['sku-1'], ['fail-2'], ['sku-3']]);

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
        $importJobRun = $this->tester->havePendingImportJobRunWithCsvFile($this->rootDirectory, [['sku'], ['fail-1'], ['fail-2']]);

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
        $importJobRun = $this->tester->havePendingImportJobRunWithCsvFile($this->rootDirectory, [['sku', 'name.en-US']]);

        // Act
        $this->tester->getFacade()->processNextPendingRun();

        // Assert
        $importJobRun = $this->findRun($importJobRun->getIdImportJobRunOrFail());
        $this->assertSame(static::STATUS_FAILED, $importJobRun->getStatus());
        $this->assertSame(0, $importJobRun->getNumberOfProcessedLines());
        $this->assertSame($importJobRun->getFileInfoOrFail()->getSize(), $importJobRun->getNumberOfProcessedBytes());
    }

    public function testStoresZeroProcessedBytesAndNoProgressPercentageWhenTheFileIsEmpty(): void
    {
        // Arrange
        $importJobRun = $this->tester->havePendingImportJobRunWithCsvFile($this->rootDirectory, []);

        // Act
        $this->tester->getFacade()->processNextPendingRun();

        // Assert
        $importJobRun = $this->findRun($importJobRun->getIdImportJobRunOrFail());
        $this->assertSame(static::STATUS_FAILED, $importJobRun->getStatus());
        $this->assertSame(0, $importJobRun->getNumberOfProcessedLines());
        $this->assertSame(0, $importJobRun->getNumberOfProcessedBytes());
        $this->assertNull($importJobRun->getProgressPercentage());
    }

    public function testStoresTheFullFileSizeAsProcessedBytesWhenRowsAfterTheLastBatchAreSkipped(): void
    {
        // Arrange
        $this->tester->mockConfigMethod('getImportCsvBatchSize', 1);
        $importJobRun = $this->tester->havePendingImportJobRunWithCsvFile($this->rootDirectory, [['sku', 'name.en-US'], ['sku-1', 'Name 1'], ['sku-2']]);

        // Act
        $this->tester->getFacade()->processNextPendingRun();

        // Assert
        $importJobRun = $this->findRun($importJobRun->getIdImportJobRunOrFail());
        $this->assertSame(static::STATUS_DONE, $importJobRun->getStatus());
        $this->assertSame($importJobRun->getFileInfoOrFail()->getSize(), $importJobRun->getNumberOfProcessedBytes());
        $this->assertSame(100.0, $importJobRun->getProgressPercentage());
    }

    public function testRecordsAColumnCountMismatchAsARowErrorAndContinues(): void
    {
        // Arrange
        $importJobRun = $this->tester->havePendingImportJobRunWithCsvFile($this->rootDirectory, [['sku', 'name.en-US'], ['sku-1', 'Name 1'], ['sku-2'], ['sku-3', 'Name 3']]);

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
        $importJobRun = $this->tester->havePendingImportJobRunWithCsvFile($this->rootDirectory, [['sku'], ['sku-1']]);

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
        $importJobRun = $this->tester->havePendingImportJobRunWithCsvFile($this->rootDirectory, [['sku'], ['sku-1']]);

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
        $this->tester->havePendingImportJobRunWithCsvFile($this->rootDirectory, [['sku'], ['sku-1']]);

        // Act
        $this->tester->getFacade()->processNextPendingRun();

        // Assert
        $this->assertSame(['pre', 'step', 'post'], $calls);
    }

    public function testKeepsTheRunDoneAndRecordsAnErrorWhenAPostProcessorFails(): void
    {
        // Arrange
        $postProcessorMock = $this->createMock(ImportPostProcessorPluginInterface::class);
        $postProcessorMock->method('postProcess')->willThrowException(new RuntimeException('Post-processor broke.'));
        $this->tester->setDependency(DataExchangeDependencyProvider::PLUGINS_IMPORT_POST_PROCESSOR, [$postProcessorMock]);
        $importJobRunTransfer = $this->tester->havePendingImportJobRunWithCsvFile($this->rootDirectory, [['sku'], ['sku-1']]);

        // Act
        $this->tester->getFacade()->processNextPendingRun();

        // Assert
        $importJobRunTransfer = $this->findRun($importJobRunTransfer->getIdImportJobRunOrFail());
        $this->assertSame(static::STATUS_DONE, $importJobRunTransfer->getStatus());
        $this->assertSame(1, $importJobRunTransfer->getNumberOfSuccessfullyProcessedLines());
        $importJobRunErrorTransfers = $this->findErrors($importJobRunTransfer->getIdImportJobRunOrFail());
        $this->assertCount(1, $importJobRunErrorTransfers);
        $this->assertSame('Unhandled exception during post-processing: Post-processor broke.', $importJobRunErrorTransfers[0]->getErrorMessage());
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

    protected function createStepRunningBeforeEachBatch(callable $beforeBatch): ImportStepInterface
    {
        $step = $this->createMock(ImportStepInterface::class);
        $step->method('executeBatch')->willReturnCallback(function (array $batch, array $propertyNamesInFile) use ($beforeBatch): ImportStepResponseTransfer {
            $beforeBatch();

            return $this->demoStep->executeBatch($batch, $propertyNamesInFile);
        });

        return $step;
    }

    protected function finishRunAsTheLiveWorker(int $idImportJobRun): void
    {
        SpyImportJobRunQuery::create()
            ->filterByIdImportJobRun($idImportJobRun)
            ->update([
                'Status' => static::STATUS_DONE,
                'ClaimedBy' => static::LIVE_WORKER,
                'ImportFinishedAt' => static::LIVE_WORKER_FINISHED_AT,
                'NumberOfProcessedLines' => static::LIVE_WORKER_NUMBER_OF_LINES,
                'NumberOfSuccessfullyProcessedLines' => static::LIVE_WORKER_NUMBER_OF_LINES,
                'NumberOfFailedLines' => 0,
            ]);
    }

    protected function createClaim(ImportJobRunTransfer $importJobRunTransfer, string $claimedBy): ImportJobRunTransfer
    {
        $claimedAt = (new DateTime())->format(static::DATETIME_FORMAT);

        return (new ImportJobRunTransfer())
            ->setIdImportJobRun($importJobRunTransfer->getIdImportJobRunOrFail())
            ->setStatus(static::STATUS_PROCESSING)
            ->setClaimedBy($claimedBy)
            ->setAttemptCount(1)
            ->setImportStartedAt($claimedAt)
            ->setLeaseExpiresAt($this->formatLeaseTime('+10 minutes'))
            ->setNumberOfProcessedLines(0)
            ->setNumberOfSuccessfullyProcessedLines(0)
            ->setNumberOfFailedLines(0)
            ->setUpdatedAt($claimedAt);
    }

    protected function formatLeaseTime(string $relativeTime): string
    {
        $leaseTimeZone = new DateTimeZone($this->tester->getModuleConfig()->getImportJobRunLeaseTimeZone());

        return (new DateTime($relativeTime, $leaseTimeZone))->format(static::DATETIME_FORMAT);
    }

    protected function getMaxAttempts(): int
    {
        return $this->tester->getModuleConfig()->getImportJobRunMaxAttempts();
    }

    protected function createImportJobRunManagerSeeingClaimableRun(ImportJobRunTransfer $importJobRunTransfer): ImportJobRunManager
    {
        $repositoryMock = $this->createMock(DataExchangeRepositoryInterface::class);
        $repositoryMock->method('findOldestClaimableImportJobRun')->willReturn($importJobRunTransfer);
        $entityManager = new DataExchangeEntityManager();
        $config = $this->tester->getModuleConfig();

        return new ImportJobRunManager(
            new ImportJobRunClaimer($repositoryMock, $entityManager, $config),
            $entityManager,
            $this->tester->getFactory()->createImportJobRunImporter(),
            $config,
            [],
            [],
        );
    }

    protected function mockFileSystemServiceExpectingNoRead(): void
    {
        $fileSystemServiceMock = $this->createMock(FileSystemServiceInterface::class);
        $fileSystemServiceMock->expects($this->never())->method('readStream');
        $this->tester->setDependency(DataExchangeDependencyProvider::SERVICE_FILE_SYSTEM, $fileSystemServiceMock);
    }

    /**
     * @return list<string>
     */
    protected function captureExecutedQueries(callable $callback): array
    {
        $connection = Propel::getConnection(SpyImportJobRunTableMap::DATABASE_NAME);
        $previousLogger = $connection->getLogger();
        $wasInDebugMode = $connection->isInDebugMode();
        $queryRecordingLogger = new class extends AbstractLogger {
            /**
             * @var list<string>
             */
            public array $messages = [];

            public function log(mixed $level, string|Stringable $message, array $context = []): void
            {
                $this->messages[] = (string)$message;
            }
        };
        $connection->setLogger($queryRecordingLogger);
        $connection->useDebug(true);

        try {
            $callback();
        } finally {
            $connection->useDebug($wasInDebugMode);
            $connection->setLogger($previousLogger);
        }

        return $queryRecordingLogger->messages;
    }

    protected function createSecondDatabaseConnection(): ConnectionInterface
    {
        $serviceContainer = Propel::getServiceContainer();
        $secondConnectionManager = clone $serviceContainer->getConnectionManager(SpyImportJobRunTableMap::DATABASE_NAME);
        $secondConnectionManager->closeConnections();

        return $secondConnectionManager->getWriteConnection($serviceContainer->getAdapter(SpyImportJobRunTableMap::DATABASE_NAME));
    }

    protected function executeOnConnection(ConnectionInterface $connection, callable $callback): mixed
    {
        $serviceContainer = Propel::getServiceContainer();
        $defaultConnectionManager = $serviceContainer->getConnectionManager(SpyImportJobRunTableMap::DATABASE_NAME);
        $serviceContainer->setConnection(SpyImportJobRunTableMap::DATABASE_NAME, $connection);

        try {
            return $callback();
        } finally {
            $serviceContainer->setConnectionManager($defaultConnectionManager);
        }
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

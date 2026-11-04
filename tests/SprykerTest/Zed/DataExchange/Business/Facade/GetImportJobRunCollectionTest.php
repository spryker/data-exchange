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
use Generated\Shared\Transfer\ImportJobRunFileInfoTransfer;
use Generated\Shared\Transfer\ImportJobRunTransfer;
use SprykerTest\Zed\DataExchange\DataExchangeBusinessTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Zed
 * @group DataExchange
 * @group Business
 * @group Facade
 * @group GetImportJobRunCollectionTest
 * Add your own group annotations below this line
 */
class GetImportJobRunCollectionTest extends Unit
{
    protected DataExchangeBusinessTester $tester;

    public function testReturnsEmptyCollectionWhenNoRunsExist(): void
    {
        // Arrange
        $importJob = $this->tester->haveImportJob();
        $criteriaTransfer = (new ImportJobRunCriteriaTransfer())
            ->setImportJobRunConditions(
                (new ImportJobRunConditionsTransfer())->addIdImportJob($importJob->getIdImportJob()),
            );

        // Act
        $collectionTransfer = $this->tester->getFacade()->getImportJobRunCollection($criteriaTransfer);

        // Assert
        $this->assertCount(0, $collectionTransfer->getImportJobRuns());
    }

    public function testReturnsRunsFilteredByJobId(): void
    {
        // Arrange
        $importJob = $this->tester->haveImportJob();
        $otherJob = $this->tester->haveImportJob();

        $this->tester->haveImportJobRun([
            ImportJobRunTransfer::FK_IMPORT_JOB => $importJob->getIdImportJob(),
            ImportJobRunTransfer::STATUS => 'done',
        ]);
        $this->tester->haveImportJobRun([
            ImportJobRunTransfer::FK_IMPORT_JOB => $importJob->getIdImportJob(),
            ImportJobRunTransfer::STATUS => 'pending',
        ]);
        $this->tester->haveImportJobRun([
            ImportJobRunTransfer::FK_IMPORT_JOB => $otherJob->getIdImportJob(),
            ImportJobRunTransfer::STATUS => 'done',
        ]);

        $criteriaTransfer = (new ImportJobRunCriteriaTransfer())
            ->setImportJobRunConditions(
                (new ImportJobRunConditionsTransfer())->addIdImportJob($importJob->getIdImportJob()),
            );

        // Act
        $collectionTransfer = $this->tester->getFacade()->getImportJobRunCollection($criteriaTransfer);

        // Assert
        $this->assertCount(2, $collectionTransfer->getImportJobRuns());
    }

    public function testReturnsRunWithCorrectStatus(): void
    {
        // Arrange
        $importJob = $this->tester->haveImportJob();
        $this->tester->haveImportJobRun([
            ImportJobRunTransfer::FK_IMPORT_JOB => $importJob->getIdImportJob(),
            ImportJobRunTransfer::STATUS => 'done',
            ImportJobRunTransfer::NUMBER_OF_PROCESSED_LINES => 100,
            ImportJobRunTransfer::NUMBER_OF_SUCCESSFULLY_PROCESSED_LINES => 95,
            ImportJobRunTransfer::NUMBER_OF_FAILED_LINES => 5,
        ]);

        $criteriaTransfer = (new ImportJobRunCriteriaTransfer())
            ->setImportJobRunConditions(
                (new ImportJobRunConditionsTransfer())->addIdImportJob($importJob->getIdImportJob()),
            );

        // Act
        $collectionTransfer = $this->tester->getFacade()->getImportJobRunCollection($criteriaTransfer);

        // Assert
        $run = $collectionTransfer->getImportJobRuns()->getIterator()->current();
        $this->assertSame('done', $run->getStatus());
        $this->assertSame(100, $run->getNumberOfProcessedLines());
        $this->assertSame(95, $run->getNumberOfSuccessfullyProcessedLines());
        $this->assertSame(5, $run->getNumberOfFailedLines());
    }

    /**
     * @dataProvider provideProcessedBytesAndFileSizes
     *
     * @param array<string, int> $fileInfo
     */
    public function testReturnsRunWithProgressPercentageOfProcessedBytesToFileSize(
        ?int $numberOfProcessedBytes,
        array $fileInfo,
        ?float $expectedProgressPercentage,
    ): void {
        // Arrange
        $importJobTransfer = $this->tester->haveImportJob();
        $this->tester->haveImportJobRun([
            ImportJobRunTransfer::FK_IMPORT_JOB => $importJobTransfer->getIdImportJobOrFail(),
            ImportJobRunTransfer::NUMBER_OF_PROCESSED_BYTES => $numberOfProcessedBytes,
            ImportJobRunTransfer::FILE_INFO => $fileInfo,
        ]);
        $importJobRunCriteriaTransfer = (new ImportJobRunCriteriaTransfer())
            ->setImportJobRunConditions(
                (new ImportJobRunConditionsTransfer())->addIdImportJob($importJobTransfer->getIdImportJobOrFail()),
            );

        // Act
        $importJobRunCollectionTransfer = $this->tester->getFacade()->getImportJobRunCollection($importJobRunCriteriaTransfer);

        // Assert
        $this->assertSame($expectedProgressPercentage, $importJobRunCollectionTransfer->getImportJobRuns()->getIterator()->current()->getProgressPercentage());
    }

    /**
     * @return array<string, array{?int, array<string, int>, ?float}>
     */
    public static function provideProcessedBytesAndFileSizes(): array
    {
        return [
            'run in progress' => [500, [ImportJobRunFileInfoTransfer::SIZE => 2000], 25.0],
            'completed run' => [2000, [ImportJobRunFileInfoTransfer::SIZE => 2000], 100.0],
            'processed bytes above the file size' => [2500, [ImportJobRunFileInfoTransfer::SIZE => 2000], 100.0],
            'run not started' => [0, [ImportJobRunFileInfoTransfer::SIZE => 2000], 0.0],
            'unknown processed bytes' => [null, [ImportJobRunFileInfoTransfer::SIZE => 2000], null],
            'unknown file size' => [500, [], null],
            'empty file' => [0, [ImportJobRunFileInfoTransfer::SIZE => 0], null],
        ];
    }
}

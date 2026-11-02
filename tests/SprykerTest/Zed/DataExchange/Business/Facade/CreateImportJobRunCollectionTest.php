<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\Zed\DataExchange\Business\Facade;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\ImportJobRunCollectionRequestTransfer;
use Generated\Shared\Transfer\ImportJobRunFileInfoTransfer;
use Generated\Shared\Transfer\ImportJobRunTransfer;
use Generated\Shared\Transfer\ImportJobTransfer;
use SprykerTest\Zed\DataExchange\DataExchangeBusinessTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Zed
 * @group DataExchange
 * @group Business
 * @group Facade
 * @group CreateImportJobRunCollectionTest
 * Add your own group annotations below this line
 */
class CreateImportJobRunCollectionTest extends Unit
{
    protected DataExchangeBusinessTester $tester;

    public function testCreatesImportJobRunForExistingJob(): void
    {
        // Arrange
        $importJob = $this->tester->haveImportJob([
            ImportJobTransfer::REFERENCE => 'test-run-job',
        ]);

        $fileInfo = (new ImportJobRunFileInfoTransfer())
            ->setOriginalFileName('test.csv')
            ->setContentType('text/csv')
            ->setSize(1024);

        $collectionRequest = (new ImportJobRunCollectionRequestTransfer())
            ->addImportJobRun(
                (new ImportJobRunTransfer())
                    ->setImportJobReference('test-run-job')
                    ->setFileInfo($fileInfo),
            );

        // Act
        $collectionResponse = $this->tester->getFacade()->createImportJobRunCollection($collectionRequest);

        // Assert
        $this->assertCount(0, $collectionResponse->getErrors());
        $this->assertCount(1, $collectionResponse->getImportJobRuns());

        $createdRun = $collectionResponse->getImportJobRuns()->getIterator()->current();
        $this->assertNotNull($createdRun->getIdImportJobRun());
        $this->assertSame($importJob->getIdImportJob(), $createdRun->getFkImportJob());
    }

    public function testReturnsErrorForNonExistentJobReference(): void
    {
        // Arrange
        $collectionRequest = (new ImportJobRunCollectionRequestTransfer())
            ->addImportJobRun(
                (new ImportJobRunTransfer())
                    ->setImportJobReference('non-existent-ref')
                    ->setFileInfo(
                        (new ImportJobRunFileInfoTransfer())
                            ->setOriginalFileName('test.csv')
                            ->setContentType('text/csv')
                            ->setSize(100),
                    ),
            );

        // Act
        $collectionResponse = $this->tester->getFacade()->createImportJobRunCollection($collectionRequest);

        // Assert
        $this->assertCount(1, $collectionResponse->getErrors());
        $this->assertSame(
            'Import job with reference "non-existent-ref" not found.',
            $collectionResponse->getErrors()->getIterator()->current()->getMessage(),
        );
    }
}

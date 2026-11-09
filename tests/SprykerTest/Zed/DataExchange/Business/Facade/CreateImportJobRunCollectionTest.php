<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\Zed\DataExchange\Business\Facade;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\FileSystemStreamTransfer;
use Generated\Shared\Transfer\ImportJobRunCollectionRequestTransfer;
use Generated\Shared\Transfer\ImportJobRunFileInfoTransfer;
use Generated\Shared\Transfer\ImportJobRunTransfer;
use Generated\Shared\Transfer\ImportJobTransfer;
use Orm\Zed\DataExchange\Persistence\SpyImportJobRunErrorQuery;
use Orm\Zed\DataExchange\Persistence\SpyImportJobRunQuery;
use Spryker\Service\FileSystem\FileSystemServiceInterface;
use Spryker\Zed\DataExchange\DataExchangeDependencyProvider;
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
    protected const string FILE_SYSTEM_NAME_UNKNOWN = 'unknown-file-system';

    protected const string ERROR_MESSAGE_FILE_NOT_STORED = 'The uploaded file could not be stored.';

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

    public function testStreamsTheUploadedFileIntoTheImportFileSystem(): void
    {
        // Arrange
        $uploadedFilePath = tempnam(sys_get_temp_dir(), 'data-exchange-upload');
        $content = "sku,name\nsku-1,Name 1\nsku-2,Name 2\n";
        file_put_contents($uploadedFilePath, $content);
        $streamedContent = null;
        $streamedPath = null;

        $fileSystemServiceMock = $this->createMock(FileSystemServiceInterface::class);
        $fileSystemServiceMock->expects($this->never())->method('write');
        $fileSystemServiceMock->expects($this->once())
            ->method('writeStream')
            ->willReturnCallback(function (FileSystemStreamTransfer $streamTransfer, $stream) use (&$streamedContent, &$streamedPath): void {
                $this->assertIsResource($stream, 'The upload is handed to the file system as a stream');
                $streamedContent = stream_get_contents($stream);
                $streamedPath = $streamTransfer->getPath();
            });
        $this->tester->setDependency(DataExchangeDependencyProvider::SERVICE_FILE_SYSTEM, $fileSystemServiceMock);

        $importJob = $this->tester->haveImportJob([ImportJobTransfer::REFERENCE => 'test-stream-job']);
        $collectionRequest = $this->buildCollectionRequestWithUpload($importJob, $uploadedFilePath, strlen($content));

        try {
            // Act
            $collectionResponse = $this->tester->getFacade()->createImportJobRunCollection($collectionRequest);
        } finally {
            unlink($uploadedFilePath);
        }

        // Assert
        $this->assertCount(0, $collectionResponse->getErrors());
        $this->assertSame($content, $streamedContent, 'The stream carries the whole upload');
        $this->assertStringStartsWith(sprintf('%s/upload_', $importJob->getType()), (string)$streamedPath);
        $storedFileInfo = $collectionResponse->getImportJobRuns()->getIterator()->current()->getFileInfoOrFail();
        $this->assertNull($storedFileInfo->getUploadedFilePath(), 'The temporary upload path must not be persisted');
        $this->assertSame($streamedPath, $storedFileInfo->getStoredPath());
    }

    public function testReportsAnErrorWhenTheUploadedFileCannotBeOpened(): void
    {
        // Arrange
        $missingUploadPath = sys_get_temp_dir() . '/data-exchange-upload-missing-' . uniqid('', true);
        $fileSystemServiceMock = $this->createMock(FileSystemServiceInterface::class);
        $fileSystemServiceMock->expects($this->never())->method('writeStream');
        $this->tester->setDependency(DataExchangeDependencyProvider::SERVICE_FILE_SYSTEM, $fileSystemServiceMock);

        $importJob = $this->tester->haveImportJob([ImportJobTransfer::REFERENCE => 'test-missing-upload-job']);
        $collectionRequest = $this->buildCollectionRequestWithUpload($importJob, $missingUploadPath, 1);

        // Act
        $collectionResponse = $this->tester->getFacade()->createImportJobRunCollection($collectionRequest);

        // Assert
        $this->assertCount(0, $collectionResponse->getImportJobRuns(), 'No run is created without its file');
        $this->assertCount(1, $collectionResponse->getErrors());
        $this->assertSame(static::ERROR_MESSAGE_FILE_NOT_STORED, $collectionResponse->getErrors()->getIterator()->current()->getMessage());
    }

    protected function buildCollectionRequestWithUpload(
        ImportJobTransfer $importJob,
        string $uploadedFilePath,
        int $size
    ): ImportJobRunCollectionRequestTransfer {
        $fileInfo = (new ImportJobRunFileInfoTransfer())
            ->setOriginalFileName('upload.csv')
            ->setContentType('text/csv')
            ->setSize($size)
            ->setUploadedFilePath($uploadedFilePath);

        return (new ImportJobRunCollectionRequestTransfer())
            ->addImportJobRun((new ImportJobRunTransfer())->setImportJobReference($importJob->getReference())->setFileInfo($fileInfo));
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

    public function testCreatesTheImportJobRunAsPendingWhateverStatusTheRequestCarries(): void
    {
        // Arrange
        $this->tester->haveImportJob([
            ImportJobTransfer::REFERENCE => 'test-run-job',
        ]);

        $collectionRequest = (new ImportJobRunCollectionRequestTransfer())
            ->addImportJobRun(
                (new ImportJobRunTransfer())
                    ->setImportJobReference('test-run-job')
                    ->setStatus(DataExchangeBusinessTester::STATUS_DONE)
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
        $idImportJobRun = $collectionResponse->getImportJobRuns()->getIterator()->current()->getIdImportJobRunOrFail();
        $this->assertSame(DataExchangeBusinessTester::STATUS_PENDING, SpyImportJobRunQuery::create()->findPk($idImportJobRun)->getStatus());
    }

    public function testStoresTheUploadedFileAndMarksTheRunPending(): void
    {
        // Arrange
        $rootDirectory = $this->tester->configureLocalFileSystems();
        $collectionRequest = $this->createCollectionRequestWithUploadedFile($rootDirectory, $this->haveImportJobWithType());

        // Act
        $collectionResponse = $this->tester->getFacade()->createImportJobRunCollection($collectionRequest);

        // Assert
        $importJobRunEntity = SpyImportJobRunQuery::create()->findPk($collectionResponse->getImportJobRuns()->getIterator()->current()->getIdImportJobRunOrFail());
        $this->assertSame(DataExchangeBusinessTester::STATUS_PENDING, $importJobRunEntity->getStatus());
        $storedPath = json_decode($importJobRunEntity->getFileInfo(), true)['stored_path'];
        $this->assertStringEqualsFile($rootDirectory . '/' . $storedPath, "sku\nsku-1\n");
    }

    public function testMarksTheRunFailedWithTheErrorWhenTheUploadedFileCannotBeStored(): void
    {
        // Arrange
        $rootDirectory = $this->tester->configureLocalFileSystems();
        $this->tester->mockConfigMethod('getImportFileSystemName', static::FILE_SYSTEM_NAME_UNKNOWN);
        $importJobTransfer = $this->haveImportJobWithType();
        $collectionRequest = $this->createCollectionRequestWithUploadedFile($rootDirectory, $importJobTransfer);

        // Act
        $collectionResponse = $this->tester->getFacade()->createImportJobRunCollection($collectionRequest);

        // Assert
        $this->assertCount(1, $collectionResponse->getErrors());
        $this->assertCount(0, $collectionResponse->getImportJobRuns());
        $importJobRunEntity = SpyImportJobRunQuery::create()->filterByFkImportJob($importJobTransfer->getIdImportJobOrFail())->findOne();
        $this->assertSame(DataExchangeBusinessTester::STATUS_FAILED, $importJobRunEntity->getStatus());
        $this->assertNotNull($importJobRunEntity->getImportFinishedAt());
        $importJobRunErrorEntities = SpyImportJobRunErrorQuery::create()->filterByFkImportJobRun($importJobRunEntity->getIdImportJobRun())->find();
        $this->assertCount(1, $importJobRunErrorEntities);
        $this->assertSame(static::ERROR_MESSAGE_FILE_NOT_STORED, $collectionResponse->getErrors()->getIterator()->current()->getMessage());
        $this->assertSame(static::ERROR_MESSAGE_FILE_NOT_STORED, $importJobRunErrorEntities->getFirst()->getErrorMessage());
    }

    protected function haveImportJobWithType(): ImportJobTransfer
    {
        return $this->tester->haveImportJob([
            ImportJobTransfer::REFERENCE => 'test-run-job',
            ImportJobTransfer::TYPE => 'test-run-type',
        ]);
    }

    protected function createCollectionRequestWithUploadedFile(
        string $rootDirectory,
        ImportJobTransfer $importJobTransfer
    ): ImportJobRunCollectionRequestTransfer {
        $uploadedFilePath = $rootDirectory . '/upload.csv';
        file_put_contents($uploadedFilePath, "sku\nsku-1\n");

        return (new ImportJobRunCollectionRequestTransfer())
            ->addImportJobRun(
                (new ImportJobRunTransfer())
                    ->setImportJobReference($importJobTransfer->getReferenceOrFail())
                    ->setFileInfo(
                        (new ImportJobRunFileInfoTransfer())
                            ->setOriginalFileName('upload.csv')
                            ->setContentType('text/csv')
                            ->setSize(12)
                            ->setUploadedFilePath($uploadedFilePath),
                    ),
            );
    }
}

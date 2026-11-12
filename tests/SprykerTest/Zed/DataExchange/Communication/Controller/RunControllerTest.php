<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\Zed\DataExchange\Communication\Controller;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\ImportJobRunTransfer;
use Spryker\Zed\DataExchange\Communication\Controller\RunController;
use Spryker\Zed\DataExchange\Communication\DataExchangeCommunicationFactory;
use SprykerTest\Zed\DataExchange\DataExchangeCommunicationTester;
use Symfony\Component\HttpFoundation\Request;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Zed
 * @group DataExchange
 * @group Communication
 * @group Controller
 * @group RunControllerTest
 * Add your own group annotations below this line
 */
class RunControllerTest extends Unit
{
    protected const string PARAM_ID_IMPORT_JOB_RUN = 'idImportJobRun';

    protected const int ID_IMPORT_JOB_RUN_UNKNOWN = 999999999;

    protected DataExchangeCommunicationTester $tester;

    public function testErrorsActionReturnsTheRunErrorsWhenTheRunExists(): void
    {
        // Arrange
        $idImportJobRun = $this->haveImportJobRun()->getIdImportJobRunOrFail();

        // Act
        $viewData = (new RunController())->errorsAction($this->createRequest($idImportJobRun));

        // Assert
        $this->assertSame($idImportJobRun, $viewData['idImportJobRun']);
    }

    public function testErrorsActionReturnsNoErrorsWhenTheRunDoesNotExist(): void
    {
        // Act
        $viewData = (new RunController())->errorsAction($this->createRequest(static::ID_IMPORT_JOB_RUN_UNKNOWN));

        // Assert
        $this->assertCount(0, $viewData['errors']);
    }

    public function testDownloadErrorsActionReturnsACsvFileWhenTheRunExists(): void
    {
        // Arrange
        $idImportJobRun = $this->haveImportJobRun()->getIdImportJobRunOrFail();

        // Act
        $response = (new RunController())->downloadErrorsAction($this->createRequest($idImportJobRun));

        // Assert
        $this->assertSame('text/csv', $response->headers->get('Content-Type'));
    }

    public function testDownloadErrorsActionWritesAllErrorsWhenThereAreMoreErrorsThanTheBatchSize(): void
    {
        // Arrange
        $dataExchangeCommunicationFactory = new DataExchangeCommunicationFactory();
        $dataExchangeCommunicationFactory->setConfig($this->tester->mockConfigMethod('getImportErrorExportBatchSize', 2));
        $idImportJobRun = $this->haveImportJobRun()->getIdImportJobRunOrFail();

        foreach ([1, 2, 3, 4, 5] as $csvRowNumber) {
            $this->tester->haveImportJobRunError($idImportJobRun, [
                'csvRowNumber' => $csvRowNumber,
                'errorMessage' => 'Error on row ' . $csvRowNumber,
            ]);
        }

        // Act
        $response = $this->createRunController($dataExchangeCommunicationFactory)->downloadErrorsAction($this->createRequest($idImportJobRun));
        ob_start();
        $response->sendContent();
        $csv = (string)ob_get_clean();

        // Assert
        $this->assertSame(
            "\"Row Number\",\"Error Message\"\n1,\"Error on row 1\"\n2,\"Error on row 2\"\n3,\"Error on row 3\"\n4,\"Error on row 4\"\n5,\"Error on row 5\"\n",
            $csv,
        );
    }

    public function testDownloadErrorsActionReturnsACsvFileWithTheHeaderOnlyWhenTheRunDoesNotExist(): void
    {
        // Act
        $response = (new RunController())->downloadErrorsAction($this->createRequest(static::ID_IMPORT_JOB_RUN_UNKNOWN));
        ob_start();
        $response->sendContent();
        $csv = (string)ob_get_clean();

        // Assert
        $this->assertSame("\"Row Number\",\"Error Message\"\n", $csv);
    }

    protected function haveImportJobRun(): ImportJobRunTransfer
    {
        $importJobTransfer = $this->tester->haveImportJob();

        return $this->tester->haveImportJobRun([
            ImportJobRunTransfer::FK_IMPORT_JOB => $importJobTransfer->getIdImportJobOrFail(),
        ]);
    }

    protected function createRunController(DataExchangeCommunicationFactory $dataExchangeCommunicationFactory): RunController
    {
        return new class ($dataExchangeCommunicationFactory) extends RunController {
            public function __construct(DataExchangeCommunicationFactory $dataExchangeCommunicationFactory)
            {
                $this->factory = $dataExchangeCommunicationFactory;
            }
        };
    }

    protected function createRequest(int $idImportJobRun): Request
    {
        return new Request([static::PARAM_ID_IMPORT_JOB_RUN => $idImportJobRun]);
    }
}

<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\Zed\DataExchange\Business\Facade;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\ImportJobRunErrorConditionsTransfer;
use Generated\Shared\Transfer\ImportJobRunErrorCriteriaTransfer;
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
 * @group GetImportJobRunErrorCollectionTest
 * Add your own group annotations below this line
 */
class GetImportJobRunErrorCollectionTest extends Unit
{
    protected DataExchangeBusinessTester $tester;

    public function testReturnsEmptyCollectionWhenNoErrorsExist(): void
    {
        // Arrange
        $importJob = $this->tester->haveImportJob();
        $importJobRun = $this->tester->haveImportJobRun([
            ImportJobRunTransfer::FK_IMPORT_JOB => $importJob->getIdImportJob(),
        ]);

        $criteriaTransfer = (new ImportJobRunErrorCriteriaTransfer())
            ->setImportJobRunErrorConditions(
                (new ImportJobRunErrorConditionsTransfer())
                    ->addIdImportJobRun($importJobRun->getIdImportJobRun()),
            );

        // Act
        $errorCollection = $this->tester->getFacade()->getImportJobRunErrorCollection($criteriaTransfer);

        // Assert
        $this->assertCount(0, $errorCollection->getImportJobRunErrors());
    }

    public function testReturnsErrorsOrderedByCsvRowNumber(): void
    {
        // Arrange
        $importJob = $this->tester->haveImportJob();
        $importJobRun = $this->tester->haveImportJobRun([
            ImportJobRunTransfer::FK_IMPORT_JOB => $importJob->getIdImportJob(),
        ]);

        $this->tester->haveImportJobRunError($importJobRun->getIdImportJobRun(), [
            'csvRowNumber' => 5,
            'errorMessage' => 'Error on row 5',
        ]);
        $this->tester->haveImportJobRunError($importJobRun->getIdImportJobRun(), [
            'csvRowNumber' => 2,
            'errorMessage' => 'Error on row 2',
        ]);
        $this->tester->haveImportJobRunError($importJobRun->getIdImportJobRun(), [
            'csvRowNumber' => 8,
            'errorMessage' => 'Error on row 8',
        ]);

        $criteriaTransfer = (new ImportJobRunErrorCriteriaTransfer())
            ->setImportJobRunErrorConditions(
                (new ImportJobRunErrorConditionsTransfer())
                    ->addIdImportJobRun($importJobRun->getIdImportJobRun()),
            );

        // Act
        $errorCollection = $this->tester->getFacade()->getImportJobRunErrorCollection($criteriaTransfer);

        // Assert
        $this->assertCount(3, $errorCollection->getImportJobRunErrors());

        $errors = $errorCollection->getImportJobRunErrors();
        $this->assertSame(2, $errors->offsetGet(0)->getCsvRowNumber());
        $this->assertSame(5, $errors->offsetGet(1)->getCsvRowNumber());
        $this->assertSame(8, $errors->offsetGet(2)->getCsvRowNumber());
    }

    public function testSumsTheOmittedErrorRowsOfTheSelectedRunsIntoTheResponse(): void
    {
        // Arrange
        $importJob = $this->tester->haveImportJob();
        $run1 = $this->tester->haveImportJobRun([
            ImportJobRunTransfer::FK_IMPORT_JOB => $importJob->getIdImportJob(),
            ImportJobRunTransfer::NUMBER_OF_OMITTED_ERROR_ROWS => 4,
        ]);
        $run2 = $this->tester->haveImportJobRun([
            ImportJobRunTransfer::FK_IMPORT_JOB => $importJob->getIdImportJob(),
            ImportJobRunTransfer::NUMBER_OF_OMITTED_ERROR_ROWS => 2,
        ]);
        $this->tester->haveImportJobRun([
            ImportJobRunTransfer::FK_IMPORT_JOB => $importJob->getIdImportJob(),
            ImportJobRunTransfer::NUMBER_OF_OMITTED_ERROR_ROWS => 9,
        ]);
        $this->tester->haveImportJobRunError($run1->getIdImportJobRun(), [
            'csvRowNumber' => 1,
            'errorMessage' => 'Run 1 error',
        ]);

        $criteriaTransfer = (new ImportJobRunErrorCriteriaTransfer())
            ->setImportJobRunErrorConditions(
                (new ImportJobRunErrorConditionsTransfer())
                    ->addIdImportJobRun($run1->getIdImportJobRun())
                    ->addIdImportJobRun($run2->getIdImportJobRun()),
            );

        // Act
        $errorCollection = $this->tester->getFacade()->getImportJobRunErrorCollection($criteriaTransfer);

        // Assert
        $this->assertSame(6, $errorCollection->getNumberOfOmittedErrorRows(), 'Only the runs the criteria selects are summed');
        $this->assertCount(1, $errorCollection->getImportJobRunErrors());
    }

    public function testReturnsZeroOmittedErrorRowsWithoutRunConditions(): void
    {
        // Arrange
        $importJob = $this->tester->haveImportJob();
        $this->tester->haveImportJobRun([
            ImportJobRunTransfer::FK_IMPORT_JOB => $importJob->getIdImportJob(),
            ImportJobRunTransfer::NUMBER_OF_OMITTED_ERROR_ROWS => 3,
        ]);

        // Act
        $errorCollection = $this->tester->getFacade()->getImportJobRunErrorCollection(new ImportJobRunErrorCriteriaTransfer());

        // Assert
        $this->assertSame(0, $errorCollection->getNumberOfOmittedErrorRows());
    }

    public function testReturnsOnlyErrorsForSpecificRun(): void
    {
        // Arrange
        $importJob = $this->tester->haveImportJob();
        $run1 = $this->tester->haveImportJobRun([
            ImportJobRunTransfer::FK_IMPORT_JOB => $importJob->getIdImportJob(),
        ]);
        $run2 = $this->tester->haveImportJobRun([
            ImportJobRunTransfer::FK_IMPORT_JOB => $importJob->getIdImportJob(),
        ]);

        $this->tester->haveImportJobRunError($run1->getIdImportJobRun(), [
            'csvRowNumber' => 1,
            'errorMessage' => 'Run 1 error',
        ]);
        $this->tester->haveImportJobRunError($run2->getIdImportJobRun(), [
            'csvRowNumber' => 3,
            'errorMessage' => 'Run 2 error',
        ]);

        $criteriaTransfer = (new ImportJobRunErrorCriteriaTransfer())
            ->setImportJobRunErrorConditions(
                (new ImportJobRunErrorConditionsTransfer())
                    ->addIdImportJobRun($run1->getIdImportJobRun()),
            );

        // Act
        $errorCollection = $this->tester->getFacade()->getImportJobRunErrorCollection($criteriaTransfer);

        // Assert
        $this->assertCount(1, $errorCollection->getImportJobRunErrors());
        $this->assertSame('Run 1 error', $errorCollection->getImportJobRunErrors()->getIterator()->current()->getErrorMessage());
    }
}

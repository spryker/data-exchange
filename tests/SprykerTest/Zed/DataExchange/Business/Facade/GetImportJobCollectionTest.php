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
use SprykerTest\Zed\DataExchange\DataExchangeBusinessTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Zed
 * @group DataExchange
 * @group Business
 * @group Facade
 * @group GetImportJobCollectionTest
 * Add your own group annotations below this line
 */
class GetImportJobCollectionTest extends Unit
{
    protected DataExchangeBusinessTester $tester;

    public function testReturnsEmptyCollectionWhenNoJobsExist(): void
    {
        // Arrange
        $criteriaTransfer = (new ImportJobCriteriaTransfer())
            ->setImportJobConditions(
                (new ImportJobConditionsTransfer())->addReference('non-existent-reference'),
            );

        // Act
        $collectionTransfer = $this->tester->getFacade()->getImportJobCollection($criteriaTransfer);

        // Assert
        $this->assertCount(0, $collectionTransfer->getImportJobs());
    }

    public function testReturnsAllJobs(): void
    {
        // Arrange
        $importJobTransferA = $this->tester->haveImportJob([
            ImportJobTransfer::NAME => 'Job A',
        ]);
        $importJobTransferB = $this->tester->haveImportJob([
            ImportJobTransfer::NAME => 'Job B',
        ]);

        $criteriaTransfer = (new ImportJobCriteriaTransfer())
            ->setImportJobConditions(
                (new ImportJobConditionsTransfer())
                    ->addReference($importJobTransferA->getReferenceOrFail())
                    ->addReference($importJobTransferB->getReferenceOrFail()),
            );

        // Act
        $collectionTransfer = $this->tester->getFacade()->getImportJobCollection($criteriaTransfer);

        // Assert
        $this->assertCount(2, $collectionTransfer->getImportJobs());
    }

    public function testFiltersJobsByReference(): void
    {
        // Arrange
        $importJob = $this->tester->haveImportJob([
            ImportJobTransfer::NAME => 'Target Job',
        ]);
        $this->tester->haveImportJob([
            ImportJobTransfer::NAME => 'Other Job',
        ]);

        $criteriaTransfer = (new ImportJobCriteriaTransfer())
            ->setImportJobConditions(
                (new ImportJobConditionsTransfer())->addReference($importJob->getReferenceOrFail()),
            );

        // Act
        $collectionTransfer = $this->tester->getFacade()->getImportJobCollection($criteriaTransfer);

        // Assert
        $this->assertCount(1, $collectionTransfer->getImportJobs());
        $this->assertSame(
            $importJob->getReferenceOrFail(),
            $collectionTransfer->getImportJobs()->getIterator()->current()->getReference(),
        );
    }

    public function testReturnsJobWithAllFields(): void
    {
        // Arrange
        $definition = [
            ['property_name_in_file' => 'Abstract SKU', 'system_property_name' => 'abstract_sku'],
        ];
        $importJobTransfer = $this->tester->haveImportJob([
            ImportJobTransfer::NAME => 'Full Job',
            ImportJobTransfer::TYPE => 'demo-csv',
            ImportJobTransfer::DEFINITION => $definition,
            ImportJobTransfer::DESCRIPTION => 'Test description',
        ]);

        $criteriaTransfer = (new ImportJobCriteriaTransfer())
            ->setImportJobConditions(
                (new ImportJobConditionsTransfer())->addReference($importJobTransfer->getReferenceOrFail()),
            );

        // Act
        $collectionTransfer = $this->tester->getFacade()->getImportJobCollection($criteriaTransfer);

        // Assert
        $job = $collectionTransfer->getImportJobs()->getIterator()->current();
        $this->assertSame('Full Job', $job->getName());
        $this->assertSame($importJobTransfer->getReferenceOrFail(), $job->getReference());
        $this->assertSame('demo-csv', $job->getType());
        $this->assertSame('Test description', $job->getDescription());
        $this->assertSame($definition, $job->getDefinition());
    }
}

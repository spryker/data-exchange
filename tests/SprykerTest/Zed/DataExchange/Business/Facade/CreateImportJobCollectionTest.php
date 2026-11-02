<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\Zed\DataExchange\Business\Facade;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\ImportJobCollectionRequestTransfer;
use Generated\Shared\Transfer\ImportJobTransfer;
use Spryker\Zed\DataExchange\DataExchangeDependencyProvider;
use SprykerTest\Zed\DataExchange\DataExchangeBusinessTester;
use SprykerTest\Zed\DataExchange\Plugin\DemoCsvImportSchemaPlugin;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Zed
 * @group DataExchange
 * @group Business
 * @group Facade
 * @group CreateImportJobCollectionTest
 * Add your own group annotations below this line
 */
class CreateImportJobCollectionTest extends Unit
{
    protected DataExchangeBusinessTester $tester;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tester->setDependency(
            DataExchangeDependencyProvider::PLUGINS_IMPORT_SCHEMA,
            [new DemoCsvImportSchemaPlugin()],
        );
    }

    public function testCreatesImportJobWithGeneratedReference(): void
    {
        // Arrange
        $collectionRequest = (new ImportJobCollectionRequestTransfer())
            ->addImportJob(
                (new ImportJobTransfer())
                    ->setName('My Test Job')
                    ->setType(DemoCsvImportSchemaPlugin::TYPE),
            );

        // Act
        $collectionResponse = $this->tester->getFacade()->createImportJobCollection($collectionRequest);

        // Assert
        $this->assertCount(0, $collectionResponse->getErrors());
        $this->assertCount(1, $collectionResponse->getImportJobs());

        $createdJob = $collectionResponse->getImportJobs()->getIterator()->current();
        $this->assertNotNull($createdJob->getIdImportJob());
        $this->assertSame('My Test Job', $createdJob->getName());
        $this->assertSame(DemoCsvImportSchemaPlugin::TYPE, $createdJob->getType());
        $this->assertSame('my-test-job', $createdJob->getReference());
    }

    public function testCreatesImportJobWithExplicitReference(): void
    {
        // Arrange
        $collectionRequest = (new ImportJobCollectionRequestTransfer())
            ->addImportJob(
                (new ImportJobTransfer())
                    ->setName('Explicit Ref Job')
                    ->setType(DemoCsvImportSchemaPlugin::TYPE)
                    ->setReference('explicit-ref'),
            );

        // Act
        $collectionResponse = $this->tester->getFacade()->createImportJobCollection($collectionRequest);

        // Assert
        $this->assertCount(0, $collectionResponse->getErrors());
        $createdJob = $collectionResponse->getImportJobs()->getIterator()->current();
        $this->assertSame('explicit-ref', $createdJob->getReference());
    }

    public function testReturnsErrorForUnknownJobType(): void
    {
        // Arrange
        $collectionRequest = (new ImportJobCollectionRequestTransfer())
            ->addImportJob(
                (new ImportJobTransfer())
                    ->setName('Unknown Type Job')
                    ->setType('non-existent-type'),
            );

        // Act
        $collectionResponse = $this->tester->getFacade()->createImportJobCollection($collectionRequest);

        // Assert
        $this->assertCount(1, $collectionResponse->getErrors());
        $this->assertCount(0, $collectionResponse->getImportJobs());
        $this->assertSame(
            'No schema found for job type "non-existent-type".',
            $collectionResponse->getErrors()->getIterator()->current()->getMessage(),
        );
    }

    public function testSkipsInvalidJobAndCreatesValidOneInSameRequest(): void
    {
        // Arrange
        $collectionRequest = (new ImportJobCollectionRequestTransfer())
            ->addImportJob(
                (new ImportJobTransfer())
                    ->setName('Valid Job')
                    ->setType(DemoCsvImportSchemaPlugin::TYPE),
            )
            ->addImportJob(
                (new ImportJobTransfer())
                    ->setName('Invalid Job')
                    ->setType('non-existent-type'),
            );

        // Act
        $collectionResponse = $this->tester->getFacade()->createImportJobCollection($collectionRequest);

        // Assert
        $this->assertCount(1, $collectionResponse->getErrors());
        $this->assertCount(1, $collectionResponse->getImportJobs());
        $this->assertSame('Valid Job', $collectionResponse->getImportJobs()->getIterator()->current()->getName());
    }

    public function testPopulatesDefinitionFromSchemaPlugin(): void
    {
        // Arrange
        $collectionRequest = (new ImportJobCollectionRequestTransfer())
            ->addImportJob(
                (new ImportJobTransfer())
                    ->setName('Schema Job')
                    ->setType(DemoCsvImportSchemaPlugin::TYPE),
            );

        // Act
        $collectionResponse = $this->tester->getFacade()->createImportJobCollection($collectionRequest);

        // Assert
        $createdJob = $collectionResponse->getImportJobs()->getIterator()->current();
        $this->assertSame((new DemoCsvImportSchemaPlugin())->getSchema(), $createdJob->getDefinition());
    }
}

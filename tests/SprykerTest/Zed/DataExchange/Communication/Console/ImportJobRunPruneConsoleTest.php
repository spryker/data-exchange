<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\Zed\DataExchange\Communication\Console;

use Codeception\Test\Unit;
use Generated\Shared\Transfer\ErrorTransfer;
use Generated\Shared\Transfer\ImportJobRunDeleteResponseTransfer;
use Spryker\Zed\DataExchange\Communication\Console\ImportJobRunPruneConsole;
use SprykerTest\Zed\DataExchange\DataExchangeCommunicationTester;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group Zed
 * @group DataExchange
 * @group Communication
 * @group Console
 * @group ImportJobRunPruneConsoleTest
 * Add your own group annotations below this line
 */
class ImportJobRunPruneConsoleTest extends Unit
{
    protected DataExchangeCommunicationTester $tester;

    public function testReportsThePrunedRunsAndExitsWithSuccess(): void
    {
        // Arrange
        $importJobRunDeleteResponseTransfer = (new ImportJobRunDeleteResponseTransfer())->setDeletedImportJobRunCount(2);

        // Act
        $commandTester = $this->runConsole($importJobRunDeleteResponseTransfer);

        // Assert
        $this->assertSame(ImportJobRunPruneConsole::CODE_SUCCESS, $commandTester->getStatusCode());
        $this->assertStringContainsString('Pruned 2 import job run(s).', $commandTester->getDisplay());
    }

    public function testExitsWithSuccessWhenNothingIsLeftToPrune(): void
    {
        // Act
        $commandTester = $this->runConsole((new ImportJobRunDeleteResponseTransfer())->setDeletedImportJobRunCount(0));

        // Assert
        $this->assertSame(ImportJobRunPruneConsole::CODE_SUCCESS, $commandTester->getStatusCode());
        $this->assertStringContainsString('Pruned 0 import job run(s).', $commandTester->getDisplay());
    }

    public function testPrintsEveryErrorAndExitsWithErrorWhenARunWasKept(): void
    {
        // Arrange
        $importJobRunDeleteResponseTransfer = (new ImportJobRunDeleteResponseTransfer())
            ->setDeletedImportJobRunCount(1)
            ->addError((new ErrorTransfer())->setMessage('Import job run #7 could not be deleted.'));

        // Act
        $commandTester = $this->runConsole($importJobRunDeleteResponseTransfer);

        // Assert
        $this->assertSame(ImportJobRunPruneConsole::CODE_ERROR, $commandTester->getStatusCode());
        $this->assertStringContainsString('Import job run #7 could not be deleted.', $commandTester->getDisplay());
        $this->assertStringContainsString('Pruned 1 import job run(s).', $commandTester->getDisplay());
    }

    protected function runConsole(ImportJobRunDeleteResponseTransfer $importJobRunDeleteResponseTransfer): CommandTester
    {
        $console = new ImportJobRunPruneConsole();
        $console->setFacade($this->tester->mockFacadeMethod('pruneImportJobRuns', $importJobRunDeleteResponseTransfer));

        $commandTester = new CommandTester($console);
        $commandTester->execute([]);

        return $commandTester;
    }
}

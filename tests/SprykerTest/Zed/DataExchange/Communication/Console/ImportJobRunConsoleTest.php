<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\Zed\DataExchange\Communication\Console;

use Codeception\Test\Unit;
use Spryker\Zed\DataExchange\Communication\Console\Exception\InvalidImportJobRunsPerInvocationException;
use Spryker\Zed\DataExchange\Communication\Console\ImportJobRunConsole;
use Spryker\Zed\DataExchange\DataExchangeConfig;
use Spryker\Zed\Kernel\Business\AbstractFacade;
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
 * @group ImportJobRunConsoleTest
 * Add your own group annotations below this line
 */
class ImportJobRunConsoleTest extends Unit
{
    protected DataExchangeCommunicationTester $tester;

    public function testProcessesRunsUntilTheConfiguredBoundAndExits(): void
    {
        // Arrange
        /** @var \Spryker\Zed\DataExchange\DataExchangeConfig $configStub */
        $configStub = $this->tester->mockConfigMethod('getImportJobRunsPerInvocation', 2);
        /** @var \Spryker\Zed\DataExchange\Business\DataExchangeFacade&\PHPUnit\Framework\MockObject\MockObject $facadeMock */
        $facadeMock = $this->tester->mockFacadeMethod('processNextPendingRun', true);
        $facadeMock->expects($this->exactly(2))->method('processNextPendingRun');

        // Act
        $commandTester = $this->runConsole($facadeMock, $configStub);

        // Assert
        $this->assertSame(ImportJobRunConsole::CODE_SUCCESS, $commandTester->getStatusCode());
        $this->assertStringContainsString('Processed 2 import job run(s).', $commandTester->getDisplay());
    }

    public function testStopsBeforeTheBoundWhenNoPendingRunIsLeft(): void
    {
        // Arrange
        /** @var \Spryker\Zed\DataExchange\DataExchangeConfig $configStub */
        $configStub = $this->tester->mockConfigMethod('getImportJobRunsPerInvocation', 5);
        $processedRuns = 0;
        $facadeMock = $this->tester->mockFacadeMethod('processNextPendingRun', function () use (&$processedRuns): bool {
            return ++$processedRuns === 1;
        });

        // Act
        $commandTester = $this->runConsole($facadeMock, $configStub);

        // Assert
        $this->assertSame(ImportJobRunConsole::CODE_SUCCESS, $commandTester->getStatusCode());
        $this->assertStringContainsString('No pending import job run left.', $commandTester->getDisplay());
        $this->assertStringContainsString('Processed 1 import job run(s).', $commandTester->getDisplay());
    }

    public function testProcessesExactlyOneRunByDefault(): void
    {
        // Arrange
        /** @var \Spryker\Zed\DataExchange\Business\DataExchangeFacade&\PHPUnit\Framework\MockObject\MockObject $facadeMock */
        $facadeMock = $this->tester->mockFacadeMethod('processNextPendingRun', true);
        $facadeMock->expects($this->once())->method('processNextPendingRun');

        // Act
        $commandTester = $this->runConsole($facadeMock);

        // Assert
        $this->assertStringContainsString('Processed 1 import job run(s).', $commandTester->getDisplay());
    }

    /**
     * @dataProvider runsPerInvocationBelowOneDataProvider
     */
    public function testRefusesToStartWhenTheConfiguredBoundIsBelowOne(int $runsPerInvocation): void
    {
        // Arrange
        /** @var \Spryker\Zed\DataExchange\DataExchangeConfig $configStub */
        $configStub = $this->tester->mockConfigMethod('getImportJobRunsPerInvocation', $runsPerInvocation);
        /** @var \Spryker\Zed\DataExchange\Business\DataExchangeFacade&\PHPUnit\Framework\MockObject\MockObject $facadeMock */
        $facadeMock = $this->tester->mockFacadeMethod('processNextPendingRun', true);
        $facadeMock->expects($this->never())->method('processNextPendingRun');

        // Assert
        $this->expectException(InvalidImportJobRunsPerInvocationException::class);
        $this->expectExceptionMessage(sprintf('must return 1 or greater, %d given.', $runsPerInvocation));

        // Act
        $this->runConsole($facadeMock, $configStub);
    }

    /**
     * @return array<string, array<int, int>>
     */
    public static function runsPerInvocationBelowOneDataProvider(): array
    {
        return [
            'zero' => [0],
            'negative' => [-1],
        ];
    }

    protected function runConsole(AbstractFacade $facade, ?DataExchangeConfig $config = null): CommandTester
    {
        $console = new ImportJobRunConsole();
        $console->setFacade($facade);

        if ($config !== null) {
            $console->setConfig($config);
        }

        $commandTester = new CommandTester($console);
        $commandTester->execute([]);

        return $commandTester;
    }
}

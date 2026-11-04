<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Communication\Console;

use Spryker\Zed\DataExchange\Communication\Console\Exception\InvalidImportJobRunsPerInvocationException;
use Spryker\Zed\Kernel\BundleConfigResolverAwareTrait;
use Spryker\Zed\Kernel\Communication\Console\Console;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @method \Spryker\Zed\DataExchange\Business\DataExchangeFacade getFacade()
 * @method \Spryker\Zed\DataExchange\DataExchangeConfig getConfig()
 */
class ImportJobRunConsole extends Console
{
    use BundleConfigResolverAwareTrait;

    protected const string COMMAND_NAME = 'import:job:run';

    protected const string COMMAND_DESCRIPTION = 'Processes pending import job runs, oldest first, up to the configured number per invocation.';

    protected function configure(): void
    {
        $this->setName(static::COMMAND_NAME)
            ->setDescription(static::COMMAND_DESCRIPTION);
    }

    /**
     * @throws \Spryker\Zed\DataExchange\Communication\Console\Exception\InvalidImportJobRunsPerInvocationException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $runsPerInvocation = $this->getConfig()->getImportJobRunsPerInvocation();

        if ($runsPerInvocation < 1) {
            throw new InvalidImportJobRunsPerInvocationException(sprintf(
                'DataExchangeConfig::getImportJobRunsPerInvocation() must return 1 or greater, %d given.',
                $runsPerInvocation,
            ));
        }

        $processedRuns = 0;

        while ($processedRuns < $runsPerInvocation) {
            $output->writeln('Processing next pending import job run...');

            if (!$this->getFacade()->processNextPendingRun()) {
                $output->writeln('No pending import job run left.');

                break;
            }

            $processedRuns++;
        }

        $output->writeln(sprintf('Done. Processed %d import job run(s).', $processedRuns));

        return static::CODE_SUCCESS;
    }
}

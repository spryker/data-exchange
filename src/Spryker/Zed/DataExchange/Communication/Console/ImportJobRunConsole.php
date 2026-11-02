<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Communication\Console;

use Spryker\Zed\Kernel\Communication\Console\Console;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @method \Spryker\Zed\DataExchange\Business\DataExchangeFacade getFacade()
 */
class ImportJobRunConsole extends Console
{
    protected const string COMMAND_NAME = 'import:job:run';

    protected const string COMMAND_DESCRIPTION = 'Processes the oldest pending import job run.';

    protected function configure(): void
    {
        $this->setName(static::COMMAND_NAME)
            ->setDescription(static::COMMAND_DESCRIPTION);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Processing next pending import job run...');

        $this->getFacade()->processNextPendingRun();

        $output->writeln('Done.');

        return static::CODE_SUCCESS;
    }
}

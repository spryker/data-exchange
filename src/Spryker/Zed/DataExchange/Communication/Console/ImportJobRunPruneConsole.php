<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Communication\Console;

use Generated\Shared\Transfer\ImportJobRunCriteriaTransfer;
use Spryker\Zed\Kernel\Communication\Console\Console;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @method \Spryker\Zed\DataExchange\Business\DataExchangeFacadeInterface getFacade()
 */
class ImportJobRunPruneConsole extends Console
{
    protected const string COMMAND_NAME = 'import:job:prune';

    protected const string COMMAND_DESCRIPTION = 'Deletes finished and never completed import job runs older than the configured retention, with their stored files and errors.';

    protected function configure(): void
    {
        $this->setName(static::COMMAND_NAME)
            ->setDescription(static::COMMAND_DESCRIPTION);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $importJobRunDeleteResponseTransfer = $this->getFacade()->pruneImportJobRuns(new ImportJobRunCriteriaTransfer());

        foreach ($importJobRunDeleteResponseTransfer->getErrors() as $errorTransfer) {
            $output->writeln(sprintf('<error>%s</error>', $errorTransfer->getMessage()));
        }

        $output->writeln(sprintf('Done. Pruned %d import job run(s).', $importJobRunDeleteResponseTransfer->getDeletedImportJobRunCountOrFail()));

        if ($importJobRunDeleteResponseTransfer->getErrors()->count() > 0) {
            return static::CODE_ERROR;
        }

        return static::CODE_SUCCESS;
    }
}

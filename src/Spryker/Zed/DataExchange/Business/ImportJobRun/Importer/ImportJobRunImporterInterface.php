<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Business\ImportJobRun\Importer;

use Generated\Shared\Transfer\ImportJobRunTransfer;

interface ImportJobRunImporterInterface
{
    /**
     * Reads the CSV file, processes rows through import steps in batches, triggers publish events, and updates the job run with results.
     */
    public function importJobRun(ImportJobRunTransfer $importJobRun): void;
}

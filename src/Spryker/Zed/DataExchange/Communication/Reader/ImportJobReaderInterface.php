<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Communication\Reader;

use Generated\Shared\Transfer\ImportJobRunTransfer;
use Generated\Shared\Transfer\ImportJobTransfer;

interface ImportJobReaderInterface
{
    public function findImportJobById(int $idImportJob): ?ImportJobTransfer;

    public function findImportJobByReference(string $importJobReference): ?ImportJobTransfer;

    public function findImportJobRun(int $idImportJobRun): ?ImportJobRunTransfer;
}

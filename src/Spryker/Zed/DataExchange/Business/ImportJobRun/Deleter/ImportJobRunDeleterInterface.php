<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Business\ImportJobRun\Deleter;

use ArrayObject;
use Generated\Shared\Transfer\ImportJobRunDeleteResponseTransfer;

interface ImportJobRunDeleterInterface
{
    /**
     * @param \ArrayObject<int, \Generated\Shared\Transfer\ImportJobRunTransfer> $importJobRunTransfers
     */
    public function deleteImportJobRuns(
        ArrayObject $importJobRunTransfers,
        ImportJobRunDeleteResponseTransfer $importJobRunDeleteResponseTransfer
    ): ImportJobRunDeleteResponseTransfer;
}

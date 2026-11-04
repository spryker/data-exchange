<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Business\ImportJobRun\Reader;

use Generated\Shared\Transfer\ImportJobRunCollectionTransfer;
use Generated\Shared\Transfer\ImportJobRunCriteriaTransfer;

interface ImportJobRunReaderInterface
{
    public function getImportJobRunCollection(ImportJobRunCriteriaTransfer $importJobRunCriteriaTransfer): ImportJobRunCollectionTransfer;
}

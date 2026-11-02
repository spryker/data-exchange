<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Business\ImportJobRun\Writer;

use Generated\Shared\Transfer\ImportJobRunCollectionRequestTransfer;
use Generated\Shared\Transfer\ImportJobRunCollectionResponseTransfer;

interface ImportJobRunWriterInterface
{
    public function createImportJobRunCollection(ImportJobRunCollectionRequestTransfer $collectionRequestTransfer): ImportJobRunCollectionResponseTransfer;
}

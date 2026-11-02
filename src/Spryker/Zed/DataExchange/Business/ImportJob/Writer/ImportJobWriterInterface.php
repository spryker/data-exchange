<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Business\ImportJob\Writer;

use Generated\Shared\Transfer\ImportJobCollectionRequestTransfer;
use Generated\Shared\Transfer\ImportJobCollectionResponseTransfer;

interface ImportJobWriterInterface
{
    /**
     * Creates import jobs from the collection request, auto-generating references and resolving definitions from schema plugins.
     */
    public function createImportJobCollection(ImportJobCollectionRequestTransfer $collectionRequestTransfer): ImportJobCollectionResponseTransfer;
}

<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Business\ImportJobRun\Writer;

use Generated\Shared\Transfer\ImportJobRunFileInfoTransfer;

interface ImportFileWriterInterface
{
    /**
     * Writes the uploaded file content to the configured filesystem and returns updated file info with stored path.
     */
    public function writeFile(ImportJobRunFileInfoTransfer $fileInfoTransfer, string $jobType): ImportJobRunFileInfoTransfer;
}

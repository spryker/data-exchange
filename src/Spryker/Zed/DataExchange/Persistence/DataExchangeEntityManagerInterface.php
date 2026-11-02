<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Persistence;

use Generated\Shared\Transfer\ImportJobRunErrorTransfer;
use Generated\Shared\Transfer\ImportJobRunTransfer;
use Generated\Shared\Transfer\ImportJobTransfer;

interface DataExchangeEntityManagerInterface
{
    public function createImportJob(ImportJobTransfer $importJobTransfer): ImportJobTransfer;

    public function updateImportJob(ImportJobTransfer $importJobTransfer): ImportJobTransfer;

    public function createImportJobRun(ImportJobRunTransfer $importJobRunTransfer): ImportJobRunTransfer;

    public function updateImportJobRun(ImportJobRunTransfer $importJobRunTransfer): ImportJobRunTransfer;

    public function createImportJobRunError(ImportJobRunErrorTransfer $importJobRunErrorTransfer): ImportJobRunErrorTransfer;
}

<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Business\ImportJobRun\Manager;

interface ImportJobRunManagerInterface
{
    /**
     * Claims the oldest claimable import job run, manages its lifecycle, and delegates CSV import to the importer.
     *
     * @return bool `true` when a run was claimed, `false` when no claimable run was left.
     */
    public function processNextPendingRun(): bool;
}

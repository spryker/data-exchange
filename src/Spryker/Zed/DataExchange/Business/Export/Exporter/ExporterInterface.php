<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Business\Export\Exporter;

use Generated\Shared\Transfer\ImportJobCriteriaTransfer;
use Generated\Shared\Transfer\ImportJobExportResultTransfer;

interface ExporterInterface
{
    /**
     * Resolves the job and expands columns from schema definition.
     * When `ImportJobCriteriaTransfer.isWithData` is true, runs export steps and populates rows.
     * Otherwise returns columns only (template mode).
     * Returns an unsuccessful result when no import job matches the provided criteria.
     */
    public function exportData(ImportJobCriteriaTransfer $criteriaTransfer): ImportJobExportResultTransfer;
}

<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Business\Export\ColumnResolver;

use Generated\Shared\Transfer\ImportJobTransfer;

interface ExportColumnResolverInterface
{
    /**
     * Expands schema pattern definitions into concrete column names
     * using actual system placeholder values.
     *
     * @param array<string, array<string>> $placeholderValues Maps placeholder names to their possible values.
     *
     * @return array<string>
     */
    public function resolveColumnHeaders(ImportJobTransfer $importJobTransfer, array $placeholderValues): array;
}

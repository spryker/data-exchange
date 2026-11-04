<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\Zed\DataExchange\Plugin;

use Generated\Shared\Transfer\ImportStepResponseTransfer;
use Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ImportStepInterface;

/**
 * A step written against `ImportStepInterface` only, without `CacheResettableImportStepInterface`: stands in for a
 * third-party step that predates the per-batch cache reset and must keep running unchanged.
 */
class DemoPlainImportStep implements ImportStepInterface
{
    /**
     * @var array<int, array<int, array<string, string>>>
     */
    protected array $receivedBatches = [];

    /**
     * {@inheritDoc}
     */
    public function executeBatch(array $rows, array $propertyNamesInFile = []): ImportStepResponseTransfer
    {
        $this->receivedBatches[] = $rows;

        return (new ImportStepResponseTransfer())->setIsSuccessful(true);
    }

    /**
     * @return array<int, array<int, array<string, string>>>
     */
    public function getReceivedBatches(): array
    {
        return $this->receivedBatches;
    }
}

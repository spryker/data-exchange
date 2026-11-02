<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\Zed\DataExchange\Plugin;

use Generated\Shared\Transfer\ImportPublishEventTransfer;
use Generated\Shared\Transfer\ImportStepErrorTransfer;
use Generated\Shared\Transfer\ImportStepResponseTransfer;
use Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ImportStepInterface;

/**
 * Records every batch it receives and reports rows whose `sku` starts with `fail-` as errors,
 * so tests can assert mapping, counters, error rows and publish events.
 */
class DemoCsvImportStep implements ImportStepInterface
{
    public const string EVENT_NAME = 'Demo.entity.publish';

    public const string FAILING_SKU_PREFIX = 'fail-';

    /**
     * @var array<int, array<int, array<string, string>>>
     */
    protected array $receivedBatches = [];

    /**
     * @var array<int, array<string, string>>
     */
    protected array $receivedPropertyNamesInFile = [];

    /**
     * {@inheritDoc}
     */
    public function executeBatch(array $rows, array $propertyNamesInFile = []): ImportStepResponseTransfer
    {
        $this->receivedBatches[] = $rows;
        $this->receivedPropertyNamesInFile[] = $propertyNamesInFile;

        $response = (new ImportStepResponseTransfer())->setIsSuccessful(true);

        foreach ($rows as $rowNumber => $row) {
            if (str_starts_with($row['sku'] ?? '', static::FAILING_SKU_PREFIX)) {
                $response->setIsSuccessful(false);
                $response->addError((new ImportStepErrorTransfer())->setCsvRowNumber($rowNumber)->setErrorMessage(sprintf('Row %d rejected by demo step.', $rowNumber)));

                continue;
            }

            $response->addPublishEvent((new ImportPublishEventTransfer())->setEventName(static::EVENT_NAME)->setEntityId($rowNumber));
        }

        return $response;
    }

    /**
     * @return array<int, array<int, array<string, string>>>
     */
    public function getReceivedBatches(): array
    {
        return $this->receivedBatches;
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function getReceivedPropertyNamesInFile(): array
    {
        return $this->receivedPropertyNamesInFile;
    }
}

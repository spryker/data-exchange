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
use Propel\Runtime\Propel;
use RuntimeException;
use Spryker\Zed\DataExchangeExtension\Dependency\Plugin\CacheResettableImportStepInterface;
use Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ImportStepInterface;

/**
 * Records every batch it receives, reports rows whose `sku` starts with `fail-` as errors and throws on rows whose
 * `sku` starts with `throw-`, so tests can assert mapping, counters, error rows, publish events and failure handling.
 *
 * Keeps a lookup cache (`sku` => file row number) the way a real step caches entity lookups, so tests can assert
 * that the engine empties it between batches.
 */
class DemoCsvImportStep implements ImportStepInterface, CacheResettableImportStepInterface
{
    public const string EVENT_NAME = 'Demo.entity.publish';

    public const string FAILING_SKU_PREFIX = 'fail-';

    public const string THROWING_SKU_PREFIX = 'throw-';

    /**
     * @var array<int, array<int, array<string, string>>>
     */
    protected array $receivedBatches = [];

    /**
     * @var array<int, array<string, string>>
     */
    protected array $receivedPropertyNamesInFile = [];

    /**
     * Lookup cache filled while a batch is processed; must not survive the batch.
     *
     * @var array<string, int>
     */
    protected array $lookupCache = [];

    /**
     * Number of batches that had been received when each `resetCache()` call arrived.
     *
     * @var array<int, int>
     */
    protected array $batchCountAtEachReset = [];

    /**
     * Number of cached lookups each `resetCache()` call dropped.
     *
     * @var array<int, int>
     */
    protected array $lookupCacheSizeAtEachReset = [];

    /**
     * @var array<int, bool>
     */
    protected array $instancePoolingStateAtEachBatch = [];

    /**
     * {@inheritDoc}
     *
     * @throws \RuntimeException
     */
    public function executeBatch(array $rows, array $propertyNamesInFile = []): ImportStepResponseTransfer
    {
        $this->receivedBatches[] = $rows;
        $this->receivedPropertyNamesInFile[] = $propertyNamesInFile;
        $this->instancePoolingStateAtEachBatch[] = Propel::isInstancePoolingEnabled();

        $response = (new ImportStepResponseTransfer())->setIsSuccessful(true);

        foreach ($rows as $rowNumber => $row) {
            $this->lookupCache[$row['sku'] ?? ''] = $rowNumber;

            if (str_starts_with($row['sku'] ?? '', static::THROWING_SKU_PREFIX)) {
                throw new RuntimeException(sprintf('Row %d exploded in demo step.', $rowNumber));
            }

            if (str_starts_with($row['sku'] ?? '', static::FAILING_SKU_PREFIX)) {
                $response->setIsSuccessful(false);
                $response->addError((new ImportStepErrorTransfer())->setCsvRowNumber($rowNumber)->setErrorMessage(sprintf('Row %d rejected by demo step.', $rowNumber)));

                continue;
            }

            $response->addPublishEvent((new ImportPublishEventTransfer())->setEventName(static::EVENT_NAME)->setEntityId($rowNumber));
        }

        return $response;
    }

    public function resetCache(): void
    {
        $this->batchCountAtEachReset[] = count($this->receivedBatches);
        $this->lookupCacheSizeAtEachReset[] = count($this->lookupCache);
        $this->lookupCache = [];
    }

    /**
     * @return array<string, int>
     */
    public function getLookupCache(): array
    {
        return $this->lookupCache;
    }

    /**
     * @return array<int, int>
     */
    public function getLookupCacheSizeAtEachReset(): array
    {
        return $this->lookupCacheSizeAtEachReset;
    }

    /**
     * @return array<int, bool>
     */
    public function getInstancePoolingStateAtEachBatch(): array
    {
        return $this->instancePoolingStateAtEachBatch;
    }

    /**
     * @return array<int, int>
     */
    public function getBatchCountAtEachReset(): array
    {
        return $this->batchCountAtEachReset;
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

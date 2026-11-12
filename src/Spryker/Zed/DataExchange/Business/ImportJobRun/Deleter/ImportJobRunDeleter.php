<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Business\ImportJobRun\Deleter;

use ArrayObject;
use Generated\Shared\Transfer\ErrorTransfer;
use Generated\Shared\Transfer\FileSystemDeleteTransfer;
use Generated\Shared\Transfer\ImportJobRunDeleteResponseTransfer;
use Generated\Shared\Transfer\ImportJobRunTransfer;
use Spryker\Service\FileSystem\FileSystemServiceInterface;
use Spryker\Shared\Log\LoggerTrait;
use Spryker\Zed\DataExchange\DataExchangeConfig;
use Spryker\Zed\DataExchange\Persistence\DataExchangeEntityManagerInterface;
use Throwable;

class ImportJobRunDeleter implements ImportJobRunDeleterInterface
{
    use LoggerTrait;

    protected const string ERROR_MESSAGE_RUN_NOT_DELETED = 'Import job run #%d could not be deleted.';

    public function __construct(
        protected DataExchangeEntityManagerInterface $entityManager,
        protected FileSystemServiceInterface $fileSystemService,
        protected DataExchangeConfig $config,
    ) {
    }

    /**
     * @param \ArrayObject<int, \Generated\Shared\Transfer\ImportJobRunTransfer> $importJobRunTransfers
     */
    public function deleteImportJobRuns(
        ArrayObject $importJobRunTransfers,
        ImportJobRunDeleteResponseTransfer $importJobRunDeleteResponseTransfer
    ): ImportJobRunDeleteResponseTransfer {
        $importJobRunIds = [];

        foreach ($importJobRunTransfers as $importJobRunTransfer) {
            if ($this->deleteStoredFile($importJobRunTransfer, $importJobRunDeleteResponseTransfer)) {
                $importJobRunIds[] = $importJobRunTransfer->getIdImportJobRunOrFail();
            }
        }

        if ($importJobRunIds === []) {
            return $importJobRunDeleteResponseTransfer;
        }

        try {
            $deletedImportJobRunCount = $this->entityManager->deleteImportJobRuns($importJobRunIds);
        } catch (Throwable $exception) {
            foreach ($importJobRunIds as $idImportJobRun) {
                $this->addRunNotDeletedError($idImportJobRun, $exception, $importJobRunDeleteResponseTransfer);
            }

            return $importJobRunDeleteResponseTransfer;
        }

        return $importJobRunDeleteResponseTransfer->setDeletedImportJobRunCount(
            $importJobRunDeleteResponseTransfer->getDeletedImportJobRunCountOrFail() + $deletedImportJobRunCount,
        );
    }

    protected function deleteStoredFile(
        ImportJobRunTransfer $importJobRunTransfer,
        ImportJobRunDeleteResponseTransfer $importJobRunDeleteResponseTransfer
    ): bool {
        $importJobRunFileInfoTransfer = $importJobRunTransfer->getFileInfo();

        if ($importJobRunFileInfoTransfer === null || !$importJobRunFileInfoTransfer->getStoredPath()) {
            return true;
        }

        try {
            $this->fileSystemService->delete(
                (new FileSystemDeleteTransfer())
                    ->setFileSystemName($importJobRunFileInfoTransfer->getFileSystemName() ?? $this->config->getImportFileSystemName())
                    ->setPath($importJobRunFileInfoTransfer->getStoredPathOrFail()),
            );
        } catch (Throwable $exception) {
            $this->addRunNotDeletedError($importJobRunTransfer->getIdImportJobRunOrFail(), $exception, $importJobRunDeleteResponseTransfer);

            return false;
        }

        return true;
    }

    protected function addRunNotDeletedError(
        int $idImportJobRun,
        Throwable $exception,
        ImportJobRunDeleteResponseTransfer $importJobRunDeleteResponseTransfer
    ): void {
        $this->getLogger()->error(
            sprintf(static::ERROR_MESSAGE_RUN_NOT_DELETED, $idImportJobRun) . ' ' . $exception->getMessage(),
            ['exception' => $exception],
        );

        $importJobRunDeleteResponseTransfer->addError(
            (new ErrorTransfer())->setMessage(sprintf(static::ERROR_MESSAGE_RUN_NOT_DELETED, $idImportJobRun)),
        );
    }
}

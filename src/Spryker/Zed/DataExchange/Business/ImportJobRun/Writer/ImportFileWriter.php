<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Business\ImportJobRun\Writer;

use DateTime;
use Generated\Shared\Transfer\FileSystemStreamTransfer;
use Generated\Shared\Transfer\ImportJobRunFileInfoTransfer;
use RuntimeException;
use Spryker\Service\FileSystem\FileSystemServiceInterface;
use Spryker\Zed\DataExchange\DataExchangeConfig;

class ImportFileWriter implements ImportFileWriterInterface
{
    protected const string STORED_PATH_PATTERN = '{type}/{fileName}';

    public function __construct(
        protected FileSystemServiceInterface $fileSystemService,
        protected DataExchangeConfig $config,
    ) {
    }

    public function writeFile(ImportJobRunFileInfoTransfer $fileInfoTransfer, string $jobType): ImportJobRunFileInfoTransfer
    {
        $originalFileName = $fileInfoTransfer->getOriginalFileNameOrFail();
        $storedPath = $this->buildStoredPath($jobType, $originalFileName);
        $contentType = $fileInfoTransfer->getContentType() ?? $this->config->getImportDefaultContentType();

        $stream = $this->openUploadedFileStream($fileInfoTransfer->getUploadedFilePathOrFail());

        try {
            $this->fileSystemService->writeStream($this->createFileSystemStreamTransfer($storedPath, $contentType), $stream);
        } finally {
            fclose($stream);
        }

        return $fileInfoTransfer
            ->setFileSystemName($this->config->getImportFileSystemName())
            ->setStoredPath($storedPath)
            ->setUploadedFilePath(null);
    }

    /**
     * The upload is handed to the storage as a stream, so a file of any size passes through with flat memory.
     *
     * @throws \RuntimeException
     *
     * @return resource
     */
    protected function openUploadedFileStream(string $uploadedFilePath)
    {
        $stream = is_readable($uploadedFilePath) ? fopen($uploadedFilePath, 'rb') : false;

        if ($stream === false) {
            throw new RuntimeException(sprintf('Uploaded import file "%s" could not be opened for reading.', $uploadedFilePath));
        }

        return $stream;
    }

    protected function createFileSystemStreamTransfer(string $storedPath, string $contentType): FileSystemStreamTransfer
    {
        return (new FileSystemStreamTransfer())
            ->setPath($storedPath)
            ->setFileSystemName($this->config->getImportFileSystemName())
            ->setConfig(['ContentType' => $contentType]);
    }

    protected function buildStoredPath(string $jobType, string $originalFileName): string
    {
        $pathInfo = pathinfo($originalFileName);
        $timestamp = (new DateTime())->format('Y-m-d_H-i-s-v');
        $storedFileName = sprintf('%s_%s', $pathInfo['filename'], $timestamp);

        if (isset($pathInfo['extension'])) {
            $storedFileName .= sprintf('.%s', $pathInfo['extension']);
        }

        return strtr(static::STORED_PATH_PATTERN, [
            '{type}' => $jobType,
            '{fileName}' => $storedFileName,
        ]);
    }
}

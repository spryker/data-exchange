<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Business\ImportJobRun\Writer;

use DateTime;
use Generated\Shared\Transfer\FileSystemContentTransfer;
use Generated\Shared\Transfer\ImportJobRunFileInfoTransfer;
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
        $content = (string)file_get_contents($fileInfoTransfer->getUploadedFilePathOrFail());

        $contentType = $fileInfoTransfer->getContentType() ?? $this->config->getImportDefaultContentType();

        $fileSystemContentTransfer = $this->createFileSystemContentTransfer($storedPath, $content, $contentType);
        $this->fileSystemService->write($fileSystemContentTransfer);

        return $fileInfoTransfer
            ->setFileSystemName($this->config->getImportFileSystemName())
            ->setStoredPath($storedPath)
            ->setUploadedFilePath(null);
    }

    protected function createFileSystemContentTransfer(string $storedPath, string $content, string $contentType): FileSystemContentTransfer
    {
        return (new FileSystemContentTransfer())
            ->setPath($storedPath)
            ->setFileSystemName($this->config->getImportFileSystemName())
            ->setContent($content)
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

<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Business\Export\Writer;

use DateTime;
use DateTimeZone;
use Generated\Shared\Transfer\FileSystemStreamTransfer;
use Generated\Shared\Transfer\ImportJobRunFileInfoTransfer;
use Spryker\Service\FileSystem\FileSystemServiceInterface;
use Spryker\Zed\DataExchange\Business\Export\Writer\Exception\ExportFileException;
use Spryker\Zed\DataExchange\DataExchangeConfig;

class ExportFileWriter implements ExportFileWriterInterface
{
    protected const string CONTENT_TYPE_CSV = 'text/csv';

    protected const string STORED_PATH_PATTERN = '{type}/export_{timestamp}.csv';

    protected const string ORIGINAL_FILE_NAME_PATTERN = '{type}_export_{timestamp}.csv';

    protected const string TEMP_FILE_PREFIX = 'data_exchange_export_';

    /**
     * @var resource|null
     */
    protected $tempFileHandle;

    protected string $tempFilePath = '';

    public function __construct(
        protected FileSystemServiceInterface $fileSystemService,
        protected DataExchangeConfig $config,
    ) {
    }

    /**
     * @param array<string> $columns
     *
     * @throws \Spryker\Zed\DataExchange\Business\Export\Writer\Exception\ExportFileException
     */
    public function openFile(array $columns): void
    {
        $tempFilePath = tempnam(sys_get_temp_dir(), static::TEMP_FILE_PREFIX);

        if ($tempFilePath === false) {
            throw new ExportFileException(sprintf('Failed to create a temp file for export in %s', sys_get_temp_dir()));
        }

        $this->tempFilePath = $tempFilePath;
        $handle = fopen($this->tempFilePath, 'wb');

        if ($handle === false) {
            throw new ExportFileException(sprintf('Failed to open temp file for export: %s', $this->tempFilePath));
        }

        $this->tempFileHandle = $handle;

        fputcsv($this->tempFileHandle, $columns);
    }

    /**
     * @param array<int, array<string, string>> $rows
     * @param array<string> $columns
     *
     * @throws \Spryker\Zed\DataExchange\Business\Export\Writer\Exception\ExportFileException
     */
    public function writeBatch(array $rows, array $columns): void
    {
        if ($this->tempFileHandle === null) {
            throw new ExportFileException('Cannot write batch: export file has not been opened. Call openFile() first.');
        }

        foreach ($rows as $row) {
            $csvRow = [];

            foreach ($columns as $column) {
                $csvRow[] = $row[$column] ?? '';
            }

            fputcsv($this->tempFileHandle, $csvRow);
        }
    }

    /**
     * @throws \Spryker\Zed\DataExchange\Business\Export\Writer\Exception\ExportFileException
     */
    public function closeAndStore(string $jobType): ImportJobRunFileInfoTransfer
    {
        if ($this->tempFileHandle === null) {
            throw new ExportFileException('Cannot close and store: export file has not been opened. Call openFile() first.');
        }

        fclose($this->tempFileHandle);
        $this->tempFileHandle = null;

        $timestamp = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d_H-i-s-v');
        $storedPath = strtr(static::STORED_PATH_PATTERN, [
            '{type}' => $jobType,
            '{timestamp}' => $timestamp,
        ]);
        $exportFileName = strtr(static::ORIGINAL_FILE_NAME_PATTERN, [
            '{type}' => $jobType,
            '{timestamp}' => $timestamp,
        ]);

        $fileSize = (int)filesize($this->tempFilePath);
        $stream = fopen($this->tempFilePath, 'rb');

        if ($stream === false) {
            throw new ExportFileException(sprintf('Failed to open temp file for reading: %s', $this->tempFilePath));
        }

        $fileSystemStreamTransfer = (new FileSystemStreamTransfer())
            ->setFileSystemName($this->config->getExportFileSystemName())
            ->setPath($storedPath);

        $this->fileSystemService->writeStream($fileSystemStreamTransfer, $stream);

        fclose($stream);
        $this->removeTempFile();

        return (new ImportJobRunFileInfoTransfer())
            ->setFileSystemName($this->config->getExportFileSystemName())
            ->setStoredPath($storedPath)
            ->setOriginalFileName($exportFileName)
            ->setContentType(static::CONTENT_TYPE_CSV)
            ->setSize($fileSize);
    }

    public function abort(): void
    {
        if ($this->tempFileHandle !== null) {
            fclose($this->tempFileHandle);
            $this->tempFileHandle = null;
        }

        $this->removeTempFile();
    }

    protected function removeTempFile(): void
    {
        if ($this->tempFilePath !== '' && file_exists($this->tempFilePath)) {
            unlink($this->tempFilePath);
        }

        $this->tempFilePath = '';
    }
}

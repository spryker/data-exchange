<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange;

use Spryker\Zed\Kernel\AbstractBundleConfig;

class DataExchangeConfig extends AbstractBundleConfig
{
    /**
     * Specification:
     * - Defines the status value assigned to a newly created import job run.
     * - Import job runs in this status are queued for processing by the import consumer.
     */
    protected const string IMPORT_STATUS_PENDING = 'pending';

    /**
     * Specification:
     * - Defines the status value for an import job run that is currently being processed.
     * - Prevents the same job run from being picked up by another consumer.
     */
    protected const string IMPORT_STATUS_PROCESSING = 'processing';

    /**
     * Specification:
     * - Defines the status value for a successfully completed import job run.
     * - Used in the Back Office table to display success counts.
     */
    protected const string IMPORT_STATUS_DONE = 'done';

    /**
     * Specification:
     * - Defines the status value for a failed import job run.
     * - Assigned when all rows fail or an unrecoverable error occurs during import.
     */
    protected const string IMPORT_STATUS_FAILED = 'failed';

    /**
     * Specification:
     * - Defines the flysystem storage name used for storing uploaded import files.
     * - Must match a configured flysystem storage in the project infrastructure.
     */
    protected const string IMPORT_FILE_SYSTEM_NAME = 'data-exchange-imports';

    /**
     * Specification:
     * - Defines the flysystem storage name used for storing generated export files.
     * - Must match a configured flysystem storage in the project infrastructure.
     */
    protected const string EXPORT_FILE_SYSTEM_NAME = 'data-exchange-exports';

    /**
     * Specification:
     * - Defines the number of rows processed in a single batch during import.
     * - Higher values increase memory usage but reduce the number of database transactions.
     * - Lower values reduce memory footprint but increase overall import duration.
     */
    protected const int IMPORT_CSV_BATCH_SIZE = 1000;

    /**
     * Specification:
     * - Defines the number of records fetched per batch during export.
     * - Higher values increase memory usage but reduce the number of database queries.
     * - Lower values reduce memory footprint but increase overall export duration.
     */
    protected const int EXPORT_CSV_BATCH_SIZE = 1000;

    /**
     * Specification:
     * - Defines the maximum allowed file size in megabytes for uploaded import files.
     * - Used to configure the file upload form validation constraint.
     * - Increasing this value may require adjusting PHP and web server upload limits.
     */
    protected const int IMPORT_MAX_FILE_SIZE_MB = 50;

    /**
     * Specification:
     * - Defines the maximum number of import errors displayed inline on the job run detail page.
     * - When the error count exceeds this threshold, errors are shown as a downloadable summary instead.
     * - Increasing this value may impact page rendering performance for large error sets.
     */
    protected const int IMPORT_ERROR_DISPLAY_THRESHOLD = 10;

    /**
     * Specification:
     * - Defines the default MIME content type used when storing import files without an explicit type.
     */
    protected const string IMPORT_DEFAULT_CONTENT_TYPE = 'text/csv';

    /**
     * Specification:
     * - Returns the status value representing a pending import job run.
     * - Used by the repository to find the next job run to process.
     *
     * @api
     */
    public function getImportStatusPending(): string
    {
        return static::IMPORT_STATUS_PENDING;
    }

    /**
     * Specification:
     * - Returns the status value representing an in-progress import job run.
     * - Used to mark a job run as actively being processed.
     *
     * @api
     */
    public function getImportStatusProcessing(): string
    {
        return static::IMPORT_STATUS_PROCESSING;
    }

    /**
     * Specification:
     * - Returns the status value representing a successfully completed import job run.
     * - Used to mark a job run after all rows have been processed without critical failure.
     *
     * @api
     */
    public function getImportStatusDone(): string
    {
        return static::IMPORT_STATUS_DONE;
    }

    /**
     * Specification:
     * - Returns the status value representing a failed import job run.
     * - Used to mark a job run when all rows fail or an unrecoverable error occurs.
     *
     * @api
     */
    public function getImportStatusFailed(): string
    {
        return static::IMPORT_STATUS_FAILED;
    }

    /**
     * Specification:
     * - Returns the flysystem storage name for import file operations.
     * - Used to read and store uploaded import files.
     *
     * @api
     */
    public function getImportFileSystemName(): string
    {
        return static::IMPORT_FILE_SYSTEM_NAME;
    }

    /**
     * Specification:
     * - Returns the flysystem storage name for export file operations.
     * - Used to write generated export files.
     *
     * @api
     */
    public function getExportFileSystemName(): string
    {
        return static::EXPORT_FILE_SYSTEM_NAME;
    }

    /**
     * Specification:
     * - Returns the number of rows processed per batch during import.
     * - Adjusting this value affects memory consumption and import throughput.
     *
     * @api
     */
    public function getImportCsvBatchSize(): int
    {
        return static::IMPORT_CSV_BATCH_SIZE;
    }

    /**
     * Specification:
     * - Returns the number of records fetched per batch during export.
     * - Adjusting this value affects memory consumption and export throughput.
     *
     * @api
     */
    public function getExportCsvBatchSize(): int
    {
        return static::EXPORT_CSV_BATCH_SIZE;
    }

    /**
     * Specification:
     * - Returns the maximum import file size as a PHP ini-compatible string (e.g. "50M").
     * - Used as the max size constraint in the import file upload form.
     *
     * @api
     */
    public function getImportMaxFileSizeString(): string
    {
        return sprintf('%dM', static::IMPORT_MAX_FILE_SIZE_MB);
    }

    /**
     * Specification:
     * - Returns allowed MIME types mapped to their file extensions for import file validation.
     * - Used by the upload form to restrict accepted file types.
     *
     * @api
     *
     * @return array<string, list<string>>
     */
    public function getAllowedMimeTypesWithExtensions(): array
    {
        return [
            'text/csv' => ['csv'],
            'text/plain' => ['csv'],
        ];
    }

    /**
     * Specification:
     * - Returns the maximum number of import errors displayed inline on the job run detail page.
     * - When exceeded, errors are shown as a downloadable summary to avoid page performance degradation.
     *
     * @api
     */
    public function getImportErrorDisplayThreshold(): int
    {
        return static::IMPORT_ERROR_DISPLAY_THRESHOLD;
    }

    /**
     * Specification:
     * - Returns the default MIME content type used when storing import files without an explicit type.
     *
     * @api
     */
    public function getImportDefaultContentType(): string
    {
        return static::IMPORT_DEFAULT_CONTENT_TYPE;
    }
}

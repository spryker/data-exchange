<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Business;

use Generated\Shared\Transfer\ImportJobCollectionRequestTransfer;
use Generated\Shared\Transfer\ImportJobCollectionResponseTransfer;
use Generated\Shared\Transfer\ImportJobCollectionTransfer;
use Generated\Shared\Transfer\ImportJobCriteriaTransfer;
use Generated\Shared\Transfer\ImportJobExportResultTransfer;
use Generated\Shared\Transfer\ImportJobRunCollectionRequestTransfer;
use Generated\Shared\Transfer\ImportJobRunCollectionResponseTransfer;
use Generated\Shared\Transfer\ImportJobRunCollectionTransfer;
use Generated\Shared\Transfer\ImportJobRunCriteriaTransfer;
use Generated\Shared\Transfer\ImportJobRunErrorCollectionTransfer;
use Generated\Shared\Transfer\ImportJobRunErrorCriteriaTransfer;

interface DataExchangeFacadeInterface
{
    /**
     * Specification:
     * - Retrieves a collection of import jobs filtered by the provided criteria.
     * - Returns `ImportJobCollectionTransfer` with matching import jobs and pagination.
     *
     * @api
     */
    public function getImportJobCollection(ImportJobCriteriaTransfer $criteriaTransfer): ImportJobCollectionTransfer;

    /**
     * Specification:
     * - Creates import job records from the provided collection request.
     * - Generates a unique reference from the job name if not provided.
     * - Populates the job definition from the matching schema plugin `\Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ImportSchemaPluginInterface` if not provided.
     * - Reports an error and skips the job when no schema plugin is registered for its type.
     * - Persists each import job to the database.
     * - Returns `ImportJobCollectionResponseTransfer` with created jobs or errors.
     *
     * @api
     */
    public function createImportJobCollection(ImportJobCollectionRequestTransfer $collectionRequestTransfer): ImportJobCollectionResponseTransfer;

    /**
     * Specification:
     * - Creates import job run records from the provided collection request.
     * - Stores the uploaded file on the import file system under `{jobType}/{fileName}` when `ImportJobRunFileInfo.uploadedFilePath` is set.
     * - Sets pending status and initializes counters for each run.
     * - Persists each import job run to the database.
     * - Returns `ImportJobRunCollectionResponseTransfer` with created runs or errors.
     *
     * @api
     */
    public function createImportJobRunCollection(ImportJobRunCollectionRequestTransfer $collectionRequestTransfer): ImportJobRunCollectionResponseTransfer;

    /**
     * Specification:
     * - Retrieves a collection of import job runs filtered by the provided criteria.
     * - Returns `ImportJobRunCollectionTransfer` with matching runs and pagination.
     *
     * @api
     */
    public function getImportJobRunCollection(ImportJobRunCriteriaTransfer $criteriaTransfer): ImportJobRunCollectionTransfer;

    /**
     * Specification:
     * - Retrieves a collection of import job run errors filtered by the provided criteria.
     * - Orders errors by CSV row number ascending.
     * - Returns `ImportJobRunErrorCollectionTransfer` with matching errors and pagination.
     *
     * @api
     */
    public function getImportJobRunErrorCollection(ImportJobRunErrorCriteriaTransfer $criteriaTransfer): ImportJobRunErrorCollectionTransfer;

    /**
     * Specification:
     * - Resolves the import job by the provided criteria.
     * - Resolves the schema plugin `\Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ImportSchemaPluginInterface` registered for the job type.
     * - Expands pattern-based schema definitions into concrete column names using the placeholder values of the schema plugin's placeholder provider.
     * - When `ImportJobCriteriaTransfer.isWithData` is true, runs the export steps of the schema plugin and writes the rows to a file on the export file system.
     * - When `ImportJobCriteriaTransfer.isWithData` is false or not set, returns the columns only (template mode).
     * - Returns an unsuccessful result with an error message when no import job matches the provided criteria.
     * - Returns an unsuccessful result with an error message when no schema plugin is registered for the job type.
     *
     * @api
     */
    public function exportData(ImportJobCriteriaTransfer $criteriaTransfer): ImportJobExportResultTransfer;

    /**
     * Specification:
     * - Finds the oldest pending import job run.
     * - Does nothing if no pending run exists.
     * - Marks the run as processing and records the start timestamp.
     * - Executes pre-processor plugins `\Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ImportPreProcessorPluginInterface` before processing.
     * - Reads the file in batches and runs each batch through the import steps of the schema plugin registered for the job type.
     * - Records per-row errors for failed rows.
     * - Triggers the collected publish events once after all rows were processed.
     * - Executes post-processor plugins `\Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ImportPostProcessorPluginInterface` after processing.
     * - Marks the run as done or failed with final counters and finish timestamp; an unhandled exception marks the run as failed and records the message as an error.
     *
     * @api
     */
    public function processNextPendingRun(): void;
}

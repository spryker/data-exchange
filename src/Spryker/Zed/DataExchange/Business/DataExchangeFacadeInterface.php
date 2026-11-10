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
use Generated\Shared\Transfer\ImportJobRunErrorCollectionResponseTransfer;
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
     * - Persists each import job run with receiving status, whatever status the request carries, and initializes its counters.
     * - Then stores the uploaded file on the import file system under `{jobType}/{fileName}` when `ImportJobRunFileInfo.uploadedFilePath` is set.
     * - Then sets pending status and stores the file info, only while the run is still receiving.
     * - Marks the run failed with one error saying that the file could not be stored, and reports the same error; the cause is logged, not stored; a failed run is never claimed.
     * - Logs an error and still reports the error when the run cannot be marked failed; the run then stays receiving.
     * - Returns `ImportJobRunCollectionResponseTransfer` with created runs or errors.
     *
     * @api
     */
    public function createImportJobRunCollection(ImportJobRunCollectionRequestTransfer $collectionRequestTransfer): ImportJobRunCollectionResponseTransfer;

    /**
     * Specification:
     * - Retrieves a collection of import job runs filtered by the provided criteria.
     * - Expands each run with `ImportJobRunTransfer.progressPercentage`: processed bytes divided by the file size, between 0 and 100.
     * - Sets `ImportJobRunTransfer.progressPercentage` to `null` when the processed bytes or the file size are unknown.
     * - Returns `ImportJobRunCollectionTransfer` with matching runs and pagination.
     *
     * @api
     */
    public function getImportJobRunCollection(ImportJobRunCriteriaTransfer $criteriaTransfer): ImportJobRunCollectionTransfer;

    /**
     * Specification:
     * - Retrieves a collection of import job run errors filtered by the provided criteria.
     * - Orders errors by CSV row number ascending.
     * - Sums `ImportJobRun.numberOfOmittedErrorRows` of the selected runs into `ImportJobRunErrorCollectionResponse.numberOfOmittedErrorRows`.
     * - Returns `ImportJobRunErrorCollectionResponseTransfer` with matching errors, the omitted error row count and pagination.
     *
     * @api
     */
    public function getImportJobRunErrorCollection(ImportJobRunErrorCriteriaTransfer $criteriaTransfer): ImportJobRunErrorCollectionResponseTransfer;

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
     * - Marks up to `DataExchangeConfig::getImportJobRunExhaustedBatchSize()` processing runs that were abandoned by their worker after `DataExchangeConfig::getImportJobRunMaxAttempts()` attempts as failed, oldest first, with one error explaining that the attempts were exhausted; such a run is never claimed again and executes no post-processors.
     * - Logs an error and continues with the next run when one of these runs cannot be marked failed; the remaining runs are marked failed by the next calls.
     * - Marking these runs failed does not count towards the return value.
     * - Finds the oldest claimable import job run: a pending run, or a processing run whose lease is not set or has expired (abandoned by its worker).
     * - Skips runs that were already claimed `DataExchangeConfig::getImportJobRunMaxAttempts()` times.
     * - Does nothing if no claimable run exists, or if another worker claims the same run first.
     * - Claims the run with a conditional update: marks it as processing, sets a unique `claimedBy` token, a lease of `DataExchangeConfig::getImportJobRunLeaseDurationInMinutes()`, increments the attempt count, records the start timestamp and resets the progress counters of a previous attempt.
     * - Executes pre-processor plugins `\Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ImportPreProcessorPluginInterface` before processing.
     * - Reads the file in batches and runs each batch through the import steps of the schema plugin registered for the job type.
     * - Skips blank lines; they count neither as processed nor as failed lines.
     * - Records per-row errors for failed rows.
     * - After every batch, writes the progress counters and renews the lease, only while the run is still processing and `claimedBy` still matches the token of this worker.
     * - Stops before the next batch, without an error, when that write affects no row because another worker took over the claim or the run was marked failed; the run is then left as it is and post-processors are not executed.
     * - Triggers the publish events collected from the processed batches once after reading stops.
     * - Marks the run with final counters and finish timestamp as failed when no row was processed, with one error explaining that the file has no rows to import, or when every row failed, as imported with errors when some rows failed, and as done otherwise; an unhandled exception marks the run as failed and records the message as an error.
     * - Writes the final status only while the run is still processing and `claimedBy` still matches the token of this worker; otherwise leaves the run as it is, records no error and does not execute post-processors.
     * - Executes post-processor plugins `\Spryker\Zed\DataExchangeExtension\Dependency\Plugin\ImportPostProcessorPluginInterface` after a completed import; a post-processor exception is recorded as an error and keeps the run status.
     * - Returns `true` when a run was claimed, `false` when no claimable run was left.
     * - Never claims a receiving run.
     *
     * @api
     */
    public function processNextPendingRun(): bool;
}

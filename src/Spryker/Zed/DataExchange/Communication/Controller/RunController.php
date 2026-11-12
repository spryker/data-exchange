<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Communication\Controller;

use Generated\Shared\Transfer\ImportJobRunCollectionRequestTransfer;
use Generated\Shared\Transfer\ImportJobRunErrorConditionsTransfer;
use Generated\Shared\Transfer\ImportJobRunErrorCriteriaTransfer;
use Generated\Shared\Transfer\ImportJobRunFileInfoTransfer;
use Generated\Shared\Transfer\ImportJobRunTransfer;
use Generated\Shared\Transfer\PaginationTransfer;
use Spryker\Zed\DataExchange\Communication\Form\ImportJobRunForm;
use Spryker\Zed\Kernel\Communication\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @method \Spryker\Zed\DataExchange\Communication\DataExchangeCommunicationFactory getFactory()
 * @method \Spryker\Zed\DataExchange\Business\DataExchangeFacade getFacade()
 */
class RunController extends AbstractController
{
    protected const string ROUTE_RUN_INDEX = '/data-exchange/run/index';

    /**
     * @uses \Spryker\Zed\DataExchange\Communication\Controller\JobController::indexAction()
     */
    protected const string ROUTE_JOB_INDEX = '/data-exchange/job/index';

    protected const string ROUTE_RUN_DOWNLOAD_ERRORS = '/data-exchange/run/download-errors';

    protected const string ROUTE_TEMPLATE_DOWNLOAD = '/data-exchange/template/download';

    protected const string PARAM_ID_IMPORT_JOB = 'idImportJob';

    protected const string PARAM_IMPORT_JOB_REFERENCE = 'importJobReference';

    protected const string PARAM_ID_IMPORT_JOB_RUN = 'idImportJobRun';

    protected const string ERROR_MESSAGE_IMPORT_JOB_RUN_DOES_NOT_EXIST = 'Import run with ID %id% does not exist.';

    protected const string ERROR_MESSAGE_IMPORT_JOB_DOES_NOT_EXIST = 'Import job with ID %id% does not exist.';

    protected const string ERROR_MESSAGE_IMPORT_JOB_REFERENCE_DOES_NOT_EXIST = 'Import job with reference %reference% does not exist.';

    protected const string ERROR_MESSAGE_PARAMETER_ID = '%id%';

    protected const string ERROR_MESSAGE_PARAMETER_REFERENCE = '%reference%';

    protected const string MESSAGE_OMITTED_ERRORS_CSV = 'The error row limit was reached: %d further errors were counted but not stored.';

    /**
     * @return \Symfony\Component\HttpFoundation\Response|array<string, mixed>
     */
    public function indexAction(Request $request): array|Response
    {
        $idImportJob = $this->castId($request->query->get(static::PARAM_ID_IMPORT_JOB));

        $importJobTransfer = $this->getFactory()->createImportJobReader()->findImportJobById($idImportJob);

        if ($importJobTransfer === null) {
            $this->addErrorMessage(static::ERROR_MESSAGE_IMPORT_JOB_DOES_NOT_EXIST, [
                static::ERROR_MESSAGE_PARAMETER_ID => $idImportJob,
            ]);

            return $this->redirectResponse(static::ROUTE_JOB_INDEX);
        }

        return $this->viewResponse([
            'table' => $this->getFactory()->createImportJobRunTable($idImportJob)->render(),
            'idImportJob' => $idImportJob,
            'importJobReference' => $importJobTransfer->getReference() ?? '',
        ]);
    }

    public function tableAction(Request $request): JsonResponse
    {
        $idImportJob = $this->castId($request->query->get(static::PARAM_ID_IMPORT_JOB));
        $table = $this->getFactory()->createImportJobRunTable($idImportJob);

        return $this->jsonResponse($table->fetchData());
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response|array<string, mixed>
     */
    public function createAction(Request $request): array|Response
    {
        $importJobReference = (string)$request->query->get(static::PARAM_IMPORT_JOB_REFERENCE);

        $importJobTransfer = $this->getFactory()->createImportJobReader()->findImportJobByReference($importJobReference);

        if ($importJobTransfer === null) {
            $this->addErrorMessage(static::ERROR_MESSAGE_IMPORT_JOB_REFERENCE_DOES_NOT_EXIST, [
                static::ERROR_MESSAGE_PARAMETER_REFERENCE => $importJobReference,
            ]);

            return $this->redirectResponse(static::ROUTE_JOB_INDEX);
        }

        $form = $this->getFactory()->createImportJobRunForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $uploadedFile = $form->get(ImportJobRunForm::FIELD_FILE)->getData();

            $fileInfo = $this->buildFileInfoFromUpload($uploadedFile);

            $importJobRunTransfer = (new ImportJobRunTransfer())
                ->setImportJobReference($importJobReference)
                ->setFileInfo($fileInfo);

            $collectionRequest = (new ImportJobRunCollectionRequestTransfer())
                ->addImportJobRun($importJobRunTransfer)
                ->setIsTransactional(true);

            $collectionResponse = $this->getFacade()->createImportJobRunCollection($collectionRequest);

            if ($collectionResponse->getErrors()->count() > 0) {
                foreach ($collectionResponse->getErrors() as $errorTransfer) {
                    $this->addErrorMessage($errorTransfer->getMessageOrFail());
                }

                return $this->viewResponse([
                    'form' => $form->createView(),
                    'idImportJob' => $importJobTransfer->getIdImportJob(),
                    'templateDownloadUrl' => sprintf('%s?%s=%s', static::ROUTE_TEMPLATE_DOWNLOAD, static::PARAM_IMPORT_JOB_REFERENCE, urlencode($importJobReference)),
                ]);
            }

            $createdRun = $collectionResponse->getImportJobRuns()->getIterator()->current();

            $this->addSuccessMessage('Import run created successfully. Processing is queued.');

            return $this->redirectResponse(sprintf('%s?%s=%d', static::ROUTE_RUN_INDEX, static::PARAM_ID_IMPORT_JOB, $createdRun->getFkImportJobOrFail()));
        }

        return $this->viewResponse([
            'form' => $form->createView(),
            'idImportJob' => $importJobTransfer->getIdImportJob(),
            'templateDownloadUrl' => sprintf('%s?%s=%s', static::ROUTE_TEMPLATE_DOWNLOAD, static::PARAM_IMPORT_JOB_REFERENCE, urlencode($importJobReference)),
        ]);
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Response|array<string, mixed>
     */
    public function detailAction(Request $request): array|Response
    {
        $idImportJobRun = $this->castId($request->query->get(static::PARAM_ID_IMPORT_JOB_RUN));

        $importJobRunTransfer = $this->getFactory()->createImportJobReader()->findImportJobRun($idImportJobRun);

        if ($importJobRunTransfer === null) {
            $this->addErrorMessage(static::ERROR_MESSAGE_IMPORT_JOB_RUN_DOES_NOT_EXIST, [
                static::ERROR_MESSAGE_PARAMETER_ID => $idImportJobRun,
            ]);

            return $this->redirectResponse(static::ROUTE_JOB_INDEX);
        }

        $errorConditions = (new ImportJobRunErrorConditionsTransfer())->addIdImportJobRun($idImportJobRun);
        $errorThreshold = $this->getFactory()->getConfig()->getImportErrorDisplayThreshold();
        $errorCriteria = (new ImportJobRunErrorCriteriaTransfer())
            ->setImportJobRunErrorConditions($errorConditions)
            ->setPagination((new PaginationTransfer())->setLimit($errorThreshold));
        $importJobRunErrorCollectionResponseTransfer = $this->getFacade()->getImportJobRunErrorCollection($errorCriteria);

        $errorCount = $importJobRunErrorCollectionResponseTransfer->getPaginationOrFail()->getNbResultsOrFail();
        $showErrorsInline = $errorCount <= $errorThreshold;

        return $this->viewResponse([
            'jobRun' => $importJobRunTransfer,
            'errorCount' => $errorCount,
            'omittedErrorCount' => $importJobRunErrorCollectionResponseTransfer->getNumberOfOmittedErrorRowsOrFail(),
            'showErrorsInline' => $showErrorsInline,
            'inlineErrors' => $showErrorsInline ? $importJobRunErrorCollectionResponseTransfer->getImportJobRunErrors() : null,
            'errorsDownloadUrl' => sprintf('%s?%s=%d', static::ROUTE_RUN_DOWNLOAD_ERRORS, static::PARAM_ID_IMPORT_JOB_RUN, $idImportJobRun),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function errorsAction(Request $request): array
    {
        $idImportJobRun = $this->castId($request->query->get(static::PARAM_ID_IMPORT_JOB_RUN));

        $errorConditions = (new ImportJobRunErrorConditionsTransfer())->addIdImportJobRun($idImportJobRun);
        $errorCriteria = (new ImportJobRunErrorCriteriaTransfer())
            ->setImportJobRunErrorConditions($errorConditions)
            ->setPagination((new PaginationTransfer())->setLimit($this->getFactory()->getConfig()->getImportErrorDisplayThreshold()));

        $importJobRunErrorCollectionResponseTransfer = $this->getFacade()->getImportJobRunErrorCollection($errorCriteria);

        return $this->viewResponse([
            'errors' => $importJobRunErrorCollectionResponseTransfer->getImportJobRunErrors(),
            'omittedErrorCount' => $importJobRunErrorCollectionResponseTransfer->getNumberOfOmittedErrorRowsOrFail(),
            'idImportJobRun' => $idImportJobRun,
        ]);
    }

    public function downloadErrorsAction(Request $request): Response
    {
        $idImportJobRun = $this->castId($request->query->get(static::PARAM_ID_IMPORT_JOB_RUN));

        $errorConditions = (new ImportJobRunErrorConditionsTransfer())->addIdImportJobRun($idImportJobRun);
        $errorCriteria = (new ImportJobRunErrorCriteriaTransfer())->setImportJobRunErrorConditions($errorConditions);
        $batchSize = $this->getFactory()->getConfig()->getImportErrorExportBatchSize();
        $dataExchangeFacade = $this->getFacade();

        $response = new StreamedResponse(static function () use ($dataExchangeFacade, $errorCriteria, $batchSize): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['Row Number', 'Error Message']);

            $offset = 0;

            do {
                $errorCriteria->setPagination((new PaginationTransfer())->setLimit($batchSize)->setOffset($offset));
                $importJobRunErrorCollectionResponseTransfer = $dataExchangeFacade->getImportJobRunErrorCollection($errorCriteria);
                $importJobRunErrorTransfers = $importJobRunErrorCollectionResponseTransfer->getImportJobRunErrors();

                foreach ($importJobRunErrorTransfers as $importJobRunErrorTransfer) {
                    fputcsv($handle, [
                        $importJobRunErrorTransfer->getCsvRowNumber() ?? '',
                        $importJobRunErrorTransfer->getErrorMessage(),
                    ]);
                }

                $offset += $batchSize;
            } while ($importJobRunErrorTransfers->count() === $batchSize);

            $omittedErrorCount = $importJobRunErrorCollectionResponseTransfer->getNumberOfOmittedErrorRowsOrFail();

            if ($omittedErrorCount > 0) {
                fputcsv($handle, [
                    '',
                    sprintf(static::MESSAGE_OMITTED_ERRORS_CSV, $omittedErrorCount),
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="import_errors_run_%d.csv"', $idImportJobRun));

        return $response;
    }

    protected function buildFileInfoFromUpload(UploadedFile $uploadedFile): ImportJobRunFileInfoTransfer
    {
        return (new ImportJobRunFileInfoTransfer())
            ->setUploadedFilePath($uploadedFile->getPathname())
            ->setOriginalFileName($uploadedFile->getClientOriginalName())
            ->setContentType($uploadedFile->getMimeType() ?: 'text/csv')
            ->setSize((int)$uploadedFile->getSize());
    }
}

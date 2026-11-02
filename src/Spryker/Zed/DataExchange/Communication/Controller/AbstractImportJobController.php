<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace Spryker\Zed\DataExchange\Communication\Controller;

use Generated\Shared\Transfer\ImportJobTransfer;
use Spryker\Zed\Kernel\Communication\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @method \Spryker\Zed\DataExchange\Communication\DataExchangeCommunicationFactory getFactory()
 * @method \Spryker\Zed\DataExchange\Business\DataExchangeFacade getFacade()
 */
abstract class AbstractImportJobController extends AbstractController
{
    protected const string PARAM_IMPORT_JOB_REFERENCE = 'importJobReference';

    protected const string ERROR_MESSAGE_PARAMETER_REQUIRED = 'Parameter "%s" is required.';

    protected const string ERROR_MESSAGE_IMPORT_JOB_NOT_FOUND = 'Import job with reference "%s" not found.';

    /**
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    protected function resolveImportJobByRequest(Request $request): ImportJobTransfer
    {
        $importJobReference = $request->query->getString(static::PARAM_IMPORT_JOB_REFERENCE);

        if ($importJobReference === '') {
            throw new NotFoundHttpException(
                sprintf(static::ERROR_MESSAGE_PARAMETER_REQUIRED, static::PARAM_IMPORT_JOB_REFERENCE),
            );
        }

        $importJobTransfer = $this->getFactory()->createImportJobReader()->findImportJobByReference($importJobReference);

        if ($importJobTransfer === null) {
            throw new NotFoundHttpException(
                sprintf(static::ERROR_MESSAGE_IMPORT_JOB_NOT_FOUND, $importJobReference),
            );
        }

        return $importJobTransfer;
    }
}

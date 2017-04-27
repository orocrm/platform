<?php

namespace Oro\Bundle\ActionBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use Oro\Bundle\ActionBundle\Exception\ForbiddenOperationException;
use Oro\Bundle\ActionBundle\Exception\OperationNotFoundException;
use Oro\Bundle\ActionBundle\Helper\ContextHelper;
use Oro\Bundle\ActionBundle\Model\ActionData;
use Oro\Bundle\ActionBundle\Model\Operation;
use Oro\Bundle\ActionBundle\Model\OperationRegistry;

use Oro\Bundle\SecurityBundle\Annotation\AclAncestor;

class AjaxController extends Controller
{
    /**
     * @Route("/operation/execute/{operationName}", name="oro_action_operation_execute")
     * @AclAncestor("oro_action")
     *
     * @param Request $request
     * @param string $operationName
     * @return Response
     */
    public function executeAction(Request $request, $operationName)
    {
        $data = $this->getContextHelper()->getActionData();

        $errors = new ArrayCollection();
        $code = Response::HTTP_OK;
        $message = '';
        $pageReload = true;

        try {
            $operation = $this->getOperationRegistry()->findByName($operationName);

            if (!$operation instanceof Operation) {
                throw new OperationNotFoundException($operationName);
            }
            if (!$operation->isAvailable($data, $errors)) {
                throw new ForbiddenOperationException();
            }

            $operation->execute($data, $errors);
            $pageReload = $operation->getDefinition()->isPageReload();
        } catch (OperationNotFoundException $e) {
            $code = Response::HTTP_NOT_FOUND;
        } catch (ForbiddenOperationException $e) {
            $code = Response::HTTP_FORBIDDEN;
        } catch (\Exception $e) {
            $code = Response::HTTP_INTERNAL_SERVER_ERROR;
        }

        if (isset($e)) {
            $message = $e->getMessage();
        }

        return $this->handleResponse($request, $data, $code, $message, $errors, $pageReload);
    }

    /**
     * @param Request $request
     * @param ActionData $data
     * @param int $code
     * @param string $message
     * @param Collection $errorMessages
     * @param bool $pageReload
     * @return Response
     */
    protected function handleResponse(
        Request $request,
        ActionData $data,
        $code,
        $message,
        Collection $errorMessages,
        bool $pageReload
    ) {
        $response = [
            'success' => $code === Response::HTTP_OK,
            'message' => $message,
            'messages' => $this->prepareMessages($errorMessages),
            'pageReload' => $pageReload
        ];

        // handle redirect for failure response on non ajax requests
        if (!$request->isXmlHttpRequest() && !$response['success'] && null !== ($routeName = $request->get('route'))) {
            $this->get('session')->getFlashBag()->add('error', $response['message']);

            return $this->redirect($this->generateUrl($routeName));
        }

        if ($data->getRefreshGrid() || !$response['success'] || !$response['pageReload']) {
            $response['refreshGrid'] = $data->getRefreshGrid();
            $response['flashMessages'] = $this->get('session')->getFlashBag()->all();
        } elseif ($data->getRedirectUrl()) {
            if ($request->isXmlHttpRequest()) {
                $response['redirectUrl'] = $data->getRedirectUrl();
            } else {
                return $this->redirect($data->getRedirectUrl());
            }
        }

        return new JsonResponse($response, $code);
    }

    /**
     * @param Collection $messages
     * @return array
     */
    protected function prepareMessages(Collection $messages)
    {
        $translator = $this->get('translator');
        $result = [];

        foreach ($messages as $message) {
            $result[] = $translator->trans($message['message'], $message['parameters']);
        }

        return $result;
    }

    /**
     * @return OperationRegistry
     */
    protected function getOperationRegistry()
    {
        return $this->get('oro_action.operation_registry');
    }

    /**
     * @return ContextHelper
     */
    protected function getContextHelper()
    {
        return $this->get('oro_action.helper.context');
    }
}

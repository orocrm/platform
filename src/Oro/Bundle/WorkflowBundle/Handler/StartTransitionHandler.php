<?php

namespace Oro\Bundle\WorkflowBundle\Handler;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

use Oro\Bundle\FeatureToggleBundle\Checker\FeatureChecker;
use Oro\Bundle\WorkflowBundle\Configuration\FeatureConfigurationExtension;
use Oro\Bundle\WorkflowBundle\Exception\ForbiddenTransitionException;
use Oro\Bundle\WorkflowBundle\Exception\InvalidTransitionException;
use Oro\Bundle\WorkflowBundle\Exception\UnknownAttributeException;
use Oro\Bundle\WorkflowBundle\Exception\WorkflowNotFoundException;
use Oro\Bundle\WorkflowBundle\Handler\Helper\TransitionHelper;
use Oro\Bundle\WorkflowBundle\Model\Transition;
use Oro\Bundle\WorkflowBundle\Model\Workflow;
use Oro\Bundle\WorkflowBundle\Model\WorkflowData;
use Oro\Bundle\WorkflowBundle\Model\WorkflowManager;
use Oro\Bundle\WorkflowBundle\Serializer\WorkflowAwareSerializer;

class StartTransitionHandler
{
    /** @var WorkflowManager */
    protected $workflowManager;

    /** @var WorkflowAwareSerializer */
    protected $serializer;

    /** @var TransitionHelper */
    protected $transitionHelper;

    /** @var FeatureChecker */
    protected $featureChecker;

    /**
     * @param WorkflowManager $workflowManager
     * @param WorkflowAwareSerializer $serializer
     * @param TransitionHelper $transitionHelper
     * @param FeatureChecker $featureChecker
     */
    public function __construct(
        WorkflowManager $workflowManager,
        WorkflowAwareSerializer $serializer,
        TransitionHelper $transitionHelper,
        FeatureChecker $featureChecker
    ) {
        $this->workflowManager = $workflowManager;
        $this->serializer = $serializer;
        $this->transitionHelper = $transitionHelper;
        $this->featureChecker = $featureChecker;
    }

    /**
     * @param Workflow $workflow
     * @param Transition $transition
     * @param string $data
     * @param object $entity
     *
     * @return Response|null
     */
    public function handle(Workflow $workflow, Transition $transition, $data, $entity)
    {
        if ($transition->getPageTemplate() || $transition->getDialogTemplate()) {
            return;
        }

        $responseCode = null;
        $responseMessage = null;
        $workflowItem = null;
        try {
            $dataArray = [];
            $workflowName = $workflow->getName();

            if (!$this->featureChecker
                ->isResourceEnabled($workflowName, FeatureConfigurationExtension::WORKFLOWS_NODE_NAME)
            ) {
                throw new ForbiddenTransitionException();
            }

            if ($data) {
                $this->serializer->setWorkflowName($workflowName);
                /* @var $data WorkflowData */
                $data = $this->serializer->deserialize(
                    $data,
                    'Oro\Bundle\WorkflowBundle\Model\WorkflowData',
                    'json'
                );
                $dataArray = $data->getValues();
            }

            $workflowItem = $this->workflowManager->startWorkflow($workflowName, $entity, $transition, $dataArray);
        } catch (HttpException $e) {
            $responseCode = $e->getStatusCode();
            $responseMessage = $e->getMessage();
        } catch (WorkflowNotFoundException $e) {
            $responseCode = 404;
            $responseMessage = $e->getMessage();
        } catch (UnknownAttributeException $e) {
            $responseCode = 400;
            $responseMessage = $e->getMessage();
        } catch (InvalidTransitionException $e) {
            $responseCode = 400;
            $responseMessage = $e->getMessage();
        } catch (ForbiddenTransitionException $e) {
            $responseCode = 403;
            $responseMessage = $e->getMessage();
        } catch (\Exception $e) {
            $responseCode = 500;
            $responseMessage = $e->getMessage();
        }

        return $this->transitionHelper->createCompleteResponse($workflowItem, $responseCode, $responseMessage);
    }
}

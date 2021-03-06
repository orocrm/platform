<?php

namespace Oro\Bundle\ActionBundle\Tests\Functional\Action;

use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Oro\Bundle\TestFrameworkBundle\Tests\Functional\DataFixtures\LoadItems;

class UpdateTest extends WebTestCase
{
    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        $this->initClient([], $this->generateBasicAuthHeader());

        $this->loadFixtures([
            'Oro\Bundle\TestFrameworkBundle\Tests\Functional\DataFixtures\LoadItems',
        ]);
    }

    public function testExecute()
    {
        $item = $this->getReference(LoadItems::ITEM1);
        $operationName = 'UPDATE';
        $entityId = $item->getId();
        $entityClass = 'Oro\Bundle\TestFrameworkBundle\Entity\Item';
        $this->client->request(
            'POST',
            $this->getUrl(
                'oro_action_operation_execute',
                [
                    'operationName' => $operationName,
                    'entityClass' => $entityClass,
                    'entityId' => $entityId,
                ]
            ),
            $this->getOperationExecuteParams($operationName, $entityId, $entityClass),
            [],
            ['HTTP_X-Requested-With' => 'XMLHttpRequest']
        );

        $result = $this->client->getResponse();
        $this->assertJsonResponseStatusCodeEquals($result, 200);

        $response = json_decode($result->getContent(), true);

        $this->assertEquals(
            [
                'success' => true,
                'message' => '',
                'messages' => [],
                'redirectUrl' => $this->getUrl('oro_test_item_update', ['id' => $item->getId()]),
                'pageReload' => true
            ],
            $response
        );
    }

    /**
     * @param $operationName
     * @param $entityId
     * @param $entityClass
     *
     * @return array
     */
    protected function getOperationExecuteParams($operationName, $entityId, $entityClass)
    {
        $actionContext = [
            'entityId'    => $entityId,
            'entityClass' => $entityClass
        ];
        $container = static::getContainer();
        $operation = $container->get('oro_action.operation_registry')->findByName($operationName);
        $actionData = $container->get('oro_action.helper.context')->getActionData($actionContext);

        $tokenData = $container
            ->get('oro_action.operation.execution.form_provider')
            ->createTokenData($operation, $actionData);
        $container->get('session')->save();

        return $tokenData;
    }
}

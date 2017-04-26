<?php
namespace Oro\Bundle\DataAuditBundle\Tests\Functional\Async;

use Doctrine\ORM\EntityManagerInterface;
use Oro\Bundle\DataAuditBundle\Async\AuditChangedEntitiesRelationsProcessor;
use Oro\Bundle\DataAuditBundle\Entity\Audit;
use Oro\Bundle\MessageQueueBundle\Test\Functional\MessageQueueExtension;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Oro\Component\MessageQueue\Consumption\MessageProcessorInterface;
use Oro\Component\MessageQueue\Transport\Null\NullMessage;
use Oro\Component\MessageQueue\Transport\Null\NullSession;

/**
 * @dbIsolationPerTest
 */
class AuditChangedEntitiesRelationsProcessorTest extends WebTestCase
{
    use MessageQueueExtension;

    protected function setUp()
    {
        $this->initClient();
    }

    public function testCouldBeGetFromContainerAsService()
    {
        /** @var AuditChangedEntitiesRelationsProcessor $processor */
        $processor = $this->getContainer()->get('oro_dataaudit.async.audit_changed_entities_relations');
        
        $this->assertInstanceOf(AuditChangedEntitiesRelationsProcessor::class, $processor);
    }

    public function testShouldDoNothingIfAnythingChangedInMessage()
    {
        $message = $this->createMessage([
            'timestamp' => time(),
            'transaction_id' => 'aTransactionId',
            'entities_inserted' => [],
            'entities_updated' => [],
            'entities_deleted' => [],
            'collections_updated' => [],
        ]);

        /** @var AuditChangedEntitiesRelationsProcessor $processor */
        $processor = $this->getContainer()->get('oro_dataaudit.async.audit_changed_entities_relations');

        $processor->process($message, new NullSession());

        $this->assertStoredAuditCount(0);
    }

    public function testShouldReturnAckOnProcess()
    {
        $message = $this->createMessage([
            'timestamp' => time(),
            'transaction_id' => 'aTransactionId',
            'entities_inserted' => [],
            'entities_updated' => [],
            'entities_deleted' => [],
            'collections_updated' => [],
        ]);

        /** @var AuditChangedEntitiesRelationsProcessor $processor */
        $processor = $this->getContainer()->get('oro_dataaudit.async.audit_changed_entities_relations');

        $this->assertEquals(MessageProcessorInterface::ACK, $processor->process($message, new NullSession()));
    }

    private function assertStoredAuditCount($expected)
    {
        $this->assertCount($expected, $this->getEntityManager()->getRepository(Audit::class)->findAll());
    }

    /**
     * @param array $body
     * @return NullMessage
     */
    private function createMessage(array $body)
    {
        $message = new NullMessage();
        $message->setBody(json_encode($body));
        
        return $message;
    }

    /**
     * @return EntityManagerInterface
     */
    private function getEntityManager()
    {
        return $this->getContainer()->get('doctrine.orm.entity_manager');
    }
}

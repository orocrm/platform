<?php

namespace Oro\Bundle\DataAuditBundle\Tests\Functional\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Proxy\Proxy;
use Oro\Bundle\DataAuditBundle\Async\Topics;
use Oro\Bundle\DataAuditBundle\Tests\Functional\Environment\Entity\TestAuditDataChild;
use Oro\Bundle\DataAuditBundle\Tests\Functional\Environment\Entity\TestAuditDataOwner;
use Oro\Bundle\MessageQueueBundle\Test\Functional\MessageQueueExtension;
use Oro\Component\MessageQueue\Client\Message;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

trait SendChangedEntitiesToMessageQueueExtensionTrait
{
    use MessageQueueExtension;

    /**
     * @param int $expected
     * @param Message $message
     */
    public function assertEntitiesInsertedInMessageCount(int $expected, Message $message): void
    {
        $this->assertTrue(isset($message->getBody()['entities_inserted']));
        $this->assertCount($expected, $message->getBody()['entities_inserted']);
    }

    /**
     * @param int $expected
     * @param Message $message
     */
    public function assertEntitiesUpdatedInMessageCount(int $expected, Message $message): void
    {
        $this->assertTrue(isset($message->getBody()['entities_updated']));
        $this->assertCount($expected, $message->getBody()['entities_updated']);
    }

    /**
     * @param int $expected
     * @param Message $message
     */
    public function assertEntitiesDeletedInMessageCount(int $expected, Message $message): void
    {
        $this->assertTrue(isset($message->getBody()['entities_deleted']));
        $this->assertCount($expected, $message->getBody()['entities_deleted']);
    }

    /**
     * @param int $expected
     * @param Message $message
     */
    public function assertCollectionsUpdatedInMessageCount(int $expected, Message $message): void
    {
        $this->assertTrue(isset($message->getBody()['collections_updated']));
        $this->assertCount($expected, $message->getBody()['collections_updated']);
    }

    /**
     * @return TestAuditDataOwner
     */
    protected function createOwner(): TestAuditDataOwner
    {
        $owner = new TestAuditDataOwner();

        $this->getEntityManager()->persist($owner);
        $this->getEntityManager()->flush();

        self::getMessageCollector()->clear();

        return $owner;
    }

    /**
     * @return TestAuditDataOwner|Proxy
     */
    protected function createOwnerProxy()
    {
        $owner = $this->createOwner();

        $this->getEntityManager()->clear();

        $ownerProxy = $this->getEntityManager()->getReference(TestAuditDataOwner::class, $owner->getId());

        //guard
        $this->assertInstanceOf(Proxy::class, $ownerProxy);

        return $ownerProxy;
    }

    /**
     * @return TestAuditDataChild
     */
    protected function createChild(): TestAuditDataChild
    {
        $child = new TestAuditDataChild();

        $this->getEntityManager()->persist($child);
        $this->getEntityManager()->flush();

        self::getMessageCollector()->clear();

        return $child;
    }

    /**
     * @return Message
     */
    protected function getFirstEntitiesChangedMessage(): Message
    {
        $messages = self::getSentMessages();

        //guard
        $this->assertGreaterThanOrEqual(1, count($messages));
        $this->assertEquals(Topics::ENTITIES_CHANGED, $messages[0]['topic']);

        return $messages[0]['message'];
    }

    /**
     * @return EntityManagerInterface
     */
    protected function getEntityManager()
    {
        return $this->getClientInstance()->getContainer()->get('doctrine.orm.entity_manager');
    }

    /**
     * @return KernelBrowser
     */
    abstract protected static function getClientInstance();
}

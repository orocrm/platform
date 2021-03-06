<?php

namespace Oro\Bundle\DataAuditBundle\Tests\Functional\Async;

use Doctrine\ORM\EntityManagerInterface;
use Oro\Bundle\DataAuditBundle\Async\AuditChangedEntitiesProcessor;
use Oro\Bundle\DataAuditBundle\Async\Topics;
use Oro\Bundle\DataAuditBundle\Entity\Audit;
use Oro\Bundle\DataAuditBundle\Tests\Functional\Environment\Entity\TestAuditDataChild;
use Oro\Bundle\DataAuditBundle\Tests\Functional\Environment\Entity\TestAuditDataOwner;
use Oro\Bundle\MessageQueueBundle\Test\Functional\MessageQueueExtension;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;
use Oro\Component\MessageQueue\Client\Message;
use Oro\Component\MessageQueue\Client\MessagePriority;
use Oro\Component\MessageQueue\Consumption\MessageProcessorInterface;
use Oro\Component\MessageQueue\Transport\ConnectionInterface;
use Oro\Component\MessageQueue\Transport\Message as TransportMessage;

/**
 * @dbIsolationPerTest
 * @SuppressWarnings(PHPMD.TooManyMethods)
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class AuditChangedEntitiesProcessorTest extends WebTestCase
{
    use MessageQueueExtension;

    protected function setUp(): void
    {
        $this->initClient();
    }

    public function testCouldBeGetFromContainerAsService()
    {
        /** @var AuditChangedEntitiesProcessor $processor */
        $processor = $this->getContainer()->get('oro_dataaudit.async.audit_changed_entities');

        $this->assertInstanceOf(AuditChangedEntitiesProcessor::class, $processor);
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

        /** @var AuditChangedEntitiesProcessor $processor */
        $processor = $this->getContainer()->get('oro_dataaudit.async.audit_changed_entities');
        /** @var ConnectionInterface $connection */
        $connection = $this->getContainer()->get('oro_message_queue.transport.connection');

        $processor->process($message, $connection->createSession());

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

        /** @var AuditChangedEntitiesProcessor $processor */
        $processor = $this->getContainer()->get('oro_dataaudit.async.audit_changed_entities');
        /** @var ConnectionInterface $connection */
        $connection = $this->getContainer()->get('oro_message_queue.transport.connection');
        $session = $connection->createSession();

        $this->assertEquals(MessageProcessorInterface::ACK, $processor->process($message, $session));
    }

    public function testShouldSendSameMessageToProcessEntitiesRelationsAndInverseRelations()
    {
        /**
         * Message content is similar to case when BusinessUnit was edited and a new User was added into BusinessUnit.
         */
        $message = $this->createMessage([
            'timestamp' => time(),
            'transaction_id' => 'aTransactionId',
            'entities_inserted' => [],
            'entities_updated' => [
                '000000007ec8f22c00000000536823d4' => [
                    'entity_class' => TestAuditDataChild::class,
                    'entity_id' => 1,
                    'change_set' => [],
                ]
            ],
            'entities_deleted' => [],
            'collections_updated' => [
                '000000007ec8f22c00000000536823d4' => [
                    'entity_class' => TestAuditDataChild::class,
                    'entity_id' => 1,
                    'change_set' => [
                        'owner' => [
                            null,
                            [
                                'inserted' => [
                                    [
                                        'entity_class' => TestAuditDataOwner::class,
                                        'entity_id' => 1,
                                        'change_set' => [],
                                    ]
                                ],
                                'deleted' => [],
                                'changed' => [],
                            ]
                        ]
                    ],
                ]
            ],
        ]);
        $expectedBody = json_decode($message->getBody(), true);

        /** @var AuditChangedEntitiesProcessor $processor */
        $processor = $this->getContainer()->get('oro_dataaudit.async.audit_changed_entities');
        /** @var ConnectionInterface $connection */
        $connection = $this->getContainer()->get('oro_message_queue.transport.connection');

        $processor->process($message, $connection->createSession());

        $this->assertMessageSent(
            Topics::ENTITIES_RELATIONS_CHANGED,
            $this->createExpectedMessage($expectedBody, MessagePriority::VERY_LOW)
        );
        $this->assertMessageSent(
            Topics::ENTITIES_INVERSED_RELATIONS_CHANGED,
            $this->createExpectedMessage($expectedBody, MessagePriority::VERY_LOW)
        );
    }

    public function testShouldSendSameMessageToProcessEntitiesInverseRelations()
    {
        $message = $this->createMessage([
            'timestamp' => time(),
            'transaction_id' => 'aTransactionId',
            'entities_inserted' => [],
            'entities_updated' => [],
            'entities_deleted' => [],
            'collections_updated' => [],
        ]);
        $expectedBody = json_decode($message->getBody(), true);

        /** @var AuditChangedEntitiesProcessor $processor */
        $processor = $this->getContainer()->get('oro_dataaudit.async.audit_changed_entities');
        /** @var ConnectionInterface $connection */
        $connection = $this->getContainer()->get('oro_message_queue.transport.connection');

        $processor->process($message, $connection->createSession());

        $this->assertMessageSent(
            Topics::ENTITIES_INVERSED_RELATIONS_CHANGED,
            $this->createExpectedMessage($expectedBody, MessagePriority::VERY_LOW)
        );
    }

    public function testShouldCreateAuditForInsertedEntity()
    {
        $expectedLoggedAt = new \DateTime('2012-02-01 03:02:01+0000');

        $message = $this->createMessage([
            'timestamp' => $expectedLoggedAt->getTimestamp(),
            'transaction_id' => 'aTransactionId',
            'entities_inserted' => [
                '000000007ec8f22c00000000536823d4' => [
                    'entity_class' => TestAuditDataOwner::class,
                    'entity_id' => 123,
                    'change_set' => [
                        'stringProperty' => [null, 'aNewValue'],
                    ],
                ]
            ],
            'entities_updated' => [],
            'entities_deleted' => [],
            'collections_updated' => [],
        ]);

        /** @var AuditChangedEntitiesProcessor $processor */
        $processor = $this->getContainer()->get('oro_dataaudit.async.audit_changed_entities');
        /** @var ConnectionInterface $connection */
        $connection = $this->getContainer()->get('oro_message_queue.transport.connection');

        $processor->process($message, $connection->createSession());

        $this->assertStoredAuditCount(1);
    }

    public function testShouldCreateAuditForUpdatedEntity()
    {
        $expectedLoggedAt = new \DateTime('2012-02-01 03:02:01+0000');

        $message = $this->createMessage([
            'timestamp' => $expectedLoggedAt->getTimestamp(),
            'transaction_id' => 'aTransactionId',
            'entities_inserted' => [],
            'entities_updated' => [
                '000000007ec8f22c00000000536823d4' => [
                    'entity_class' => TestAuditDataOwner::class,
                    'entity_id' => 123,
                    'change_set' => [
                        'stringProperty' => [null, 'aNewValue'],
                    ],
                ]
            ],
            'entities_deleted' => [],
            'collections_updated' => [],
        ]);

        /** @var AuditChangedEntitiesProcessor $processor */
        $processor = $this->getContainer()->get('oro_dataaudit.async.audit_changed_entities');
        /** @var ConnectionInterface $connection */
        $connection = $this->getContainer()->get('oro_message_queue.transport.connection');

        $processor->process($message, $connection->createSession());

        $this->assertStoredAuditCount(1);
    }

    public function testShouldCreateAuditForDeletedEntity()
    {
        $expectedLoggedAt = new \DateTime('2012-02-01 03:02:01+0000');

        $message = $this->createMessage([
            'timestamp' => $expectedLoggedAt->getTimestamp(),
            'transaction_id' => 'aTransactionId',
            'entities_inserted' => [],
            'entities_updated' => [],
            'entities_deleted' => [
                '000000007ec8f22c00000000536823d4' => [
                    'entity_class' => TestAuditDataOwner::class,
                    'entity_id' => 123,
                    'change_set' => [
                        'stringProperty' => ['123', null]
                    ],
                ]
            ],
            'collections_updated' => [],
        ]);

        /** @var AuditChangedEntitiesProcessor $processor */
        $processor = $this->getContainer()->get('oro_dataaudit.async.audit_changed_entities');
        /** @var ConnectionInterface $connection */
        $connection = $this->getContainer()->get('oro_message_queue.transport.connection');

        $processor->process($message, $connection->createSession());

        $this->assertStoredAuditCount(1);
    }

    public function testShouldTryGetEntityNameFromPreviousAuditEntryForDeletedEntity()
    {
        $audit = new Audit();
        $audit->setObjectName('theExpectedEntityName');
        $audit->setObjectClass(TestAuditDataOwner::class);
        $audit->setObjectId(123);
        $audit->setTransactionId('previousTransactionId');
        $audit->setVersion(1);
        $this->getEntityManager()->persist($audit);
        $this->getEntityManager()->flush();

        $expectedLoggedAt = new \DateTime('2012-02-01 03:02:01+0000');

        $message = $this->createMessage([
            'timestamp' => $expectedLoggedAt->getTimestamp(),
            'transaction_id' => 'aTransactionId',
            'entities_inserted' => [],
            'entities_updated' => [],
            'entities_deleted' => [
                '000000007ec8f22c00000000536823d4' => [
                    'entity_class' => TestAuditDataOwner::class,
                    'entity_id' => 123,
                    'change_set' => [
                        'stringProperty' => ['123', null]
                    ],
                ]
            ],
            'collections_updated' => [],
        ]);

        /** @var AuditChangedEntitiesProcessor $processor */
        $processor = $this->getContainer()->get('oro_dataaudit.async.audit_changed_entities');
        /** @var ConnectionInterface $connection */
        $connection = $this->getContainer()->get('oro_message_queue.transport.connection');

        $processor->process($message, $connection->createSession());

        //guard
        $this->assertStoredAuditCount(2);

        $audit = $this->findLastStoredAudit();

        //guard
        $this->assertEquals(2, $audit->getVersion());

        $this->assertEquals('theExpectedEntityName', $audit->getObjectName());
    }

    public function testShouldProcessAllChangedEntities()
    {
        $message = $this->createMessage([
            'timestamp' => time(),
            'transaction_id' => 'aTransactionId',
            'entities_inserted' => [
                '000000007ec8f22c00000000536823d4' => [
                    'entity_class' => TestAuditDataOwner::class,
                    'entity_id' => 123,
                    'change_set' => [
                        'stringProperty' => [null, 'aNewValue'],
                    ],
                ]
            ],
            'entities_updated' => [
                '000000007ec8f22c00000000136823d4' => [
                    'entity_class' => TestAuditDataOwner::class,
                    'entity_id' => 234,
                    'change_set' => [
                        'stringProperty' => [null, 'aNewValue'],
                    ],
                ]
            ],
            'entities_deleted' => [
                '000000007ec8f22c00000000236823d4' => [
                    'entity_class' => TestAuditDataOwner::class,
                    'entity_id' => 345,
                    'change_set' => [
                        'stringProperty' => ['123', null]
                    ],
                ]
            ],
            'collections_updated' => [],
        ]);

        /** @var AuditChangedEntitiesProcessor $processor */
        $processor = $this->getContainer()->get('oro_dataaudit.async.audit_changed_entities');
        /** @var ConnectionInterface $connection */
        $connection = $this->getContainer()->get('oro_message_queue.transport.connection');

        $processor->process($message, $connection->createSession());

        $this->assertStoredAuditCount(3);
    }

    public function testShouldIncrementVersionWhenEntityChangedAgain()
    {
        /** @var AuditChangedEntitiesProcessor $processor */
        $processor = $this->getContainer()->get('oro_dataaudit.async.audit_changed_entities');
        /** @var ConnectionInterface $connection */
        $connection = $this->getContainer()->get('oro_message_queue.transport.connection');
        $session = $connection->createSession();

        $processor->process($this->createMessage([
            'timestamp' => time(),
            'transaction_id' => 'aTransactionId',
            'entities_updated' => [
                '000000007ec8f22c00000000536823d4' => [
                    'entity_class' => TestAuditDataOwner::class,
                    'entity_id' => 123,
                    'change_set' => [
                        'stringProperty' => [null, 'aNewValue'],
                    ],
                ]
            ],
            'entities_inserted' => [],
            'entities_deleted' => [],
            'collections_updated' => [],
        ]), $session);

        $this->assertStoredAuditCount(1);
        $audit = $this->findLastStoredAudit();
        $this->assertEquals(1, $audit->getVersion());

        $processor->process($this->createMessage([
            'timestamp' => time(),
            'transaction_id' => 'anotherTransactionId',
            'entities_updated' => [
                '000000007ec8f22c00000000536823d4' => [
                    'entity_class' => TestAuditDataOwner::class,
                    'entity_id' => 123,
                    'change_set' => [
                        'stringProperty' => [null, 'aNewValue'],
                    ],
                ]
            ],
            'entities_inserted' => [],
            'entities_deleted' => [],
            'collections_updated' => [],
        ]), $session);

        $this->assertStoredAuditCount(2);
        $audit = $this->findLastStoredAudit();
        $this->assertEquals(2, $audit->getVersion());
    }

    public function testShouldBeTolerantToMessageDuplication()
    {
        $message = $this->createMessage([
            'timestamp' => time(),
            'transaction_id' => 'aTransactionId',
            'entities_updated' => [
                '000000007ec8f22c00000000536823d4' => [
                    'entity_class' => TestAuditDataOwner::class,
                    'entity_id' => 123,
                    'change_set' => [
                        'stringProperty' => [null, 'aNewValue'],
                    ],
                ]
            ],
            'entities_inserted' => [],
            'entities_deleted' => [],
            'collections_updated' => [],
        ]);

        /** @var AuditChangedEntitiesProcessor $processor */
        $processor = $this->getContainer()->get('oro_dataaudit.async.audit_changed_entities');
        /** @var ConnectionInterface $connection */
        $connection = $this->getContainer()->get('oro_message_queue.transport.connection');
        $session = $connection->createSession();

        $processor->process($message, $session);
        $processor->process($message, $session);
        $processor->process($message, $session);

        $this->assertStoredAuditCount(1);
    }

    public function testShouldSkipAuditForInsertedEntityWithoutEntityClass()
    {
        $expectedLoggedAt = new \DateTime('2012-02-01 03:02:01+0000');

        $message = $this->createMessage([
            'timestamp' => $expectedLoggedAt->getTimestamp(),
            'transaction_id' => 'aTransactionId',
            'entities_inserted' => [
                '000000007ec8f22c00000000536823d4' => [
                    'entity_id' => 123,
                    'change_set' => [
                        'stringProperty' => [null, 'aNewValue']
                    ]
                ],
                '000000007ec8f22c00000000136823d4' => [
                    'entity_class' => null,
                    'entity_id' => 123,
                    'change_set' => [
                        'stringProperty' => [null, 'aNewValue']
                    ]
                ],
                '000000007ec8f22c00000000236823d4' => [
                    'entity_class' => '',
                    'entity_id' => 123,
                    'change_set' => [
                        'stringProperty' => [null, 'aNewValue']
                    ]
                ]
            ],
            'entities_updated' => [],
            'entities_deleted' => [],
            'collections_updated' => []
        ]);

        /** @var AuditChangedEntitiesProcessor $processor */
        $processor = $this->getContainer()->get('oro_dataaudit.async.audit_changed_entities');
        /** @var ConnectionInterface $connection */
        $connection = $this->getContainer()->get('oro_message_queue.transport.connection');

        $processor->process($message, $connection->createSession());

        $this->assertStoredAuditCount(0);
    }

    public function testShouldSkipAuditForInsertedEntityWithoutEntityId()
    {
        $expectedLoggedAt = new \DateTime('2012-02-01 03:02:01+0000');

        $message = $this->createMessage([
            'timestamp' => $expectedLoggedAt->getTimestamp(),
            'transaction_id' => 'aTransactionId',
            'entities_inserted' => [
                '000000007ec8f22c00000000536823d4' => [
                    'entity_class' => TestAuditDataOwner::class,
                    'change_set' => [
                        'stringProperty' => [null, 'aNewValue']
                    ]
                ],
                '000000007ec8f22c00000000136823d4' => [
                    'entity_class' => TestAuditDataOwner::class,
                    'entity_id' => null,
                    'change_set' => [
                        'stringProperty' => [null, 'aNewValue']
                    ]
                ]
            ],
            'entities_updated' => [],
            'entities_deleted' => [],
            'collections_updated' => []
        ]);

        /** @var AuditChangedEntitiesProcessor $processor */
        $processor = $this->getContainer()->get('oro_dataaudit.async.audit_changed_entities');
        /** @var ConnectionInterface $connection */
        $connection = $this->getContainer()->get('oro_message_queue.transport.connection');

        $processor->process($message, $connection->createSession());

        $this->assertStoredAuditCount(0);
    }

    public function testShouldSkipAuditForUpdatedEntityWithoutEntityClass()
    {
        $expectedLoggedAt = new \DateTime('2012-02-01 03:02:01+0000');

        $message = $this->createMessage([
            'timestamp' => $expectedLoggedAt->getTimestamp(),
            'transaction_id' => 'aTransactionId',
            'entities_inserted' => [],
            'entities_updated' => [
                '000000007ec8f22c00000000536823d4' => [
                    'entity_id' => 123,
                    'change_set' => [
                        'stringProperty' => [null, 'aNewValue']
                    ]
                ],
                '000000007ec8f22c00000000136823d4' => [
                    'entity_class' => null,
                    'entity_id' => 123,
                    'change_set' => [
                        'stringProperty' => [null, 'aNewValue']
                    ]
                ],
                '000000007ec8f22c00000000236823d4' => [
                    'entity_class' => '',
                    'entity_id' => 123,
                    'change_set' => [
                        'stringProperty' => [null, 'aNewValue']
                    ]
                ]
            ],
            'entities_deleted' => [],
            'collections_updated' => []
        ]);

        /** @var AuditChangedEntitiesProcessor $processor */
        $processor = $this->getContainer()->get('oro_dataaudit.async.audit_changed_entities');
        /** @var ConnectionInterface $connection */
        $connection = $this->getContainer()->get('oro_message_queue.transport.connection');

        $processor->process($message, $connection->createSession());

        $this->assertStoredAuditCount(0);
    }

    public function testShouldSkipAuditForUpdatedEntityWithoutEntityId()
    {
        $expectedLoggedAt = new \DateTime('2012-02-01 03:02:01+0000');

        $message = $this->createMessage([
            'timestamp' => $expectedLoggedAt->getTimestamp(),
            'transaction_id' => 'aTransactionId',
            'entities_inserted' => [],
            'entities_updated' => [
                '000000007ec8f22c00000000536823d4' => [
                    'entity_class' => TestAuditDataOwner::class,
                    'change_set' => [
                        'stringProperty' => [null, 'aNewValue']
                    ]
                ],
                '000000007ec8f22c00000000136823d4' => [
                    'entity_class' => TestAuditDataOwner::class,
                    'entity_id' => null,
                    'change_set' => [
                        'stringProperty' => [null, 'aNewValue']
                    ]
                ]
            ],
            'entities_deleted' => [],
            'collections_updated' => []
        ]);

        /** @var AuditChangedEntitiesProcessor $processor */
        $processor = $this->getContainer()->get('oro_dataaudit.async.audit_changed_entities');
        /** @var ConnectionInterface $connection */
        $connection = $this->getContainer()->get('oro_message_queue.transport.connection');

        $processor->process($message, $connection->createSession());

        $this->assertStoredAuditCount(0);
    }

    public function testShouldSkipAuditForDeletedEntityWithoutEntityClass()
    {
        $expectedLoggedAt = new \DateTime('2012-02-01 03:02:01+0000');

        $message = $this->createMessage([
            'timestamp' => $expectedLoggedAt->getTimestamp(),
            'transaction_id' => 'aTransactionId',
            'entities_inserted' => [],
            'entities_updated' => [],
            'entities_deleted' => [
                '000000007ec8f22c00000000536823d4' => [
                    'entity_id' => 123,
                    'change_set' => []
                ],
                '000000007ec8f22c00000000136823d4' => [
                    'entity_class' => null,
                    'entity_id' => 123,
                    'change_set' => []
                ],
                '000000007ec8f22c00000000236823d4' => [
                    'entity_class' => '',
                    'entity_id' => 123,
                    'change_set' => []
                ]
            ],
            'collections_updated' => []
        ]);

        /** @var AuditChangedEntitiesProcessor $processor */
        $processor = $this->getContainer()->get('oro_dataaudit.async.audit_changed_entities');
        /** @var ConnectionInterface $connection */
        $connection = $this->getContainer()->get('oro_message_queue.transport.connection');

        $processor->process($message, $connection->createSession());

        $this->assertStoredAuditCount(0);
    }

    public function testShouldSkipAuditForDeletedEntityWithoutEntityId()
    {
        $expectedLoggedAt = new \DateTime('2012-02-01 03:02:01+0000');

        $message = $this->createMessage([
            'timestamp' => $expectedLoggedAt->getTimestamp(),
            'transaction_id' => 'aTransactionId',
            'entities_inserted' => [],
            'entities_updated' => [],
            'entities_deleted' => [
                '000000007ec8f22c00000000536823d4' => [
                    'entity_class' => TestAuditDataOwner::class,
                    'change_set' => []
                ],
                '000000007ec8f22c00000000136823d4' => [
                    'entity_class' => TestAuditDataOwner::class,
                    'entity_id' => null,
                    'change_set' => []
                ]
            ],
            'collections_updated' => []
        ]);

        /** @var AuditChangedEntitiesProcessor $processor */
        $processor = $this->getContainer()->get('oro_dataaudit.async.audit_changed_entities');
        /** @var ConnectionInterface $connection */
        $connection = $this->getContainer()->get('oro_message_queue.transport.connection');

        $processor->process($message, $connection->createSession());

        $this->assertStoredAuditCount(0);
    }

    /**
     * @return array
     */
    public function associationAuditRecordWithoutEntityClassOrIdDataProvider()
    {
        return [
            [['entity_id' => 10]],
            [['entity_class' => '', 'entity_id' => 10]],
            [['entity_class' => null, 'entity_id' => 10]],
            [['entity_class' => TestAuditDataChild::class]],
            [['entity_class' => TestAuditDataChild::class, 'entity_id' => null]]
        ];
    }

    /**
     * @dataProvider associationAuditRecordWithoutEntityClassOrIdDataProvider
     */
    public function testShouldSkipAuditForUpdatedAssociationWithoutEntityClassOrIdInOldChangeSet($record)
    {
        $expectedLoggedAt = new \DateTime('2012-02-01 03:02:01+0000');

        $message = $this->createMessage([
            'timestamp' => $expectedLoggedAt->getTimestamp(),
            'transaction_id' => 'aTransactionId',
            'entities_inserted' => [],
            'entities_updated' => [
                '000000007ec8f22c00000000536823d4' => [
                    'entity_class' => TestAuditDataOwner::class,
                    'entity_id' => 123,
                    'change_set' => [
                        'child' => [
                            $record,
                            ['entity_class' => TestAuditDataChild::class, 'entity_id' => 20]
                        ]
                    ]
                ]
            ],
            'entities_deleted' => [],
            'collections_updated' => []
        ]);

        /** @var AuditChangedEntitiesProcessor $processor */
        $processor = $this->getContainer()->get('oro_dataaudit.async.audit_changed_entities');
        /** @var ConnectionInterface $connection */
        $connection = $this->getContainer()->get('oro_message_queue.transport.connection');

        $processor->process($message, $connection->createSession());

        $this->assertStoredAuditCount(1);
        $audit = $this->findLastStoredAudit();
        self::assertNull($audit->getField('child')->getOldValue());
        self::assertEquals('Added: TestAuditDataChild::20', $audit->getField('child')->getNewValue());
    }

    /**
     * @dataProvider associationAuditRecordWithoutEntityClassOrIdDataProvider
     */
    public function testShouldSkipAuditForUpdatedAssociationWithoutEntityClassOrIdInNewChangeSet($record)
    {
        $expectedLoggedAt = new \DateTime('2012-02-01 03:02:01+0000');

        $message = $this->createMessage([
            'timestamp' => $expectedLoggedAt->getTimestamp(),
            'transaction_id' => 'aTransactionId',
            'entities_inserted' => [],
            'entities_updated' => [
                '000000007ec8f22c00000000536823d4' => [
                    'entity_class' => TestAuditDataOwner::class,
                    'entity_id' => 123,
                    'change_set' => [
                        'child' => [
                            ['entity_class' => TestAuditDataChild::class, 'entity_id' => 20],
                            $record
                        ]
                    ]
                ]
            ],
            'entities_deleted' => [],
            'collections_updated' => []
        ]);

        /** @var AuditChangedEntitiesProcessor $processor */
        $processor = $this->getContainer()->get('oro_dataaudit.async.audit_changed_entities');
        /** @var ConnectionInterface $connection */
        $connection = $this->getContainer()->get('oro_message_queue.transport.connection');

        $processor->process($message, $connection->createSession());

        $this->assertStoredAuditCount(1);
        $audit = $this->findLastStoredAudit();
        self::assertEquals('Removed: TestAuditDataChild::20', $audit->getField('child')->getOldValue());
        self::assertNull($audit->getField('child')->getNewValue());
    }

    /**
     * @dataProvider associationAuditRecordWithoutEntityClassOrIdDataProvider
     */
    public function testShouldSkipAuditForUpdatedAssociationWithoutEntityClassOrIdInInsertedChangeSet($record)
    {
        $expectedLoggedAt = new \DateTime('2012-02-01 03:02:01+0000');

        $message = $this->createMessage([
            'timestamp' => $expectedLoggedAt->getTimestamp(),
            'transaction_id' => 'aTransactionId',
            'entities_inserted' => [],
            'entities_updated' => [
                '000000007ec8f22c00000000536823d4' => [
                    'entity_class' => TestAuditDataOwner::class,
                    'entity_id' => 123,
                    'change_set' => [
                        'childrenManyToMany' => [
                            [],
                            ['inserted' => [$record], 'deleted' => [], 'changed' => []]
                        ]
                    ]
                ]
            ],
            'entities_deleted' => [],
            'collections_updated' => []
        ]);

        /** @var AuditChangedEntitiesProcessor $processor */
        $processor = $this->getContainer()->get('oro_dataaudit.async.audit_changed_entities');
        /** @var ConnectionInterface $connection */
        $connection = $this->getContainer()->get('oro_message_queue.transport.connection');

        $processor->process($message, $connection->createSession());

        $this->assertStoredAuditCount(1);
        $audit = $this->findLastStoredAudit();
        self::assertNull($audit->getField('childrenManyToMany')->getOldValue());
        self::assertEquals(
            ['added' => [], 'removed' => [], 'changed' => []],
            $audit->getField('childrenManyToMany')->getCollectionDiffs()
        );
    }

    /**
     * @dataProvider associationAuditRecordWithoutEntityClassOrIdDataProvider
     */
    public function testShouldSkipAuditForUpdatedAssociationWithoutEntityClassOrIdInDeletedChangeSet($record)
    {
        $expectedLoggedAt = new \DateTime('2012-02-01 03:02:01+0000');

        $message = $this->createMessage([
            'timestamp' => $expectedLoggedAt->getTimestamp(),
            'transaction_id' => 'aTransactionId',
            'entities_inserted' => [],
            'entities_updated' => [
                '000000007ec8f22c00000000536823d4' => [
                    'entity_class' => TestAuditDataOwner::class,
                    'entity_id' => 123,
                    'change_set' => [
                        'childrenManyToMany' => [
                            [],
                            ['inserted' => [], 'deleted' => [$record], 'changed' => []]
                        ]
                    ]
                ]
            ],
            'entities_deleted' => [],
            'collections_updated' => []
        ]);

        /** @var AuditChangedEntitiesProcessor $processor */
        $processor = $this->getContainer()->get('oro_dataaudit.async.audit_changed_entities');
        /** @var ConnectionInterface $connection */
        $connection = $this->getContainer()->get('oro_message_queue.transport.connection');

        $processor->process($message, $connection->createSession());

        $this->assertStoredAuditCount(1);
        $audit = $this->findLastStoredAudit();
        self::assertNull($audit->getField('childrenManyToMany')->getOldValue());
        self::assertEquals(
            ['added' => [], 'removed' => [], 'changed' => []],
            $audit->getField('childrenManyToMany')->getCollectionDiffs()
        );
    }

    /**
     * @dataProvider associationAuditRecordWithoutEntityClassOrIdDataProvider
     */
    public function testShouldSkipAuditForUpdatedAssociationWithoutEntityClassOrIdInChangedChangeSet($record)
    {
        $expectedLoggedAt = new \DateTime('2012-02-01 03:02:01+0000');

        $message = $this->createMessage([
            'timestamp' => $expectedLoggedAt->getTimestamp(),
            'transaction_id' => 'aTransactionId',
            'entities_inserted' => [],
            'entities_updated' => [
                '000000007ec8f22c00000000536823d4' => [
                    'entity_class' => TestAuditDataOwner::class,
                    'entity_id' => 123,
                    'change_set' => [
                        'childrenManyToMany' => [
                            [],
                            ['inserted' => [], 'deleted' => [], 'changed' => [$record]]
                        ]
                    ]
                ]
            ],
            'entities_deleted' => [],
            'collections_updated' => []
        ]);

        /** @var AuditChangedEntitiesProcessor $processor */
        $processor = $this->getContainer()->get('oro_dataaudit.async.audit_changed_entities');
        /** @var ConnectionInterface $connection */
        $connection = $this->getContainer()->get('oro_message_queue.transport.connection');

        $processor->process($message, $connection->createSession());

        $this->assertStoredAuditCount(1);
        $audit = $this->findLastStoredAudit();
        self::assertNull($audit->getField('childrenManyToMany')->getOldValue());
        self::assertEquals(
            ['added' => [], 'removed' => [], 'changed' => []],
            $audit->getField('childrenManyToMany')->getCollectionDiffs()
        );
    }

    private function assertStoredAuditCount($expected)
    {
        $this->assertCount($expected, $this->getEntityManager()->getRepository(Audit::class)->findAll());
    }

    /**
     * @return Audit
     */
    private function findLastStoredAudit()
    {
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('log')
            ->from(Audit::class, 'log')
            ->orderBy('log.id', 'DESC')
            ->setMaxResults(1)
        ;

        return $qb->getQuery()->getSingleResult();
    }

    /**
     * @param array $body
     * @return TransportMessage
     */
    private function createMessage(array $body)
    {
        $message = new TransportMessage();
        $message->setBody(json_encode($body));

        return $message;
    }

    /**
     * @param mixed  $body
     * @param string $priority
     *
     * @return Message
     */
    protected function createExpectedMessage($body, $priority)
    {
        $message = new Message();
        $message->setBody($body);
        $message->setPriority($priority);

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

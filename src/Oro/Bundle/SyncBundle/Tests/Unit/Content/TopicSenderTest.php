<?php

namespace Oro\Bundle\SyncBundle\Tests\Unit\Content;

use Psr\Log\LoggerInterface;

use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

use Oro\Bundle\SyncBundle\Content\TagGeneratorChain;
use Oro\Bundle\SyncBundle\Wamp\TopicPublisher;
use Oro\Bundle\SyncBundle\Content\TopicSender;
use Oro\Bundle\EntityConfigBundle\DependencyInjection\Utils\ServiceLink;

class TopicSenderTest extends \PHPUnit_Framework_TestCase
{
    const TEST_USERNAME = 'usernameTeST';
    const TEST_TAG1     = 'TestTag1';
    const TEST_TAG2     = 'TestTag2';

    /** @var TopicPublisher|\PHPUnit_Framework_MockObject_MockObject */
    protected $publisher;

    /** @var TagGeneratorChain */
    protected $generator;

    /** @var TokenStorageInterface|\PHPUnit_Framework_MockObject_MockObject */
    protected $tokenStorage;

    /** @var TopicSender */
    protected $sender;

    /** @var LoggerInterface|\PHPUnit_Framework_MockObject_MockObject */
    protected $logger;

    protected function setUp()
    {
        $this->publisher = $this->createMock(TopicPublisher::class);
        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->generator = new TagGeneratorChain();
        $container = new Container();
        $container->set('generator', $this->generator);

        $this->sender = new TopicSender(
            $this->publisher,
            new ServiceLink($container, 'generator'),
            $this->tokenStorage,
            $this->logger
        );
    }

    protected function tearDown()
    {
        unset($this->publisher, $this->generator, $this->tokenStorage, $this->sender);
    }

    public function testGetGenerator()
    {
        $this->assertSame($this->generator, $this->sender->getGenerator(), 'Should return generator chain object');
    }

    public function testSend()
    {
        $user = $this->getMockForAbstractClass('Symfony\Component\Security\Core\User\UserInterface');
        $user->expects($this->any())->method('getUserName')->will($this->returnValue(self::TEST_USERNAME));

        $token = $this->createMock('Symfony\Component\Security\Core\Authentication\Token\TokenInterface');
        $token->expects($this->any())->method('getUser')->will($this->returnValue($user));

        $this->tokenStorage->expects($this->any())->method('getToken')->will($this->returnValue($token));

        $that = $this;
        $this->publisher->expects($this->once())->method('send')
            ->will(
                $this->returnCallback(
                    function ($topic, $payload) use ($that) {
                        $that->assertSame('oro/data/update', $topic, 'Should be the same as frontend code expects');

                        $tags = json_decode($payload, true);
                        $that->assertCount(2, $tags);

                        foreach ($tags as $tag) {
                            $that->assertArrayHasKey('username', $tag);
                            $that->assertArrayHasKey('tagname', $tag);

                            $that->assertSame(self::TEST_USERNAME, $tag['username']);
                        }
                    }
                )
            );

        $tags = [self::TEST_TAG1, self::TEST_TAG2];
        $this->sender->send($tags);
    }
}

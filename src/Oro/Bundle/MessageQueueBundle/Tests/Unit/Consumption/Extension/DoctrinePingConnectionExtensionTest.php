<?php
namespace Oro\Bundle\MessageQueueBundle\Tests\Unit\Consumption\Extension;

use Doctrine\DBAL\Connection;
use Oro\Bundle\MessageQueueBundle\Consumption\Extension\DoctrinePingConnectionExtension;
use Oro\Component\MessageQueue\Consumption\Context;
use Oro\Component\MessageQueue\Consumption\MessageProcessorInterface;
use Oro\Component\MessageQueue\Transport\MessageConsumerInterface;
use Oro\Component\MessageQueue\Transport\SessionInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Doctrine\RegistryInterface;

class DoctrinePingConnectionExtensionTest extends \PHPUnit_Framework_TestCase
{
    public function testCouldBeConstructedWithRequiredAttributes()
    {
        new DoctrinePingConnectionExtension($this->createRegistryMock());
    }

    public function testShouldNotReconnectIfConnectionIsOK()
    {
        $connection = $this->createConnectionMock();
        $connection
            ->expects($this->once())
            ->method('ping')
            ->will($this->returnValue(true))
        ;
        $connection
            ->expects($this->never())
            ->method('close')
        ;
        $connection
            ->expects($this->never())
            ->method('connect')
        ;

        $context = $this->createContext();
        $context->getLogger()
            ->expects($this->never())
            ->method('debug')
        ;

        $registry = $this->createRegistryMock();
        $registry
            ->expects($this->once())
            ->method('getConnections')
            ->will($this->returnValue([$connection]))
        ;

        $extension = new DoctrinePingConnectionExtension($registry);
        $extension->onPreReceived($context);
    }

    public function testShouldDoesReconnectIfConnectionFailed()
    {
        $connection = $this->createConnectionMock();
        $connection
            ->expects($this->once())
            ->method('ping')
            ->will($this->returnValue(false))
        ;
        $connection
            ->expects($this->once())
            ->method('close')
        ;
        $connection
            ->expects($this->once())
            ->method('connect')
        ;

        $context = $this->createContext();
        $context->getLogger()
            ->expects($this->at(0))
            ->method('debug')
            ->with('Connection "test" is not active, trying to reconnect.')
        ;
        $context->getLogger()
            ->expects($this->at(1))
            ->method('debug')
            ->with('Connection "test" is active now.')
        ;

        $registry = $this->createRegistryMock();
        $registry
            ->expects($this->once())
            ->method('getConnections')
            ->will($this->returnValue(['test' => $connection]))
        ;

        $extension = new DoctrinePingConnectionExtension($registry);
        $extension->onPreReceived($context);
    }

    /**
     * @return Context
     */
    protected function createContext()
    {
        $context = new Context($this->createMock(SessionInterface::class));
        $context->setLogger($this->createMock(LoggerInterface::class));
        $context->setMessageConsumer($this->createMock(MessageConsumerInterface::class));
        $context->setMessageProcessor($this->createMock(MessageProcessorInterface::class));

        return $context;
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|RegistryInterface
     */
    protected function createRegistryMock()
    {
        return $this->createMock(RegistryInterface::class);
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|Connection
     */
    protected function createConnectionMock()
    {
        return $this->createMock(Connection::class);
    }
}

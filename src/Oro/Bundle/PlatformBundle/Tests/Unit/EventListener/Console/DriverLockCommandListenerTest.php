<?php

namespace Oro\Bundle\PlatformBundle\Tests\Unit\EventListener\Console;

use Oro\Bundle\PlatformBundle\EventListener\Console\DriverLockCommandListener;
use Oro\Bundle\PlatformBundle\Maintenance\MaintenanceEvent;

class DriverLockCommandListenerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var DriverLockCommandListener
     */
    protected $target;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    protected $dispatcherInterface;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    protected $event;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject
     */
    protected $command;

    protected function setUp(): void
    {
        $this->dispatcherInterface = $this->createMock('Symfony\Component\EventDispatcher\EventDispatcherInterface');

        $this->event = $this->getMockBuilder('Symfony\Component\Console\Event\ConsoleTerminateEvent')
            ->disableOriginalConstructor()
            ->getMock();

        $this->command = $this->getMockBuilder('Symfony\Component\Console\Command\Command')
            ->disableOriginalConstructor()
            ->getMock();

        $this->target = new DriverLockCommandListener($this->dispatcherInterface);
    }

    public function testAfterExecuteShouldDispatchMaintenanceOnEvent()
    {
        $this->command->expects($this->once())
            ->method('getName')
            ->will($this->returnValue(DriverLockCommandListener::LEXIK_MAINTENANCE_LOCK));

        $this->dispatcherInterface->expects($this->once())
            ->method('dispatch')
            ->with(new MaintenanceEvent(), MaintenanceEvent::MAINTENANCE_ON);

        $this->event->expects($this->once())->method('getCommand')->will($this->returnValue($this->command));

        $this->target->afterExecute($this->event);
    }

    public function testAfterExecuteShouldDispatchMaintenanceOffEvent()
    {
        $this->command->expects($this->once())
            ->method('getName')
            ->will($this->returnValue(DriverLockCommandListener::LEXIK_MAINTENANCE_UNLOCK));

        $this->dispatcherInterface->expects($this->once())
            ->method('dispatch')
            ->with(new MaintenanceEvent(), MaintenanceEvent::MAINTENANCE_OFF);

        $this->event->expects($this->once())->method('getCommand')->will($this->returnValue($this->command));

        $this->target->afterExecute($this->event);
    }

    public function testNoEventDispatchedIfNotMaintenanceCommand()
    {
        $unknownCommand = 'UnknownCommand';
        $this->command->expects($this->once())
            ->method('getName')
            ->will($this->returnValue($unknownCommand));

        $this->dispatcherInterface->expects($this->never())->method('dispatch');

        $this->event->expects($this->once())->method('getCommand')->will($this->returnValue($this->command));

        $this->target->afterExecute($this->event);
    }
}

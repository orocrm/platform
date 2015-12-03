<?php
namespace Oro\Bundle\FlexibleEntityBundle\Tests\Unit\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Oro\Bundle\FlexibleEntityBundle\EventListener\ScopableListener;
use Oro\Bundle\FlexibleEntityBundle\Tests\Unit\AbstractFlexibleManagerTest;
use Oro\Bundle\FlexibleEntityBundle\Tests\Unit\Entity\Demo\Flexible;

/**
 * Test related class
 *
 */
class ScopableListenerTest extends AbstractFlexibleManagerTest
{

    /**
     * @var Flexible
     */
    protected $flexible;

    /**
     * Set up unit test
     */
    public function setUp()
    {
        parent::setUp();
        // create listener
        $this->listener = new ScopableListener();
        $this->listener->setContainer($this->container);
        // create flexible entity
        $this->flexible = new Flexible();
    }

    /**
     * test related method
     */
    public function testGetSubscribedEvents()
    {
        $events = array('postLoad');
        $this->assertEquals($this->listener->getSubscribedEvents(), $events);
    }

    /**
     * test related method
     */
    public function testPostLoad()
    {
        // check before
        $this->assertNull($this->flexible->getScope());
        // call method
        $args = new LifecycleEventArgs($this->flexible, $this->entityManager);
        $this->listener->postLoad($args);
        // check after (locale is setup)
        $this->assertEquals($this->flexible->getScope(), $this->defaultScope);
        // change locale from manager, and re-call
        $code = 'ecommerce';
        $this->manager->setScope($code);
        $this->listener->postLoad($args);
        //locale heas been changed by post load
        $this->assertEquals($this->flexible->getScope(), $code);
    }
}

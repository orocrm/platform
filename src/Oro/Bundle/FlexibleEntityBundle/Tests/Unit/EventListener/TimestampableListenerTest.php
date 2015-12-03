<?php
namespace Oro\Bundle\FlexibleEntityBundle\Tests\Unit\EventListener;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\Tests\OrmTestCase;
use Oro\Bundle\FlexibleEntityBundle\Entity\Attribute;
use Oro\Bundle\FlexibleEntityBundle\EventListener\TimestampableListener;

/**
 * Test related class
 *
 */
class TimestampableListenerTest extends OrmTestCase
{

    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * @var Attribute
     */
    protected $attribute;

    /**
     * @var TimestampableListener
     */
    protected $listener;

    /**
     * Set up unit test
     */
    public function setUp()
    {
        // create attribute
        $this->attribute = new Attribute();
        // create listener
        $this->listener = new TimestampableListener();
        // prepare test entity manager
        $reader = new AnnotationReader();
        $metadataDriver = new AnnotationDriver($reader, 'Oro\\Bundle\\FlexibleEntityBundle\\Entity');
        $this->em = $this->_getTestEntityManager();
        $this->em->getConfiguration()->setMetadataDriverImpl($metadataDriver);
    }

    /**
     * test related method
     */
    public function testGetSubscribedEvents()
    {
        $events = array('prePersist', 'preUpdate');
        $this->assertEquals($this->listener->getSubscribedEvents(), $events);
    }

    /**
     * test related method
     */
    public function testPrePersist()
    {
        // check before
        $this->assertNull($this->attribute->getCreated());
        $this->assertNull($this->attribute->getUpdated());
        // call method
        $args = new LifecycleEventArgs($this->attribute, $this->em);
        $this->listener->prePersist($args);
        // check after (dates are setup)
        $this->assertTrue($this->attribute->getCreated() instanceof \DateTime);
        $this->assertTrue($this->attribute->getUpdated() instanceof \DateTime);
    }

    /**
     * test related method
     */
    public function testPreUpdate()
    {
        $args = new LifecycleEventArgs($this->attribute, $this->em);
        $this->listener->prePersist($args);
        // check before
        $created = $this->attribute->getCreated();
        $updated = $this->attribute->getUpdated();
        $this->assertTrue($created instanceof \DateTime);
        $this->assertTrue($updated instanceof \DateTime);
        // call method
        sleep(1); // to be sure update date is different
        $args = new LifecycleEventArgs($this->attribute, $this->em);
        $this->listener->preUpdate($args);
        // check after (only update date has been changed)
        $this->assertEquals($this->attribute->getCreated(), $created);
        $this->assertNotEquals($this->attribute->getUpdated(), $updated);
    }
}

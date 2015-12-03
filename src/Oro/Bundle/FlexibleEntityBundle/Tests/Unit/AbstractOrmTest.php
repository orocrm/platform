<?php

namespace Oro\Bundle\FlexibleEntityBundle\Tests\Unit;

use Doctrine\Common\Annotations\AnnotationReader;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\Tests\OrmTestCase;
use Oro\Bundle\FlexibleEntityBundle\Manager\FlexibleManager;
use Symfony\Component\DependencyInjection\Container;

/**
 * Abstract test class which mock the entity manager
 */
abstract class AbstractOrmTest extends OrmTestCase
{

    /**
     * @var string
     */
    protected $entityPath = 'Oro\\Bundle\\FlexibleEntityBundle\\Test\\Entity\\Demo';

    /**
     * @var Container
     */
    protected $container;

    /**
     * @var EntityManager
     */
    protected $entityManager;

    /**
     * Set up unit test
     */
    public function setUp()
    {
        // prepare test entity manager
        $reader = new AnnotationReader();
        $metadataDriver = new AnnotationDriver($reader, $this->entityPath);
        $this->entityManager = $this->_getTestEntityManager();
        $this->entityManager->getConfiguration()->setMetadataDriverImpl($metadataDriver);
        // prepare test container
        $this->container = new Container();
        $this->container->set('doctrine.orm.entity_manager', $this->entityManager);
    }
}

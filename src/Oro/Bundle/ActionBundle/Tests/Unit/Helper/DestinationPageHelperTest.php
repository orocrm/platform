<?php

namespace Oro\Bundle\ActionBundle\Tests\Unit\Helper;

use Oro\Bundle\ActionBundle\Helper\DestinationPageHelper;
use Oro\Bundle\EntityConfigBundle\Helper\EntityConfigHelper;
use Oro\Bundle\TestFrameworkBundle\Entity\Item;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;

class DestinationPageHelperTest extends \PHPUnit\Framework\TestCase
{
    /** @var RequestStack|\PHPUnit\Framework\MockObject\MockObject */
    private $requestStack;

    /** @var EntityConfigHelper|\PHPUnit\Framework\MockObject\MockObject */
    private $entityConfigHelper;

    /** @var RouterInterface|\PHPUnit\Framework\MockObject\MockObject */
    private $router;

    /** @var DestinationPageHelper */
    private $helper;

    protected function setUp(): void
    {
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->router = $this->createMock(RouterInterface::class);
        $this->entityConfigHelper = $this->createMock(EntityConfigHelper::class);

        $this->helper = new DestinationPageHelper($this->requestStack, $this->entityConfigHelper, $this->router);
    }

    public function testGetAvailableDestinations()
    {
        $this->entityConfigHelper->expects($this->once())
            ->method('getRoutes')
            ->with('TestClass', DestinationPageHelper::AVAILABLE_DESTINATIONS)
            ->willReturn(['name' => 'index_route', 'view' => 'view_route', 'custom' => 'custom_route']);

        $this->assertEquals(['name', 'view'], $this->helper->getAvailableDestinations('TestClass'));
    }

    public function testGetAvailableDestinationsWithoutRoutes()
    {
        $this->entityConfigHelper->expects($this->once())
            ->method('getRoutes')
            ->with('TestClass', DestinationPageHelper::AVAILABLE_DESTINATIONS)
            ->willReturn(['custom' => 'custom_route']);

        $this->assertEquals([], $this->helper->getAvailableDestinations('TestClass'));
    }

    public function testGetOriginalUrl()
    {
        $this->requestStack->expects($this->once())
            ->method('getMasterRequest')
            ->willReturn(
                new Request([], [], [], [], [], ['REQUEST_URI' => 'example.com'])
            );

        $this->assertEquals('example.com', $this->helper->getOriginalUrl());
    }

    public function testGetDestinationUrls()
    {
        $entity = new Item();
        $entity->id = 10;

        $this->entityConfigHelper->expects($this->once())
            ->method('getRoutes')
            ->with($entity, DestinationPageHelper::AVAILABLE_DESTINATIONS)
            ->willReturn(['name' => 'index_route', 'view' => 'view_route', 'custom' => 'custom_route']);

        $this->router->expects($this->exactly(2))
            ->method('generate')
            ->withConsecutive(
                ['index_route'],
                ['view_route', ['id' => 10]]
            )
            ->willReturnOnConsecutiveCalls(
                'example.com/index',
                'example.com/view'
            );

        $this->assertEquals(
            [
                'name' => 'example.com/index',
                'view' => 'example.com/view'
            ],
            $this->helper->getDestinationUrls($entity)
        );
    }

    public function testGetDestinationUrlsWithoutEntityId()
    {
        $entity = new Item();

        $this->entityConfigHelper->expects($this->once())
            ->method('getRoutes')
            ->with($entity, DestinationPageHelper::AVAILABLE_DESTINATIONS)
            ->willReturn(['name' => 'index_route', 'view' => 'view_route', 'custom' => 'custom_route']);

        $this->router->expects($this->once())
            ->method('generate')
            ->with('index_route')
            ->willReturn('example.com/index');

        $this->assertEquals(
            [
                'name' => 'example.com/index',
            ],
            $this->helper->getDestinationUrls($entity)
        );
    }

    public function testGetDestinationUrlsWithoutRoutes()
    {
        $entity = new Item();
        $entity->id = 10;

        $this->entityConfigHelper->expects($this->once())
            ->method('getRoutes')
            ->with($entity, DestinationPageHelper::AVAILABLE_DESTINATIONS)
            ->willReturn(['custom' => 'custom_route']);

        $this->router->expects($this->never())
            ->method('generate');

        $this->assertEquals([], $this->helper->getDestinationUrls($entity));
    }

    public function testGetDestinationUrl()
    {
        $entity = new Item();
        $entity->id = 10;

        $this->entityConfigHelper->expects($this->any())
            ->method('getRoutes')
            ->willReturn(['name' => 'index_route', 'view' => 'view_route']);

        $this->router->expects($this->any())
            ->method('generate')
            ->willReturnMap([
                ['index_route', [], RouterInterface::ABSOLUTE_PATH, 'example.com/index'],
                ['view_route', ['id' => 10], RouterInterface::ABSOLUTE_PATH, 'example.com/view'],
            ]);

        $this->assertEquals('example.com/index', $this->helper->getDestinationUrl($entity, 'name'));
        $this->assertEquals('example.com/view', $this->helper->getDestinationUrl($entity, 'view'));
        $this->assertEquals(null, $this->helper->getDestinationUrl($entity, 'unknown_route'));
    }
}

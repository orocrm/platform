<?php

namespace Oro\Bundle\DashboardBundle\Tests\Unit\EventListener;

use Oro\Bundle\DashboardBundle\EventListener\NavigationListener;
use Oro\Bundle\DashboardBundle\Model\Manager;
use Oro\Bundle\NavigationBundle\Event\ConfigureMenuEvent;
use Oro\Bundle\SecurityBundle\Authentication\TokenAccessorInterface;

class NavigationListenerTest extends \PHPUnit_Framework_TestCase
{
    /** @var NavigationListener */
    protected $navigationListener;

    /** @var TokenAccessorInterface|\PHPUnit_Framework_MockObject_MockObject */
    protected $tokenAccessor;

    /** @var Manager|\PHPUnit_Framework_MockObject_MockObject */
    protected $manager;

    protected function setUp()
    {
        $this->tokenAccessor = $this->createMock(TokenAccessorInterface::class);

        $this->manager = $this->getMockBuilder('Oro\Bundle\DashboardBundle\Model\Manager')
            ->setMethods(['getDashboards', 'findAllowedDashboards'])
            ->disableOriginalConstructor()
            ->getMock();

        $this->navigationListener = new NavigationListener(
            $this->tokenAccessor,
            $this->manager
        );
    }

    public function testOnNavigationConfigureCheckIfMenuAndUserExists()
    {
        /** @var ConfigureMenuEvent|\PHPUnit_Framework_MockObject_MockObject $event */
        $event = $this->getMockBuilder('Oro\Bundle\NavigationBundle\Event\ConfigureMenuEvent')
            ->disableOriginalConstructor()
            ->getMock();

        $menu = $this->createMock('Knp\Menu\ItemInterface');
        $item = $this->createMock('Knp\Menu\ItemInterface');

        $event->expects($this->exactly(2))->method('getMenu')->will($this->returnValue($menu));

        $this->manager->expects($this->never())->method('getDashboards');

        $menu->expects($this->at(0))->method('getChild')->will($this->returnValue(null));
        $menu->expects($this->at(1))->method('getChild')->will($this->returnValue($item));
        $menu->expects($this->any())->method('getChildren')->will($this->returnValue([]));

        $this->navigationListener->onNavigationConfigure($event);
        $this->navigationListener->onNavigationConfigure($event);
    }

    public function testOnNavigationConfigureAddCorrectItems()
    {
        $event = $this->getMockBuilder('Oro\Bundle\NavigationBundle\Event\ConfigureMenuEvent')
            ->disableOriginalConstructor()
            ->getMock();

        $id = 42;
        $secondId = 43;
        $expectedLabel = 'expected label';
        $secondExpectedLabel = 'test expected label';

        $dashboardModel = $this->getMockBuilder('Oro\Bundle\DashboardBundle\Model\DashboardModel')
            ->disableOriginalConstructor()
            ->getMock();
        $dashboardModel->expects($this->once())->method('getId')->will($this->returnValue($id));
        $dashboardModel->expects($this->once())->method('getLabel')->will($this->returnValue($expectedLabel));

        $secondDashboardModel = $this->getMockBuilder('Oro\Bundle\DashboardBundle\Model\DashboardModel')
            ->disableOriginalConstructor()
            ->getMock();
        $secondDashboardModel->expects($this->once())
            ->method('getId')
            ->will($this->returnValue($secondId));
        $secondDashboardModel->expects($this->once())
            ->method('getLabel')
            ->will($this->returnValue($secondExpectedLabel));


        $dashboards = array($dashboardModel, $secondDashboardModel);
        $menuItemAlias = $id.'_dashboard_menu_item';
        $secondMenuItemAlias = $secondId.'_dashboard_menu_item';

        $expectedOptions = array(
            'label'           => $expectedLabel,
            'route'           => 'oro_dashboard_view',
            'extras'          => array(
                'position' => 1
            ),
            'routeParameters' => array(
                'id'               => $id,
                'change_dashboard' => true
            )
        );
        $secondExpectedOptions = array(
            'label'           => $secondExpectedLabel,
            'route'           => 'oro_dashboard_view',
            'extras'          => array(
                'position' => 1
            ),
            'routeParameters' => array(
                'id'               => $secondId,
                'change_dashboard' => true
            )
        );

        $menu = $this->createMock('Knp\Menu\ItemInterface');
        $item = $this->createMock('Knp\Menu\ItemInterface');
        $child = $this->createMock('Knp\Menu\ItemInterface');
        $child->expects($this->atLeastOnce())->method('setAttribute')->with('data-menu')->will($this->returnSelf());

        $divider = $this->createMock('Knp\Menu\ItemInterface');
        $divider->expects($this->once())->method('setLabel')->with('')->will($this->returnSelf());
        $divider->expects($this->once())->method('setAttribute')->with('class', 'menu-divider')
            ->will($this->returnSelf());
        $divider->expects($this->exactly(2))->method('setExtra')
            ->will($this->returnValueMap([
                ['position', 2, $divider],
                ['divider', true, $divider]
            ]));

        $item->expects($this->at(0))
            ->method('addChild')
            ->with($menuItemAlias, $this->equalTo($expectedOptions))
            ->will($this->returnValue($child));
        $item->expects($this->at(1))
            ->method('addChild')
            ->with($secondMenuItemAlias, $this->equalTo($secondExpectedOptions))
            ->will($this->returnValue($child));
        $item->expects($this->at(2))->method('addChild')->will($this->returnValue($divider));

        $menu->expects($this->once())->method('getChild')->will($this->returnValue($item));
        $event->expects($this->once())->method('getMenu')->will($this->returnValue($menu));
        $this->tokenAccessor->expects($this->once())->method('hasUser')->will($this->returnValue(true));
        $this->manager->expects($this->once())->method('findAllowedDashboards')->will($this->returnValue($dashboards));

        $this->navigationListener->onNavigationConfigure($event);
    }
}

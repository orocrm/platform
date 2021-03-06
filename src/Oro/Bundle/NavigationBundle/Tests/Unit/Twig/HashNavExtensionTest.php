<?php

namespace Oro\Bundle\NavigationBundle\Tests\Unit\Twig;

use Oro\Bundle\NavigationBundle\Event\ResponseHashnavListener;
use Oro\Bundle\NavigationBundle\Twig\HashNavExtension;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernel;

class HashNavExtensionTest extends \PHPUnit\Framework\TestCase
{
    private HashNavExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new HashNavExtension();
    }

    public function testCheckIsHashNavigation(): void
    {
        $event = $this->createMock(RequestEvent::class);

        $event->expects(self::once())
            ->method('getRequestType')
            ->willReturn(HttpKernel::MASTER_REQUEST);

        $request = $this->createMock(Request::class);

        $event->expects(self::once())
            ->method('getRequest')
            ->willReturn($request);

        $request->headers = $this->createMock(HeaderBag::class);

        $request->headers->expects(self::once())
            ->method('get')
            ->willReturn(false);

        $request->expects(self::once())
            ->method('get')
            ->willReturn(true);

        $this->extension->onKernelRequest($event);

        self::assertTrue($this->extension->checkIsHashNavigation());
    }

    public function testGetHashNavigationHeaderConst(): void
    {
        self::assertEquals(
            ResponseHashnavListener::HASH_NAVIGATION_HEADER,
            $this->extension->getHashNavigationHeaderConst()
        );
    }

    public function testGetName(): void
    {
        self::assertEquals('oro_hash_nav', $this->extension->getName());
    }
}

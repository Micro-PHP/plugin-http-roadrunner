<?php

/*
 *  This file is part of the Micro framework package.
 *
 *  (c) Stanislau Komar <kost@micro-php.net>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

namespace Micro\Plugin\Http\Test\Unit\Listener;

use Micro\Component\EventEmitter\EventInterface;
use Micro\Framework\Kernel\KernelInterface;
use Micro\Kernel\App\AppKernelInterface;
use Micro\Kernel\App\Business\Event\ApplicationReadyEvent;
use Micro\Plugin\Http\Facade\HttpFacadeInterface;
use Micro\Plugin\Http\Listener\ApplicationRoadrunnerStartedListener;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ApplicationRoadrunnerStartedListenerTest extends TestCase
{
    private KernelInterface|MockObject $kernel;

    private EventInterface $event;

    protected function setUp(): void
    {
        $this->kernel = $this->createMock(AppKernelInterface::class);
        $this->event = new ApplicationReadyEvent(
            $this->kernel,
            'cli',
        );
    }

    public function testOnWithoutCliAndRrMode(): void
    {
        $httpFacade = $this->createMock(HttpFacadeInterface::class);
        $listener = new ApplicationRoadrunnerStartedListener($httpFacade);
        putenv('RR_MODE');
        $event = new ApplicationReadyEvent(
            $this->kernel,
            'web',
        );
        $httpFacade->expects($this->never())
            ->method('execute');
        $listener->on($event);
    }

    public function testSupports(): void
    {
        $httpFacade = $this->createMock(HttpFacadeInterface::class);
        $listener = new ApplicationRoadrunnerStartedListener($httpFacade);
        $result = $listener->supports($this->event);
        $this->assertTrue($result);
    }
}

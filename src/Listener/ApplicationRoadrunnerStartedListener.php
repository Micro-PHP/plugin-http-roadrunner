<?php

declare(strict_types=1);

/*
 *  This file is part of the Micro framework package.
 *
 *  (c) Stanislau Komar <kost@micro-php.net>
 *
 *  For the full copyright and license information, please view the LICENSE
 *  file that was distributed with this source code.
 */

namespace Micro\Plugin\Http\Listener;

use Micro\Component\EventEmitter\EventInterface;
use Micro\Component\EventEmitter\EventListenerInterface;
use Micro\Kernel\App\Business\Event\ApplicationReadyEvent;
use Micro\Kernel\App\Business\Event\ApplicationReadyEventInterface;
use Micro\Plugin\Http\Facade\HttpFacadeInterface;
use Micro\Plugin\Http\Facade\HttpRoadrunnerFacadeInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Spiral\RoadRunner;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

final readonly class ApplicationRoadrunnerStartedListener implements EventListenerInterface
{
    public function __construct(
        private HttpFacadeInterface $httpFacade,
        private HttpRoadrunnerFacadeInterface $httpRoadrunnerFacade
    ) {
    }

    /**
     * @param ApplicationReadyEvent $event
     *
     * @psalm-suppress MoreSpecificImplementedParamType
     *
     * @throws \JsonException
     */
    public function on(EventInterface $event): void
    {
        $sysenv = $event->systemEnvironment();
        if ('cli' !== $sysenv || !getenv('RR_MODE')) {
            return;
        }

        $httpFoundationFactory = new HttpFoundationFactory();
        $psr17Factory = new Psr17Factory();
        $httpMessageFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);

        $worker = RoadRunner\Worker::create();
        $worker = new RoadRunner\Http\PSR7Worker($worker, $psr17Factory, $psr17Factory, $psr17Factory);
        $i = 0;
        $gcCollectStep = $this->httpRoadrunnerFacade->getGcCollectCyclesCount();
        while ($request = $worker->waitRequest()) {
            try {
                $isStreamed = \in_array(mb_strtolower($request->getMethod()), [
                    'post', 'put', 'patch',
                ]);

                $appRequest = $httpFoundationFactory->createRequest($request, $isStreamed);
                $appResponse = $this->httpFacade->execute($appRequest, false);
                $worker->respond($httpMessageFactory->createResponse($appResponse));
            } catch (\Throwable $e) {
                $worker->getWorker()->error((string) $e);
            }

            if (++$i < $gcCollectStep) {
                continue;
            }

            gc_collect_cycles();
            $i = 0;
        }
    }

    public static function supports(EventInterface $event): bool
    {
        return $event instanceof ApplicationReadyEventInterface;
    }
}

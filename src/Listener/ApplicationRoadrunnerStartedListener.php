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
use Micro\Plugin\Http\Exception\HttpException;
use Micro\Plugin\Http\Facade\HttpFacadeInterface;
use Micro\Plugin\Http\Facade\HttpRoadrunnerFacadeInterface;
use Micro\Plugin\Logger\Facade\LoggerFacadeInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response as Psr7Response;
use Spiral\RoadRunner;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

final readonly class ApplicationRoadrunnerStartedListener implements EventListenerInterface
{
    public function __construct(
        private HttpFacadeInterface $httpFacade,
        private HttpRoadrunnerFacadeInterface $httpRoadrunnerFacade,
        private LoggerFacadeInterface $loggerFacade,
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
        $rrMode = getenv('RR_MODE');
        if ('cli' !== $sysenv || 'http' !== $rrMode) {
            return;
        }

        $logger = $this->loggerFacade->getLogger();
        $httpFoundationFactory = new HttpFoundationFactory();
        $psr17Factory = new Psr17Factory();
        $httpMessageFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);

        $psr7 = new RoadRunner\Http\PSR7Worker(RoadRunner\Worker::create(), $psr17Factory, $psr17Factory, $psr17Factory);
        $i = 0;
        $gcCollectStep = $this->httpRoadrunnerFacade->getGcCollectCyclesCount();
        while (true) {
            try {
                $request = $psr7->waitRequest();
                if(!$request) {
                    break;
                }
            } catch (\Throwable $e) {
                $psr7->respond(new Psr7Response(500));
                if (!($e instanceof HttpException)) {
                    $logger->error('RoadRunner Exception [Request]: '.$e->getMessage(), ['exception' => $e]);
                }

                continue;
            }

            try {
                $appRequest = $httpFoundationFactory->createRequest($request);
                $appResponse = $this->httpFacade->execute($appRequest, false);
                $psr7->respond($httpMessageFactory->createResponse($appResponse));
            } catch (\Throwable $e) {
                $psr7->respond(new Psr7Response(500));
                $psr7->getWorker()->error((string) $e);
                $logger->error('RoadRunner Exception [Response]: '.$e->getMessage(), ['exception' => $e]);
            } finally {
                if (++$i === $gcCollectStep) {
                    gc_collect_cycles();
                }
            }
        }
    }

    public static function supports(EventInterface $event): bool
    {
        return $event instanceof ApplicationReadyEventInterface;
    }
}

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

namespace Micro\Plugin\Http;

use Micro\Component\DependencyInjection\Container;
use Micro\Framework\Kernel\Plugin\ConfigurableInterface;
use Micro\Framework\Kernel\Plugin\DependencyProviderInterface;
use Micro\Framework\Kernel\Plugin\PluginConfigurationTrait;
use Micro\Framework\Kernel\Plugin\PluginDependedInterface;
use Micro\Plugin\EventEmitter\EventEmitterPlugin;
use Micro\Plugin\Http\Facade\HttpRoadrunnerFacade;
use Micro\Plugin\Http\Facade\HttpRoadrunnerFacadeInterface;

/**
 * @method HttpRoadrunnerPluginConfigurationInterface configuration()
 */
final class HttpRoadrunnerPlugin implements DependencyProviderInterface, PluginDependedInterface, ConfigurableInterface
{
    use PluginConfigurationTrait;

    public function provideDependencies(Container $container): void
    {
        $container->register(HttpRoadrunnerFacadeInterface::class, function () {
            return new HttpRoadrunnerFacade($this->configuration());
        });
    }

    public function getDependedPlugins(): iterable
    {
        return [
            EventEmitterPlugin::class,
            HttpCorePlugin::class,
        ];
    }
}

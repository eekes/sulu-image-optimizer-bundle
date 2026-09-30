<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Eekes\Sulu\ImageOptimizerBundle\Application\EventListener\MediaUploadListener;
use Eekes\Sulu\ImageOptimizerBundle\Application\Service\ImageOptimizer;
use Eekes\Sulu\ImageOptimizerBundle\Application\Service\OptimizationRecorder;

// The upload listener on its own, so "enabled: false" switches off exactly this and nothing else.
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(MediaUploadListener::class)
        ->arg('$imageOptimizer', service(ImageOptimizer::class))
        ->arg('$recorder', service(OptimizationRecorder::class))
        // Below the firewall (priority 8), so only a request that may upload ever costs the CPU.
        ->tag('kernel.event_listener', ['event' => 'kernel.request', 'method' => 'onKernelRequest', 'priority' => 0])
        ->tag('kernel.event_listener', ['event' => 'kernel.response', 'method' => 'onKernelResponse']);
};

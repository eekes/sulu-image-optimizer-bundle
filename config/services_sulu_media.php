<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Eekes\Sulu\ImageOptimizerBundle\Application\Command\OptimizeExistingMediaCommand;
use Eekes\Sulu\ImageOptimizerBundle\Application\EventListener\MediaStoredListener;
use Eekes\Sulu\ImageOptimizerBundle\Application\Service\ExistingMediaOptimizer;
use Eekes\Sulu\ImageOptimizerBundle\Application\Service\ImageOptimizer;
use Eekes\Sulu\ImageOptimizerBundle\Application\Service\MediaLibraryInterface;
use Eekes\Sulu\ImageOptimizerBundle\Application\Service\OptimizationRecorder;
use Eekes\Sulu\ImageOptimizerBundle\Domain\Repository\ImageOptimizationRepository;
use Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Sulu\Media\SuluMediaLibrary;
use Sulu\Bundle\MediaBundle\Domain\Event\MediaCreatedEvent;
use Sulu\Bundle\MediaBundle\Domain\Event\MediaVersionAddedEvent;

/*
 * Loaded only when SuluMediaBundle is registered: linking log entries to the media they became, and
 * optimizing the images that are already in the media library.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(MediaStoredListener::class)
        ->arg('$recorder', service(OptimizationRecorder::class))
        ->tag('kernel.event_listener', ['event' => MediaCreatedEvent::class, 'method' => 'onMediaCreated'])
        ->tag('kernel.event_listener', ['event' => MediaVersionAddedEvent::class, 'method' => 'onMediaVersionAdded']);

    $services->set(SuluMediaLibrary::class)
        ->arg('$entityManager', service('doctrine.orm.entity_manager'))
        ->arg('$mediaRepository', service('sulu.repository.media'))
        ->arg('$mediaManager', service('sulu_media.media_manager'))
        ->arg('$storage', service('sulu_media.storage'));
    $services->alias(MediaLibraryInterface::class, SuluMediaLibrary::class);

    $services->set(ExistingMediaOptimizer::class)
        ->arg('$imageOptimizer', service(ImageOptimizer::class))
        ->arg('$recorder', service(OptimizationRecorder::class))
        ->arg('$mediaLibrary', service(MediaLibraryInterface::class))
        ->arg('$imageOptimizationRepository', service(ImageOptimizationRepository::class))
        ->arg('$logger', service('logger'))
        ->tag('monolog.logger', ['channel' => 'image_optimizer']);

    $services->set(OptimizeExistingMediaCommand::class)
        ->arg('$existingMediaOptimizer', service(ExistingMediaOptimizer::class))
        ->tag('console.command');
};

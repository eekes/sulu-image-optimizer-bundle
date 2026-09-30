<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Eekes\Sulu\ImageOptimizerBundle\Application\Command\CheckOptimizersCommand;
use Eekes\Sulu\ImageOptimizerBundle\Application\Service\ImageEncoderInterface;
use Eekes\Sulu\ImageOptimizerBundle\Application\Service\ImageOptimizer;
use Eekes\Sulu\ImageOptimizerBundle\Application\Service\MemoryBudget;
use Eekes\Sulu\ImageOptimizerBundle\Application\Service\OptimizationRecorder;
use Eekes\Sulu\ImageOptimizerBundle\Application\Service\OptimizerSettings;
use Eekes\Sulu\ImageOptimizerBundle\Application\Service\OptimizerToolsInterface;
use Eekes\Sulu\ImageOptimizerBundle\Application\Service\SvgSanitizerInterface;
use Eekes\Sulu\ImageOptimizerBundle\Domain\Repository\ImageOptimizationRepository;
use Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Doctrine\ImageOptimizationTableListener;
use Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Spatie\SpatieImageEncoder;
use Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Spatie\SpatieOptimizerTools;
use Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Svg\EnshrinedSvgSanitizer;

/*
 * Core services: the optimizer and the log. Everything that needs the Sulu media or admin bundle
 * lives in the sibling files, so the bundle also boots in a project (or test kernel) without them.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(OptimizerSettings::class)
        ->factory([OptimizerSettings::class, 'fromConfiguration'])
        ->args([
            param('eekes_sulu_image_optimizer.resize_enabled'),
            param('eekes_sulu_image_optimizer.max_size'),
            param('eekes_sulu_image_optimizer.ignore_types'),
            param('eekes_sulu_image_optimizer.quality'),
            param('eekes_sulu_image_optimizer.strip_metadata'),
            param('eekes_sulu_image_optimizer.sanitize_svg'),
        ]);

    $services->set(SpatieImageEncoder::class);
    $services->alias(ImageEncoderInterface::class, SpatieImageEncoder::class);

    $services->set(SpatieOptimizerTools::class)
        ->arg('$settings', service(OptimizerSettings::class))
        ->arg('$encoder', service(ImageEncoderInterface::class))
        ->arg('$logger', service('logger'))
        ->arg('$binaryPath', param('eekes_sulu_image_optimizer.binary_path'))
        ->arg('$timeout', param('eekes_sulu_image_optimizer.timeout'))
        ->tag('monolog.logger', ['channel' => 'image_optimizer']);
    $services->alias(OptimizerToolsInterface::class, SpatieOptimizerTools::class);

    $services->set(EnshrinedSvgSanitizer::class);
    $services->alias(SvgSanitizerInterface::class, EnshrinedSvgSanitizer::class);

    $services->set(MemoryBudget::class);

    $services->set(ImageOptimizer::class)
        ->arg('$settings', service(OptimizerSettings::class))
        ->arg('$encoder', service(ImageEncoderInterface::class))
        ->arg('$tools', service(OptimizerToolsInterface::class))
        ->arg('$svgSanitizer', service(SvgSanitizerInterface::class))
        ->arg('$memoryBudget', service(MemoryBudget::class))
        ->arg('$logger', service('logger'))
        ->tag('monolog.logger', ['channel' => 'image_optimizer']);

    $services->set(ImageOptimizationRepository::class)
        ->args([service('doctrine')])
        ->tag('doctrine.repository_service');

    $services->set(OptimizationRecorder::class)
        ->arg('$imageOptimizationRepository', service(ImageOptimizationRepository::class))
        ->arg('$logger', service('logger'))
        ->tag('monolog.logger', ['channel' => 'image_optimizer'])
        // A worker handling several requests must never carry an entry into the next one.
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(ImageOptimizationTableListener::class)
        ->arg('$tableName', param('eekes_sulu_image_optimizer.table_name'))
        ->tag('doctrine.event_listener', ['event' => 'loadClassMetadata']);

    $services->set(CheckOptimizersCommand::class)
        ->arg('$tools', service(OptimizerToolsInterface::class))
        ->tag('console.command');
};

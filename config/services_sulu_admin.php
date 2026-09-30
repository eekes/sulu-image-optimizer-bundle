<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Eekes\Sulu\ImageOptimizerBundle\Application\Service\OptimizerToolsInterface;
use Eekes\Sulu\ImageOptimizerBundle\Domain\Repository\ImageOptimizationRepository;
use Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Sulu\Admin\ImageOptimizationAdmin;
use Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Sulu\Controller\ImageOptimizationBatchDeleteController;
use Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Sulu\Controller\ImageOptimizationDeleteController;
use Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Sulu\Controller\ImageOptimizationListController;
use Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Sulu\Controller\ImageOptimizationStatisticsController;

/*
 * Loaded only when the Sulu admin bundle is registered: the log under Settings and the endpoints
 * behind it.
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(ImageOptimizationAdmin::class)
        ->arg('$viewBuilderFactory', service('sulu_admin.view_builder_factory'))
        ->arg('$securityChecker', service('sulu_security.security_checker'))
        ->arg('$urlGenerator', service('router'))
        ->arg('$navigationPosition', param('eekes_sulu_image_optimizer.navigation_position'))
        ->tag('sulu.admin');

    // The controllers are tagged by hand and never autoconfigured. Symfony answers a #[Route]
    // attribute on an autoconfigured service with the routing.controller tag as well, and an
    // application that imports "resource: routing.controllers" - the default of the Symfony
    // skeleton - then publishes these routes a second time, without the /admin/api prefix they are
    // imported under. That second set falls outside the firewall guarding the administration
    // interface, which would leave the log readable, and deletable, by anyone knowing the path.
    //
    // Skipping autoconfiguration means the two things AbstractController relies on have to be set
    // by hand: autowire(), so the #[Required] setContainer() of the base class is called at all,
    // and the container.service_subscriber tag, so the argument of that call resolves to the
    // service locator holding what the base class subscribes to.
    $services->set(ImageOptimizationListController::class)
        ->autowire()
        ->tag('container.service_subscriber')
        ->arg('$restHelper', service('sulu_core.doctrine_rest_helper'))
        ->arg('$fieldDescriptorFactory', service('sulu_core.list_builder.field_descriptor_factory'))
        ->arg('$listBuilderFactory', service('sulu_core.doctrine_list_builder_factory'))
        ->tag('controller.service_arguments');

    $services->set(ImageOptimizationDeleteController::class)
        ->autowire()
        ->tag('container.service_subscriber')
        ->arg('$imageOptimizationRepository', service(ImageOptimizationRepository::class))
        ->tag('controller.service_arguments');

    $services->set(ImageOptimizationBatchDeleteController::class)
        ->autowire()
        ->tag('container.service_subscriber')
        ->arg('$imageOptimizationRepository', service(ImageOptimizationRepository::class))
        ->tag('controller.service_arguments');

    $services->set(ImageOptimizationStatisticsController::class)
        ->autowire()
        ->tag('container.service_subscriber')
        ->arg('$imageOptimizationRepository', service(ImageOptimizationRepository::class))
        ->arg('$tools', service(OptimizerToolsInterface::class))
        ->tag('controller.service_arguments');
};

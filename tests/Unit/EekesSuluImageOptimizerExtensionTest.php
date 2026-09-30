<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Tests\Unit;

use Eekes\Sulu\ImageOptimizerBundle\Application\Command\OptimizeExistingMediaCommand;
use Eekes\Sulu\ImageOptimizerBundle\Application\EventListener\MediaStoredListener;
use Eekes\Sulu\ImageOptimizerBundle\Application\EventListener\MediaUploadListener;
use Eekes\Sulu\ImageOptimizerBundle\Application\Service\ImageOptimizer;
use Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Sulu\Admin\ImageOptimizationAdmin;
use Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Sulu\Controller\ImageOptimizationListController;
use Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Symfony\DependencyInjection\EekesSuluImageOptimizerExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Which services the extension registers, depending on the bundles a project has.
 *
 * The container is built the way Symfony builds it for an extension: load() is handed a throwaway
 * container that carries the parameters but none of the other extensions. Gating the Sulu services
 * on hasExtension() would therefore silently register nothing at all, which no test in the light
 * kernel could notice - it has no Sulu bundles either way.
 */
#[CoversClass(EekesSuluImageOptimizerExtension::class)]
final class EekesSuluImageOptimizerExtensionTest extends TestCase
{
    private const array MEDIA = ['SuluMediaBundle' => 'Sulu\Bundle\MediaBundle\SuluMediaBundle'];
    private const array ADMIN = ['SuluAdminBundle' => 'Sulu\Bundle\AdminBundle\SuluAdminBundle'];

    public function testWithoutSuluOnlyTheOptimizerItselfIsThere(): void
    {
        $container = $this->load([]);

        self::assertTrue($container->hasDefinition(ImageOptimizer::class));
        self::assertFalse($container->hasDefinition(MediaUploadListener::class));
        self::assertFalse($container->hasDefinition(ImageOptimizationAdmin::class));
    }

    public function testAProjectWithTheMediaBundleGetsItsUploadsOptimized(): void
    {
        $container = $this->load(self::MEDIA);

        self::assertTrue($container->hasDefinition(MediaUploadListener::class));
        self::assertTrue($container->hasDefinition(MediaStoredListener::class));
        self::assertTrue($container->hasDefinition(OptimizeExistingMediaCommand::class));
    }

    /**
     * Switching the optimizer off stops it from touching uploads, but leaves the command that a
     * developer runs on purpose, and the log of what happened before.
     */
    public function testSwitchingItOffOnlyStopsTheUploadListener(): void
    {
        $container = $this->load(self::MEDIA + self::ADMIN, [['enabled' => false]]);

        self::assertFalse($container->hasDefinition(MediaUploadListener::class));
        self::assertTrue($container->hasDefinition(OptimizeExistingMediaCommand::class));
        self::assertTrue($container->hasDefinition(ImageOptimizationAdmin::class));
    }

    public function testTheLogIsRegisteredForAProjectWithTheAdminBundle(): void
    {
        $container = $this->load(self::ADMIN);

        self::assertTrue($container->hasDefinition(ImageOptimizationAdmin::class));
        self::assertTrue($container->hasDefinition(ImageOptimizationListController::class));
    }

    public function testTheLogCanBeSwitchedOffWhileTheOptimizerKeepsWorking(): void
    {
        $container = $this->load(self::MEDIA + self::ADMIN, [['admin' => ['enabled' => false]]]);

        self::assertFalse($container->hasDefinition(ImageOptimizationAdmin::class));
        self::assertTrue($container->hasDefinition(MediaUploadListener::class));
    }

    /**
     * Autoconfiguration answers the #[Route] attribute on these controllers with the
     * routing.controller tag, and an application importing "resource: routing.controllers" then
     * publishes every route a second time without the /admin/api prefix - outside the firewall that
     * guards the administration interface.
     */
    public function testTheControllersAreNotPublishedAsRoutesOfTheirOwn(): void
    {
        $controller = $this->load(self::ADMIN)->getDefinition(ImageOptimizationListController::class);

        self::assertFalse($controller->isAutoconfigured());
        self::assertSame([], $controller->getTag('routing.controller'));
        self::assertNotSame([], $controller->getTag('controller.service_arguments'));
    }

    /**
     * @param array<string, string> $bundles the kernel.bundles parameter of the project
     * @param array<array<mixed>>   $configs
     */
    private function load(array $bundles, array $configs = [[]]): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', $bundles);

        (new EekesSuluImageOptimizerExtension())->load($configs, $container);

        return $container;
    }
}

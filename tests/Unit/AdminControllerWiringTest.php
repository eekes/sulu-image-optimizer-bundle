<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Tests\Unit;

use Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Sulu\Controller\ImageOptimizationBatchDeleteController;
use Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Sulu\Controller\ImageOptimizationDeleteController;
use Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Sulu\Controller\ImageOptimizationListController;
use Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Sulu\Controller\ImageOptimizationStatisticsController;
use Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Symfony\DependencyInjection\EekesSuluImageOptimizerExtension;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * The admin controllers extend Symfony's AbstractController, which is useless until its
 * #[Required] setContainer() has been called with the locator of the services it subscribes to.
 *
 * Autoconfiguration normally arranges that, and these controllers deliberately do without it - see
 * config/services_sulu_admin.php. Without the two replacements everything else keeps working: the
 * services are built, the routes registered, and only a real request fails. So the assertion is on
 * the compiled definition: what matters is that the call is there, whichever way it got there.
 */
#[CoversNothing]
final class AdminControllerWiringTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string}>
     */
    public static function controllers(): iterable
    {
        yield 'list' => [ImageOptimizationListController::class];
        yield 'delete' => [ImageOptimizationDeleteController::class];
        yield 'batch delete' => [ImageOptimizationBatchDeleteController::class];
        yield 'statistics' => [ImageOptimizationStatisticsController::class];
    }

    /**
     * @param class-string $controller
     */
    #[DataProvider('controllers')]
    public function testEveryControllerIsHandedTheContainerItNeedsToRun(string $controller): void
    {
        $container = $this->compile();

        $arguments = null;

        foreach ($container->getDefinition($controller)->getMethodCalls() as $call) {
            self::assertIsArray($call);

            if (($call[0] ?? null) !== 'setContainer') {
                continue;
            }

            self::assertIsArray($call[1] ?? null);
            $arguments = $call[1];
        }

        self::assertNotNull($arguments, \sprintf('%s is never handed a container, so every request to it fails.', $controller));

        $locator = $arguments[0] ?? null;
        self::assertInstanceOf(Definition::class, $locator);
        self::assertSame(ServiceLocator::class, $locator->getClass());
    }

    private function compile(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.bundles', ['SuluAdminBundle' => 'Sulu\Bundle\AdminBundle\SuluAdminBundle']);

        // The collaborators the extension refers to. Their classes are irrelevant here: the test is
        // about how the controllers are built, not about what they are given.
        foreach ([
            'doctrine',
            'logger',
            'router',
            'sulu_admin.view_builder_factory',
            'sulu_security.security_checker',
            'sulu_core.doctrine_rest_helper',
            'sulu_core.list_builder.field_descriptor_factory',
            'sulu_core.doctrine_list_builder_factory',
        ] as $id) {
            $container->setDefinition($id, (new Definition(\stdClass::class))->setPublic(true));
        }

        (new EekesSuluImageOptimizerExtension())->load([[]], $container);

        foreach (self::controllers() as [$controller]) {
            $container->getDefinition($controller)->setPublic(true);
        }

        $container->compile();

        return $container;
    }
}

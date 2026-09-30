<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Tests\Functional;

use Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Sulu\Controller\ImageOptimizationBatchDeleteController;
use Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Sulu\Controller\ImageOptimizationDeleteController;
use Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Sulu\Controller\ImageOptimizationListController;
use Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Sulu\Controller\ImageOptimizationStatisticsController;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * The routing file a project imports points at a directory of controllers, and the route names it
 * produces are what the container extension hands to Sulu. Nothing else in the suite touches
 * either, so a rename or a moved folder would otherwise only show up as an empty list in a real
 * administration interface.
 */
#[CoversNothing]
final class AdminRoutingTest extends FunctionalTestCase
{
    public function testTheListIsWhereSuluIsToldItIs(): void
    {
        $route = $this->route(ImageOptimizationListController::ROUTE);

        self::assertSame('/admin/api/image-optimizations', $route->getPath());
        self::assertSame(['GET'], $route->getMethods());
    }

    public function testAnEntryAndASelectionCanBeDeleted(): void
    {
        self::assertSame('/admin/api/image-optimizations/{id}', $this->route(ImageOptimizationDeleteController::ROUTE)->getPath());
        self::assertSame(['DELETE'], $this->route(ImageOptimizationDeleteController::ROUTE)->getMethods());
        self::assertSame('/admin/api/image-optimizations', $this->route(ImageOptimizationBatchDeleteController::ROUTE)->getPath());
        self::assertSame(['DELETE'], $this->route(ImageOptimizationBatchDeleteController::ROUTE)->getMethods());
    }

    /**
     * The statistics live under the same collection path as the entries, so they must never be
     * mistaken for the entry with the id "statistics".
     */
    public function testTheStatisticsDoNotCollideWithAnEntry(): void
    {
        $router = $this->router();
        $context = $router->getContext();
        $context->setMethod('GET');

        self::assertSame('/admin/api/image-optimizations/statistics', $this->route(ImageOptimizationStatisticsController::ROUTE)->getPath());
        self::assertSame(ImageOptimizationStatisticsController::ROUTE, $router->match('/admin/api/image-optimizations/statistics')['_route'] ?? null);
    }

    /**
     * An entry is a log of what happened, so there is deliberately nothing to create or change one.
     */
    public function testThereIsNoRouteThatWritesToTheLog(): void
    {
        foreach ($this->router()->getRouteCollection() as $route) {
            self::assertSame([], array_intersect(['POST', 'PUT', 'PATCH'], $route->getMethods()));
        }
    }

    private function route(string $name): Route
    {
        $route = $this->router()->getRouteCollection()->get($name);

        self::assertInstanceOf(Route::class, $route, \sprintf('The route "%s" was not registered.', $name));

        return $route;
    }

    private function router(): RouterInterface
    {
        $router = self::getContainer()->get('eekes_test.router');
        \assert($router instanceof RouterInterface);

        return $router;
    }
}

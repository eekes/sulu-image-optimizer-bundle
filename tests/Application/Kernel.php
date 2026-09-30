<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Tests\Application;

use Composer\InstalledVersions;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Eekes\Sulu\ImageOptimizerBundle\EekesSuluImageOptimizerBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * The smallest application this bundle can live in: no Sulu bundles at all.
 *
 * That is deliberate. Booting a full Sulu application needs PHPCR, webspaces and a content
 * repository, which would make the suite slow and brittle for what it actually tests. The log's
 * relation to Sulu's MediaInterface is resolved to a small entity of the test suite instead, and
 * the classes that talk to Sulu are built by hand in the tests.
 */
class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * @return iterable<BundleInterface>
     */
    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new MonologBundle();
        yield new DoctrineBundle();
        yield new EekesSuluImageOptimizerBundle();
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return __DIR__.'/var/cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return __DIR__.'/var/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->import(__DIR__.'/config/config.yaml');

        // DoctrineBundle 3 dropped these options, 2 still needs them: without the first there are no
        // proxies for the relation to the media, without the second every boot is a deprecation.
        if (version_compare(InstalledVersions::getVersion('doctrine/doctrine-bundle') ?? '0', '3.0.0', '<')) {
            $container->extension('doctrine', [
                'orm' => [
                    'auto_generate_proxy_classes' => true,
                    'report_fields_where_declared' => true,
                ],
            ]);
        }
    }

    /**
     * The bundle's own routing file, imported exactly the way a project imports it, so a broken
     * resource path in it fails a test instead of only failing in a real Sulu admin.
     */
    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(\dirname(__DIR__, 2).'/config/routing_admin_api.php')->prefix('/admin/api');
    }
}

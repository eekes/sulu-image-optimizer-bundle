<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Symfony\DependencyInjection;

use Eekes\Sulu\ImageOptimizerBundle\Domain\Entity\ImageOptimization;
use Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Sulu\Admin\ImageOptimizationAdmin;
use Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Sulu\Controller\ImageOptimizationDeleteController;
use Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Sulu\Controller\ImageOptimizationListController;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

/**
 * Loads only the services a project can actually use: the listeners and the command that work on
 * the media library need SuluMediaBundle, the log in the administration interface needs
 * SuluAdminBundle. Without either (as in the light test kernel) the optimizer itself still boots.
 */
final class EekesSuluImageOptimizerExtension extends Extension implements PrependExtensionInterface
{
    private const string PARAMETER_PREFIX = Configuration::ROOT_NODE.'.';

    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->config($configs);

        $container->setParameter(self::PARAMETER_PREFIX.'table_name', $config->tableName);
        $container->setParameter(self::PARAMETER_PREFIX.'resize_enabled', $config->resizeEnabled);
        $container->setParameter(self::PARAMETER_PREFIX.'max_size', $config->maxSize);
        $container->setParameter(self::PARAMETER_PREFIX.'quality', $config->quality);
        $container->setParameter(self::PARAMETER_PREFIX.'strip_metadata', $config->stripMetadata);
        $container->setParameter(self::PARAMETER_PREFIX.'sanitize_svg', $config->sanitizeSvg);
        $container->setParameter(self::PARAMETER_PREFIX.'ignore_types', $config->ignoreTypes);
        $container->setParameter(self::PARAMETER_PREFIX.'binary_path', $config->binaryPath);
        $container->setParameter(self::PARAMETER_PREFIX.'timeout', $config->timeout);
        $container->setParameter(self::PARAMETER_PREFIX.'navigation_position', $config->navigationPosition);

        $loader = new PhpFileLoader($container, new FileLocator(self::bundleRoot().'/config'));
        $loader->load('services.php');

        if (self::hasBundle($container, 'SuluMediaBundle')) {
            $loader->load('services_sulu_media.php');

            if ($config->enabled) {
                $loader->load('services_sulu_media_upload.php');
            }
        }

        if (self::hasBundle($container, 'SuluAdminBundle') && $config->adminEnabled) {
            $loader->load('services_sulu_admin.php');
        }
    }

    /**
     * Whether a project registered a bundle.
     *
     * Deliberately not hasExtension(): load() is handed a throwaway container that holds the
     * parameters but none of the extensions, so asking it about sulu_media would always answer no
     * and silently leave the upload listener unregistered - an optimizer that installs cleanly and
     * never runs.
     */
    private static function hasBundle(ContainerBuilder $container, string $bundleName): bool
    {
        $bundles = $container->getParameter('kernel.bundles');

        return \is_array($bundles) && \array_key_exists($bundleName, $bundles);
    }

    public function prepend(ContainerBuilder $container): void
    {
        // prepend() runs before load(), so the configuration has to be resolved by hand here.
        $config = $this->config($container->getExtensionConfig($this->getAlias()));

        if ($container->hasExtension('doctrine')) {
            $container->prependExtensionConfig('doctrine', [
                'orm' => [
                    'mappings' => [
                        'EekesImageOptimizer' => [
                            'type' => 'attribute',
                            'is_bundle' => false,
                            'dir' => self::bundleRoot().'/src/Domain/Entity',
                            'prefix' => 'Eekes\Sulu\ImageOptimizerBundle\Domain\Entity',
                            'alias' => 'EekesImageOptimizer',
                        ],
                    ],
                ],
            ]);
        }

        // Declaring the channel is what makes the monolog.logger tag in services.php resolve.
        if ($container->hasExtension('monolog')) {
            $container->prependExtensionConfig('monolog', [
                'channels' => ['image_optimizer'],
            ]);
        }

        if ($container->hasExtension('sulu_admin') && $config->adminEnabled) {
            $container->prependExtensionConfig('sulu_admin', [
                'lists' => [
                    'directories' => [self::bundleRoot().'/config/lists'],
                ],
                'resources' => [
                    ImageOptimization::SULU_RESOURCE_KEY => [
                        'routes' => [
                            'list' => ImageOptimizationListController::ROUTE,
                            // There is nothing to GET: an entry is only ever deleted by its URL.
                            'detail' => ImageOptimizationDeleteController::ROUTE,
                        ],
                    ],
                    // Only a view, no endpoint: it exists so a row of the log can open its media.
                    ImageOptimizationAdmin::MEDIA_LINK_RESOURCE_KEY => [
                        'views' => [
                            'detail' => ImageOptimizationAdmin::MEDIA_DETAIL_VIEW,
                        ],
                    ],
                ],
            ]);
        }
    }

    /**
     * The root of the bundle, where config/ and src/ live next to each other.
     */
    private static function bundleRoot(): string
    {
        return \dirname(__DIR__, 4);
    }

    /**
     * @param array<array<mixed>> $configs
     */
    private function config(array $configs): ImageOptimizerConfig
    {
        return ImageOptimizerConfig::fromArray($this->processConfiguration(new Configuration(), $configs));
    }

    public function getAlias(): string
    {
        return Configuration::ROOT_NODE;
    }
}

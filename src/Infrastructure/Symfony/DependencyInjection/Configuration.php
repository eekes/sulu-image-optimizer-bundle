<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Symfony\DependencyInjection;

use Eekes\Sulu\ImageOptimizerBundle\Domain\Entity\ImageOptimization;
use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\ImageFormat;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Everything a project might reasonably want to change. The defaults are what a Sulu site on
 * ordinary hosting needs, so installing the bundle is enough.
 */
final class Configuration implements ConfigurationInterface
{
    public const string ROOT_NODE = 'eekes_sulu_image_optimizer';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::ROOT_NODE);

        $treeBuilder->getRootNode()
            ->children()
                ->booleanNode('enabled')
                    ->defaultTrue()
                    ->info('Optimizes files uploaded to the media library. The console commands keep working when this is off.')
                ->end()
                ->scalarNode('table_name')
                    ->defaultValue(ImageOptimization::DEFAULT_TABLE_NAME)
                    ->cannotBeEmpty()
                    ->info('Table the log is stored in. Changing it after the first migration needs a rename migration.')
                ->end()
                ->arrayNode('resize')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultTrue()
                        ->end()
                        ->integerNode('max_size')
                            ->defaultValue(4000)
                            ->min(1)
                            ->info('Maximum width or height in pixels. A larger image is scaled down, keeping its aspect ratio.')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('quality')
                    ->addDefaultsIfNotSet()
                    ->info('Quality (1-100) the lossy formats are written with. For PNG it is the upper quality bound pngquant may use.')
                    ->children()
                        ->integerNode('jpeg')->defaultValue(85)->min(1)->max(100)->end()
                        ->integerNode('png')->defaultValue(85)->min(1)->max(100)->end()
                        ->integerNode('webp')->defaultValue(80)->min(1)->max(100)->end()
                        ->integerNode('avif')->defaultValue(60)->min(1)->max(100)->end()
                    ->end()
                ->end()
                ->booleanNode('strip_metadata')
                    ->defaultTrue()
                    ->info('Removes EXIF data, GPS coordinates included, after applying its rotation. Off keeps it, but then a JPEG, WebP or AVIF is only re-encoded when it has to be resized.')
                ->end()
                ->booleanNode('sanitize_svg')
                    ->defaultTrue()
                    ->info('Removes scripts, event handlers and remote references from uploaded SVG files.')
                ->end()
                ->arrayNode('ignore_types')
                    ->scalarPrototype()->cannotBeEmpty()->end()
                    ->defaultValue([])
                    ->info('Formats to leave alone, e.g. [gif, webp]. The format is detected from the content of a file, not its name.')
                    ->validate()
                        ->ifTrue(static fn (array $types): bool => array_filter($types, static fn (mixed $type): bool => !\is_string($type) || ImageFormat::fromName($type) === null) !== [])
                        ->thenInvalid('ignore_types only knows '.implode(', ', array_map(static fn (ImageFormat $format): string => $format->value, ImageFormat::cases())).' (and "jpeg"), %s given.')
                    ->end()
                ->end()
                ->scalarNode('binary_path')
                    ->defaultNull()
                    ->info('Directory holding jpegoptim, pngquant, ... when they are not on the PATH of PHP, e.g. "%kernel.project_dir%/bin/optimizers".')
                ->end()
                ->integerNode('timeout')
                    ->defaultValue(60)
                    ->min(1)
                    ->info('Seconds one optimizer binary may run before it is stopped.')
                ->end()
                ->arrayNode('admin')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultTrue()
                            ->info('Registers the log under Settings in the Sulu admin.')
                        ->end()
                        ->integerNode('navigation_position')
                            ->defaultValue(45)
                            ->info('Position of the navigation item within Settings.')
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}

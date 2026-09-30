<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Tests\Unit;

use Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Symfony\DependencyInjection\Configuration;
use Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Symfony\DependencyInjection\ImageOptimizerConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

#[CoversClass(Configuration::class)]
#[CoversClass(ImageOptimizerConfig::class)]
final class ConfigurationTest extends TestCase
{
    public function testInstallingTheBundleIsEnough(): void
    {
        $config = $this->process([]);

        self::assertTrue($config->enabled);
        self::assertSame('eekes_image_optimization', $config->tableName);
        self::assertSame(4000, $config->maxSize);
        self::assertSame([], $config->ignoreTypes);
        self::assertTrue($config->stripMetadata);
        self::assertTrue($config->sanitizeSvg);
    }

    /**
     * The previous bundle accepted "jpeg" and "jpg" for the same format, so a configuration copied
     * over from it keeps meaning what it meant.
     */
    public function testIgnoreTypesAreNormalised(): void
    {
        self::assertSame(['jpg', 'gif'], $this->process(['ignore_types' => ['jpeg', 'JPG', 'GIF']])->ignoreTypes);
    }

    /**
     * A typo would otherwise be silently ignored, and the format it meant optimized anyway.
     */
    public function testAnUnknownFormatInIgnoreTypesIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process(['ignore_types' => ['gifs']]);
    }

    public function testAQualityOutsideOneToAHundredIsRefused(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->process(['quality' => ['jpeg' => 0]]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function process(array $config): ImageOptimizerConfig
    {
        return ImageOptimizerConfig::fromArray((new Processor())->processConfiguration(new Configuration(), [$config]));
    }
}

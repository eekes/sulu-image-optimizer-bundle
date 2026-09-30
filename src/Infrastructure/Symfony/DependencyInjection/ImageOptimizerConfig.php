<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Symfony\DependencyInjection;

use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\ImageFormat;

/**
 * The processed configuration as typed values.
 *
 * The configuration component hands back a plain array of mixed, which the extension would
 * otherwise have to narrow at every use.
 */
final readonly class ImageOptimizerConfig
{
    /**
     * @param list<string>               $ignoreTypes the normalised ImageFormat values
     * @param array<string, int<1, 100>> $quality     per ImageFormat value
     */
    public function __construct(
        public bool $enabled,
        public string $tableName,
        public bool $resizeEnabled,
        public int $maxSize,
        public array $quality,
        public bool $stripMetadata,
        public bool $sanitizeSvg,
        public array $ignoreTypes,
        public ?string $binaryPath,
        public int $timeout,
        public bool $adminEnabled,
        public int $navigationPosition,
    ) {
    }

    /**
     * @param array<mixed> $config the configuration as the processor returned it
     */
    public static function fromArray(array $config): self
    {
        $resize = self::readArray($config, 'resize');
        $quality = self::readArray($config, 'quality');
        $admin = self::readArray($config, 'admin');
        $binaryPath = $config['binary_path'] ?? null;

        $ignoreTypes = [];

        foreach (self::readArray($config, 'ignore_types') as $type) {
            $format = \is_string($type) ? ImageFormat::fromName($type) : null;

            if ($format === null) {
                throw new \LogicException(\sprintf('Unknown format "%s" in ignore_types.', get_debug_type($type)));
            }

            $ignoreTypes[] = $format->value;
        }

        return new self(
            enabled: self::readBool($config, 'enabled'),
            tableName: self::readString($config, 'table_name'),
            resizeEnabled: self::readBool($resize, 'enabled'),
            maxSize: self::readInt($resize, 'max_size'),
            quality: [
                ImageFormat::Jpeg->value => self::readQuality($quality, 'jpeg'),
                ImageFormat::Png->value => self::readQuality($quality, 'png'),
                ImageFormat::Webp->value => self::readQuality($quality, 'webp'),
                ImageFormat::Avif->value => self::readQuality($quality, 'avif'),
            ],
            stripMetadata: self::readBool($config, 'strip_metadata'),
            sanitizeSvg: self::readBool($config, 'sanitize_svg'),
            ignoreTypes: array_values(array_unique($ignoreTypes)),
            binaryPath: \is_string($binaryPath) && $binaryPath !== '' ? $binaryPath : null,
            timeout: self::readInt($config, 'timeout'),
            adminEnabled: self::readBool($admin, 'enabled'),
            navigationPosition: self::readInt($admin, 'navigation_position'),
        );
    }

    /**
     * @param array<mixed> $config
     *
     * @return array<mixed>
     */
    private static function readArray(array $config, string $key): array
    {
        $value = $config[$key] ?? null;

        if (!\is_array($value)) {
            throw new \LogicException(\sprintf('Expected "%s" to be an array.', $key));
        }

        return $value;
    }

    /**
     * @param array<mixed> $config
     */
    private static function readString(array $config, string $key): string
    {
        $value = $config[$key] ?? null;

        if (!\is_string($value)) {
            throw new \LogicException(\sprintf('Expected "%s" to be a string.', $key));
        }

        return $value;
    }

    /**
     * @param array<mixed> $config
     */
    private static function readInt(array $config, string $key): int
    {
        $value = $config[$key] ?? null;

        if (!\is_int($value)) {
            throw new \LogicException(\sprintf('Expected "%s" to be an integer.', $key));
        }

        return $value;
    }

    /**
     * @param array<mixed> $config
     *
     * @return int<1, 100>
     */
    private static function readQuality(array $config, string $key): int
    {
        $value = self::readInt($config, $key);

        if ($value < 1 || $value > 100) {
            throw new \LogicException(\sprintf('Expected the quality of "%s" to be between 1 and 100.', $key));
        }

        return $value;
    }

    /**
     * @param array<mixed> $config
     */
    private static function readBool(array $config, string $key): bool
    {
        $value = $config[$key] ?? null;

        if (!\is_bool($value)) {
            throw new \LogicException(\sprintf('Expected "%s" to be a boolean.', $key));
        }

        return $value;
    }
}

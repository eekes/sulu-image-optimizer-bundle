<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Application\Service;

use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\ImageFormat;

/**
 * What the optimizer is allowed to do, read once from the bundle configuration.
 */
final readonly class OptimizerSettings
{
    /**
     * @param list<ImageFormat>          $ignoredFormats
     * @param array<string, int<1, 100>> $quality        per ImageFormat value
     */
    public function __construct(
        public bool $resizeEnabled = true,
        public int $maxSize = 4000,
        public array $ignoredFormats = [],
        public array $quality = [],
        public bool $stripMetadata = true,
        public bool $sanitizeSvg = true,
    ) {
    }

    /**
     * Builds the settings from the scalar container parameters, which cannot hold the enums.
     *
     * @param list<string>               $ignoreTypes ImageFormat values
     * @param array<string, int<1, 100>> $quality
     */
    public static function fromConfiguration(
        bool $resizeEnabled,
        int $maxSize,
        array $ignoreTypes,
        array $quality,
        bool $stripMetadata,
        bool $sanitizeSvg,
    ): self {
        return new self(
            $resizeEnabled,
            $maxSize,
            array_map(static fn (string $type): ImageFormat => ImageFormat::from($type), $ignoreTypes),
            $quality,
            $stripMetadata,
            $sanitizeSvg,
        );
    }

    /**
     * @return int<1, 100>
     */
    public function quality(ImageFormat $format): int
    {
        return $this->quality[$format->value] ?? 85;
    }

    public function ignores(ImageFormat $format): bool
    {
        return \in_array($format, $this->ignoredFormats, true);
    }
}

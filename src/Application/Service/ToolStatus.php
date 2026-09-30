<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Application\Service;

use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\ImageFormat;

/**
 * One thing the optimizer relies on - a binary, or GD's support for a format - and whether this
 * server has it.
 */
final readonly class ToolStatus
{
    /**
     * @param list<ImageFormat> $formats
     */
    public function __construct(
        public string $name,
        public array $formats,
        public bool $available,
        public ?string $path = null,
    ) {
    }

    /**
     * @return array{name: string, formats: list<string>, available: bool, path: null|string}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'formats' => array_map(static fn (ImageFormat $format): string => $format->value, $this->formats),
            'available' => $this->available,
            'path' => $this->path,
        ];
    }
}

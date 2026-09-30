<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Application\Service;

use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\Dimensions;

/**
 * Tells beforehand whether GD can hold an image in memory.
 *
 * Running out of memory in GD is a fatal error, not an exception: nothing can catch it, and the
 * upload dies with a blank 500 instead of simply storing the original. That is the typical way a
 * 40 megapixel phone photo fails on shared hosting, so the check happens before GD is touched.
 */
final readonly class MemoryBudget
{
    /**
     * Bytes GD needs per pixel of a true colour image, plus the overhead measured on real uploads.
     */
    private const float BYTES_PER_PIXEL = 4 * 1.8;

    /**
     * @param null|int $limit the memory limit in bytes, null for the one PHP runs with, -1 for none
     */
    public function __construct(
        private ?int $limit = null,
    ) {
    }

    public function allows(Dimensions $source, ?Dimensions $target = null): bool
    {
        $limit = $this->limit ?? self::phpMemoryLimit();

        if ($limit < 0) {
            return true;
        }

        $needed = self::pixelBytes($source) + ($target === null ? 0 : self::pixelBytes($target));

        return memory_get_usage() + $needed < $limit;
    }

    private static function pixelBytes(Dimensions $dimensions): int
    {
        return (int) ceil($dimensions->width * $dimensions->height * self::BYTES_PER_PIXEL);
    }

    private static function phpMemoryLimit(): int
    {
        $value = trim((string) \ini_get('memory_limit'));

        if ($value === '' || $value === '-1') {
            return -1;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}

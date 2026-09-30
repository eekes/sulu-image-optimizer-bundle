<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject;

/**
 * Totals over the whole log, for the summary above the list in the administration interface.
 */
final readonly class OptimizationStatistics
{
    /**
     * @param array<string, int> $countPerStatus the number of entries per OptimizationStatus value, every status present
     */
    public function __construct(
        public int $count,
        public int $originalSize,
        public int $finalSize,
        public array $countPerStatus,
    ) {
    }

    public function savedBytes(): int
    {
        return $this->originalSize - $this->finalSize;
    }

    public function savedPercentage(): float
    {
        if ($this->originalSize === 0) {
            return 0.0;
        }

        return round($this->savedBytes() / $this->originalSize * 100, 1);
    }

    /**
     * @return array{count: int, originalSize: int, finalSize: int, savedBytes: int, savedPercentage: float, countPerStatus: array<string, int>}
     */
    public function toArray(): array
    {
        return [
            'count' => $this->count,
            'originalSize' => $this->originalSize,
            'finalSize' => $this->finalSize,
            'savedBytes' => $this->savedBytes(),
            'savedPercentage' => $this->savedPercentage(),
            'countPerStatus' => $this->countPerStatus,
        ];
    }
}

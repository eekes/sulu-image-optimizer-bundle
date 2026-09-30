<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject;

/**
 * The outcome of optimizing one file: what it was, what it became, and how.
 *
 * The final size is the size of the file that is actually stored, so for every status except
 * Optimized it equals the original size. That keeps totals honest: an image that could not be made
 * smaller counts as zero bytes saved rather than as the larger result that was thrown away.
 */
final readonly class OptimizationResult
{
    /**
     * @param list<string> $tools what processed the file, in the order it ran
     */
    private function __construct(
        public OptimizationStatus $status,
        public ImageFormat $format,
        public int $originalSize,
        public int $finalSize,
        public ?Dimensions $originalDimensions,
        public ?Dimensions $finalDimensions,
        public array $tools,
        public ?string $message,
        public int $durationMs,
    ) {
    }

    /**
     * @param list<string> $tools
     */
    public static function optimized(
        ImageFormat $format,
        int $originalSize,
        int $finalSize,
        ?Dimensions $originalDimensions,
        ?Dimensions $finalDimensions,
        array $tools,
        int $durationMs,
        ?string $message = null,
    ): self {
        return new self(OptimizationStatus::Optimized, $format, $originalSize, $finalSize, $originalDimensions, $finalDimensions, $tools, $message, $durationMs);
    }

    /**
     * @param list<string> $tools
     */
    public static function unchanged(
        ImageFormat $format,
        int $size,
        ?Dimensions $dimensions,
        array $tools,
        int $durationMs,
        ?string $message = null,
    ): self {
        return new self(OptimizationStatus::Unchanged, $format, $size, $size, $dimensions, $dimensions, $tools, $message, $durationMs);
    }

    public static function skipped(ImageFormat $format, int $size, ?Dimensions $dimensions, string $reason): self
    {
        return new self(OptimizationStatus::Skipped, $format, $size, $size, $dimensions, $dimensions, [], $reason, 0);
    }

    /**
     * @param list<string> $tools
     */
    public static function failed(
        ImageFormat $format,
        int $size,
        ?Dimensions $dimensions,
        string $message,
        array $tools,
        int $durationMs,
    ): self {
        return new self(OptimizationStatus::Failed, $format, $size, $size, $dimensions, $dimensions, $tools, $message, $durationMs);
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
     * Whether the stored file differs from the upload. Only then does a new file have to be written
     * anywhere.
     */
    public function changedTheFile(): bool
    {
        return $this->status === OptimizationStatus::Optimized;
    }
}

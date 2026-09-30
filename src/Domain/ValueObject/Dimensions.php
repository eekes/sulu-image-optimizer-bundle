<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject;

final readonly class Dimensions
{
    public function __construct(
        public int $width,
        public int $height,
    ) {
        if ($width < 1 || $height < 1) {
            throw new \InvalidArgumentException(\sprintf('An image cannot be %dx%d pixels.', $width, $height));
        }
    }

    public function longestSide(): int
    {
        return max($this->width, $this->height);
    }

    /**
     * The dimensions after scaling down so the longest side is at most $maxSize, keeping the aspect
     * ratio. A square image counts as well: its width and height are both the longest side.
     */
    public function fitWithin(int $maxSize): self
    {
        if ($this->longestSide() <= $maxSize) {
            return $this;
        }

        if ($this->width >= $this->height) {
            return new self($maxSize, max(1, (int) round($this->height * $maxSize / $this->width)));
        }

        return new self(max(1, (int) round($this->width * $maxSize / $this->height)), $maxSize);
    }

    public function equals(self $other): bool
    {
        return $this->width === $other->width && $this->height === $other->height;
    }
}

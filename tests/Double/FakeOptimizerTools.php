<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Tests\Double;

use Eekes\Sulu\ImageOptimizerBundle\Application\Service\OptimizerToolsInterface;
use Eekes\Sulu\ImageOptimizerBundle\Application\Service\ToolStatus;
use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\ImageFormat;

/**
 * The optimizer binaries, without the binaries: each call can shrink or grow the file by a set
 * number of bytes, so a test decides whether "the tools" gained anything.
 */
final class FakeOptimizerTools implements OptimizerToolsInterface
{
    /**
     * @var list<array{string, ImageFormat}>
     */
    public array $calls = [];

    /**
     * Bytes to cut off the end of the file (positive) or append to it (negative).
     */
    public int $shrinkBy = 0;

    public ?\Throwable $failWith = null;

    /**
     * @var list<ToolStatus>
     */
    public array $statuses = [];

    public function optimize(string $path, ImageFormat $format): array
    {
        $this->calls[] = [$path, $format];

        if ($this->failWith !== null) {
            throw $this->failWith;
        }

        if ($this->shrinkBy === 0) {
            return [];
        }

        $contents = (string) file_get_contents($path);

        $changed = $this->shrinkBy > 0
            ? substr($contents, 0, max(0, \strlen($contents) - $this->shrinkBy))
            : $contents.str_repeat("\0", -$this->shrinkBy);

        file_put_contents($path, $changed);
        clearstatcache(true, $path);

        return ['fake-tool'];
    }

    public function status(): array
    {
        return $this->statuses;
    }
}

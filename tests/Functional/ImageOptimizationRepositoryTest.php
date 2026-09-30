<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Tests\Functional;

use Eekes\Sulu\ImageOptimizerBundle\Domain\Entity\ImageOptimization;
use Eekes\Sulu\ImageOptimizerBundle\Domain\Repository\ImageOptimizationRepository;
use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\ImageFormat;
use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\OptimizationResult;
use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\OptimizationSource;
use Eekes\Sulu\ImageOptimizerBundle\Tests\Double\Media;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ImageOptimizationRepository::class)]
final class ImageOptimizationRepositoryTest extends FunctionalTestCase
{
    public function testTheTotalsOnlyCountWhatWasActuallySaved(): void
    {
        $this->entry(OptimizationResult::optimized(ImageFormat::Jpeg, 1000, 400, null, null, ['gd'], 5));
        $this->entry(OptimizationResult::optimized(ImageFormat::Png, 500, 300, null, null, [], 5));
        $this->entry(OptimizationResult::unchanged(ImageFormat::Png, 800, null, [], 5));
        $this->entry(OptimizationResult::failed(ImageFormat::Jpeg, 200, null, 'Broken', [], 5));

        $statistics = $this->repository()->statistics();

        self::assertSame(4, $statistics->count);
        self::assertSame(2500, $statistics->originalSize);
        self::assertSame(1700, $statistics->finalSize);
        self::assertSame(800, $statistics->savedBytes());
        self::assertSame(32.0, $statistics->savedPercentage());
        self::assertSame(['optimized' => 2, 'unchanged' => 1, 'skipped' => 0, 'failed' => 1], $statistics->countPerStatus);
    }

    public function testAnEmptyLogHasTotalsOfZero(): void
    {
        $statistics = $this->repository()->statistics();

        self::assertSame(0, $statistics->count);
        self::assertSame(0.0, $statistics->savedPercentage());
    }

    /**
     * A failed attempt is not "handled": whatever made it fail may be fixed by the next run.
     */
    public function testOnlyAVersionThatDidNotFailCountsAsHandled(): void
    {
        $handled = $this->media();
        $failed = $this->media();
        $this->entry(OptimizationResult::unchanged(ImageFormat::Png, 800, null, [], 5), $handled, 1);
        $this->entry(OptimizationResult::failed(ImageFormat::Jpeg, 200, null, 'Broken', [], 5), $failed, 1);

        self::assertTrue($this->repository()->hasHandled($handled->getId(), 1));
        self::assertFalse($this->repository()->hasHandled($handled->getId(), 2), 'A new version is a new file.');
        self::assertFalse($this->repository()->hasHandled($failed->getId(), 1));
    }

    public function testASelectionIsDeletedAndTheRestIsLeftAlone(): void
    {
        $doomed = $this->entry(OptimizationResult::unchanged(ImageFormat::Png, 800, null, [], 5));
        $kept = $this->entry(OptimizationResult::unchanged(ImageFormat::Png, 800, null, [], 5));

        self::assertSame(1, $this->repository()->deleteByIds([$doomed->id, $kept->id + 999]));
        self::assertSame(0, $this->repository()->deleteByIds([]));
        self::assertSame([$kept->id], array_map(static fn (ImageOptimization $entry): int => $entry->id, $this->logged()));
    }

    private function entry(OptimizationResult $result, ?Media $media = null, ?int $version = null): ImageOptimization
    {
        $entry = new ImageOptimization($result, 'file', OptimizationSource::Upload);
        $entry->media = $media;
        $entry->mediaVersion = $version;
        $this->repository()->save($entry);

        return $entry;
    }
}

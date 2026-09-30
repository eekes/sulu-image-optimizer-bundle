<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Tests\Functional;

use Eekes\Sulu\ImageOptimizerBundle\Application\EventListener\MediaStoredListener;
use Eekes\Sulu\ImageOptimizerBundle\Application\Service\ExistingMediaOptimizer;
use Eekes\Sulu\ImageOptimizerBundle\Application\Service\ExistingMediaOutcome;
use Eekes\Sulu\ImageOptimizerBundle\Application\Service\ImageOptimizer;
use Eekes\Sulu\ImageOptimizerBundle\Application\Service\StoredImage;
use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\OptimizationResult;
use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\OptimizationSource;
use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\OptimizationStatus;
use Eekes\Sulu\ImageOptimizerBundle\Tests\Double\FakeMediaLibrary;
use Eekes\Sulu\ImageOptimizerBundle\Tests\Double\Images;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\NullLogger;

#[CoversClass(ExistingMediaOptimizer::class)]
final class ExistingMediaOptimizerTest extends FunctionalTestCase
{
    private FakeMediaLibrary $library;

    protected function setUp(): void
    {
        parent::setUp();

        $this->library = new FakeMediaLibrary(new MediaStoredListener($this->recorder()));
    }

    public function testAnImageThatGetsSmallerIsStoredAsANewVersionOfItsMedia(): void
    {
        $media = $this->media();
        $this->library->add($media, Images::jpeg(quality: 100));

        $summary = $this->optimizer()->run(static function (): void {});

        [$entry] = $this->logged();

        self::assertSame(1, $summary->optimized);
        self::assertGreaterThan(0, $summary->savedBytes);
        self::assertCount(1, $this->library->addedVersions);
        self::assertSame(OptimizationSource::Command, $entry->source());
        self::assertSame($media->getId(), $entry->media?->getId());
        self::assertSame(2, $entry->mediaVersion, 'The entry is about the version the optimized file became.');
    }

    /**
     * An image that is already as small as it gets is logged all the same: that entry is what lets
     * the next run skip it instead of trying again.
     */
    public function testAnImageThatCannotBeImprovedIsOnlyLogged(): void
    {
        $media = $this->media();
        $this->library->add($media, Images::png(20, 20));

        $this->optimizer()->run(static function (): void {});

        [$entry] = $this->logged();

        self::assertSame([], $this->library->addedVersions);
        self::assertSame(OptimizationStatus::Unchanged, $entry->status());
        self::assertSame(1, $entry->mediaVersion);
    }

    public function testRunningItAgainSkipsWhatIsAlreadyDone(): void
    {
        $this->library->add($this->media(), Images::jpeg(quality: 100));
        $this->library->add($this->media(), Images::png(20, 20));

        $this->optimizer()->run(static function (): void {});
        $second = $this->optimizer()->run(static function (): void {});

        self::assertSame(0, $second->processed);
        self::assertSame(2, $second->alreadyHandled);
        self::assertCount(1, $this->library->addedVersions);
        self::assertCount(2, $this->logged());
    }

    public function testADryRunStoresAndLogsNothing(): void
    {
        $this->library->add($this->media(), Images::jpeg(quality: 100));

        /** @var list<?OptimizationResult> $reported */
        $reported = [];
        $summary = $this->optimizer()->run(
            static function (StoredImage $image, ExistingMediaOutcome $outcome, ?OptimizationResult $result) use (&$reported): void {
                $reported[] = $result;
            },
            dryRun: true,
        );

        self::assertSame(1, $summary->optimized);
        self::assertSame(OptimizationStatus::Optimized, $reported[0]?->status);
        self::assertSame([], $this->library->addedVersions);
        self::assertSame([], $this->logged());
    }

    public function testTheLimitCountsTheImagesThatWereProcessed(): void
    {
        $this->library->add($this->media(), Images::jpeg(quality: 100));
        $this->library->add($this->media(), Images::jpeg(quality: 100));
        $this->library->add($this->media(), Images::jpeg(quality: 100));

        $summary = $this->optimizer()->run(static function (): void {}, limit: 2);

        self::assertSame(2, $summary->processed);
        self::assertCount(2, $this->logged());
    }

    /**
     * Sulu serves an SVG from the site's own domain, so an old upload with a script in it is exactly
     * as dangerous as a new one.
     */
    public function testAnOldSvgWithAScriptInItIsCleanedToo(): void
    {
        $this->library->add($this->media(), Images::svg('<script>alert(1)</script>'));

        $this->optimizer()->run(static function (): void {});

        self::assertCount(1, $this->library->addedVersions);
        self::assertStringNotContainsString('script', $this->library->addedVersions[0][2]);
    }

    private function optimizer(): ExistingMediaOptimizer
    {
        $imageOptimizer = self::getContainer()->get('eekes_test.image_optimizer');
        \assert($imageOptimizer instanceof ImageOptimizer);

        return new ExistingMediaOptimizer($imageOptimizer, $this->recorder(), $this->library, $this->repository(), new NullLogger());
    }
}

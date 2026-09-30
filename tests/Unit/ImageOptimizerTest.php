<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Tests\Unit;

use Eekes\Sulu\ImageOptimizerBundle\Application\Service\ImageOptimizer;
use Eekes\Sulu\ImageOptimizerBundle\Application\Service\MemoryBudget;
use Eekes\Sulu\ImageOptimizerBundle\Application\Service\OptimizerSettings;
use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\Dimensions;
use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\ImageFormat;
use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\OptimizationResult;
use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\OptimizationStatus;
use Eekes\Sulu\ImageOptimizerBundle\Tests\Double\FailingEncoder;
use Eekes\Sulu\ImageOptimizerBundle\Tests\Double\FakeOptimizerTools;
use Eekes\Sulu\ImageOptimizerBundle\Tests\Double\Images;
use Eekes\Sulu\ImageOptimizerBundle\Tests\Double\Optimizers;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * What happens to an upload. The encoder is the real GD one; only the optimizer binaries are a
 * double, so the outcome does not depend on what the machine running the suite has installed.
 */
#[CoversClass(ImageOptimizer::class)]
final class ImageOptimizerTest extends TestCase
{
    protected function tearDown(): void
    {
        Images::cleanUp();
    }

    public function testAPhotoIsReencodedSmallerAndTheSmallerFileReplacesTheUpload(): void
    {
        $path = Images::jpeg(quality: 100);
        $before = filesize($path);

        $result = self::optimized(Optimizers::build()->optimize($path));

        self::assertSame(OptimizationStatus::Optimized, $result->status);
        self::assertSame(ImageFormat::Jpeg, $result->format);
        self::assertSame($before, $result->originalSize);
        self::assertSame(filesize($path), $result->finalSize);
        self::assertLessThan($before, $result->finalSize);
        self::assertSame(['gd'], $result->tools);
    }

    /**
     * Sulu reads the size of the upload after the optimizer ran. PHP caches file sizes, so without
     * clearing that cache the media library would record the size of the original.
     */
    public function testTheSizeSuluRecordsIsTheSizeOfTheOptimizedFile(): void
    {
        $path = Images::jpeg(quality: 100);
        $upload = new UploadedFile($path, 'photo.jpg', null, null, true);
        $upload->getSize();

        $result = self::optimized(Optimizers::build()->optimize($path));

        self::assertSame($result->finalSize, $upload->getSize());
    }

    public function testAnImageLargerThanTheMaximumIsScaledDownKeepingItsAspectRatio(): void
    {
        $path = Images::jpeg(800, 400);

        $result = self::optimized(Optimizers::build(new OptimizerSettings(maxSize: 400))->optimize($path));

        self::assertEquals(new Dimensions(800, 400), $result->originalDimensions);
        self::assertEquals(new Dimensions(400, 200), $result->finalDimensions);
        self::assertSame([400, 200], \array_slice((array) getimagesize($path), 0, 2));
    }

    /**
     * The previous bundle compared width and height with ">" both ways, so an image exactly as wide
     * as it is high was never resized.
     */
    public function testASquareImageLargerThanTheMaximumIsScaledDownToo(): void
    {
        $path = Images::png(600, 600);

        $result = self::optimized(Optimizers::build(new OptimizerSettings(maxSize: 300))->optimize($path));

        self::assertSame(OptimizationStatus::Optimized, $result->status);
        self::assertEquals(new Dimensions(300, 300), $result->finalDimensions);
    }

    /**
     * Logos are the typical PNG, and a logo that lost its transparency is a white box on the site.
     */
    public function testATransparentPngStaysTransparentWhenResized(): void
    {
        $gd = imagecreatetruecolor(600, 300);
        \assert($gd instanceof \GdImage);
        imagealphablending($gd, false);
        imagesavealpha($gd, true);
        $transparent = imagecolorallocatealpha($gd, 0, 0, 0, 127);
        \assert(\is_int($transparent));
        imagefill($gd, 0, 0, $transparent);
        $path = Images::png();
        imagepng($gd, $path);

        Optimizers::build(new OptimizerSettings(maxSize: 300))->optimize($path);

        $resized = imagecreatefrompng($path);
        \assert($resized instanceof \GdImage);
        $corner = imagecolorat($resized, 10, 10);
        self::assertIsInt($corner);
        self::assertSame(300, imagesx($resized));
        self::assertSame(127, imagecolorsforindex($resized, $corner)['alpha']);
    }

    public function testAnUploadThatDoesNotGetSmallerIsKeptAsItWas(): void
    {
        $path = Images::png();
        $original = file_get_contents($path);
        $tools = new FakeOptimizerTools();
        $tools->shrinkBy = -100;

        $result = self::optimized(Optimizers::build(tools: $tools)->optimize($path));

        self::assertSame(OptimizationStatus::Unchanged, $result->status);
        self::assertSame(0, $result->savedBytes());
        self::assertSame($original, file_get_contents($path));
    }

    public function testWhenSomethingFailsTheOriginalIsStoredUntouched(): void
    {
        $path = Images::jpeg();
        $original = file_get_contents($path);

        $result = self::optimized(Optimizers::build(encoder: new FailingEncoder())->optimize($path));

        self::assertSame(OptimizationStatus::Failed, $result->status);
        self::assertSame('GD could not decode the image.', $result->message);
        self::assertSame($original, file_get_contents($path));
    }

    /**
     * Running out of memory in GD is a fatal error that no try/catch survives, so an image too large
     * for the memory limit has to be refused before GD sees it.
     */
    public function testAnImageTooLargeForTheMemoryLimitFailsBeforeGdIsTouched(): void
    {
        $path = Images::jpeg();
        $original = file_get_contents($path);

        $result = self::optimized(Optimizers::build(encoder: new FailingEncoder(), memoryBudget: new MemoryBudget(1))->optimize($path));

        self::assertSame(OptimizationStatus::Failed, $result->status);
        self::assertStringContainsString('more memory', (string) $result->message);
        self::assertSame($original, file_get_contents($path));
    }

    public function testAnIgnoredFormatIsNeverTouched(): void
    {
        $path = Images::gif();
        $original = file_get_contents($path);
        $tools = new FakeOptimizerTools();

        $result = self::optimized(Optimizers::build(new OptimizerSettings(ignoredFormats: [ImageFormat::Gif]), $tools)->optimize($path));

        self::assertSame(OptimizationStatus::Skipped, $result->status);
        self::assertSame([], $tools->calls);
        self::assertSame($original, file_get_contents($path));
    }

    /**
     * An upload called ".JPG" that is really a PNG broke the previous bundle: the encoder wrote the
     * format the name promised.
     */
    public function testTheFormatIsReadFromTheContentNotFromTheFileName(): void
    {
        $png = Images::png();
        $misnamed = $png.'.JPG';
        rename($png, $misnamed);

        try {
            $result = self::optimized(Optimizers::build(new OptimizerSettings(maxSize: 100))->optimize($misnamed));

            self::assertSame(ImageFormat::Png, $result->format);
            self::assertSame('image/png', ((array) getimagesize($misnamed))['mime'] ?? null);
        } finally {
            unlink($misnamed);
        }
    }

    public function testAFileThatIsNotAnImageIsLeftToSulu(): void
    {
        self::assertNull(Optimizers::build()->optimize(Images::text()));
    }

    /**
     * GD only reads the first frame, so resizing an animation through it turns it into a still.
     */
    public function testAnAnimatedGifIsNeverFlattened(): void
    {
        $path = Images::animatedGif();
        $original = file_get_contents($path);
        $tools = new FakeOptimizerTools();

        $result = self::optimized(Optimizers::build(new OptimizerSettings(maxSize: 2), $tools)->optimize($path));

        self::assertSame(OptimizationStatus::Unchanged, $result->status);
        self::assertStringContainsString('Animated', (string) $result->message);
        self::assertCount(1, $tools->calls, 'gifsicle can still optimize the animation as a whole.');
        self::assertSame($original, file_get_contents($path));
    }

    public function testTheLocationAPhotoWasTakenIsRemoved(): void
    {
        $path = self::withExif(Images::jpeg());

        Optimizers::build()->optimize($path);

        self::assertStringNotContainsString('Exif', (string) file_get_contents($path));
    }

    /**
     * With metadata kept, re-encoding would still throw it away, so a photo within the size limit is
     * only handed to the tools that can leave it in place.
     */
    public function testAPhotoKeepsItsMetadataWhenAskedTo(): void
    {
        $path = self::withExif(Images::jpeg());
        $tools = new FakeOptimizerTools();
        $tools->shrinkBy = 10;

        $result = self::optimized(Optimizers::build(new OptimizerSettings(stripMetadata: false), $tools)->optimize($path));

        self::assertSame(['fake-tool'], $result->tools);
        self::assertStringContainsString('Exif', (string) file_get_contents($path));
    }

    public function testAScriptHiddenInAnSvgIsRemoved(): void
    {
        $path = Images::svg('<script>alert(document.cookie)</script><rect width="10" height="10" onload="alert(1)"/>');

        $result = self::optimized(Optimizers::build()->optimize($path));
        $cleaned = (string) file_get_contents($path);

        self::assertSame(OptimizationStatus::Optimized, $result->status);
        self::assertSame(ImageFormat::Svg, $result->format);
        self::assertStringNotContainsString('script', $cleaned);
        self::assertStringNotContainsString('onload', $cleaned);
        self::assertStringContainsString('<rect', $cleaned);
    }

    /**
     * Recognising an SVG by the first few kilobytes would let a long comment in front of the root
     * element carry a script straight past the sanitizer.
     */
    public function testAScriptCannotHideBehindALongPrologue(): void
    {
        $path = Images::text(
            '<?xml version="1.0"?><!--'.str_repeat(' padding', 2000).' -->'
            .'<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><rect width="10" height="10"/></svg>',
            'svg',
        );

        $result = self::optimized(Optimizers::build()->optimize($path));

        self::assertSame(ImageFormat::Svg, $result->format);
        self::assertStringNotContainsString('script', (string) file_get_contents($path));
    }

    public function testAWebPageWithAnInlineSvgIsNotAnImage(): void
    {
        $path = Images::text('<!DOCTYPE html><html><body><svg xmlns="http://www.w3.org/2000/svg"></svg></body></html>', 'html');

        self::assertNull(Optimizers::build()->optimize($path));
    }

    public function testAnSvgThatCannotBeParsedIsLoggedAsNotSanitized(): void
    {
        $path = Images::svg('<rect');
        $original = file_get_contents($path);

        $result = self::optimized(Optimizers::build()->optimize($path));

        self::assertSame(OptimizationStatus::Failed, $result->status);
        self::assertSame($original, file_get_contents($path));
    }

    public function testSvgsAreLeftAloneWhenSanitizingIsSwitchedOff(): void
    {
        self::assertNull(Optimizers::build(new OptimizerSettings(sanitizeSvg: false))->optimize(Images::svg()));
    }

    private static function optimized(?OptimizationResult $result): OptimizationResult
    {
        self::assertNotNull($result, 'The file was not recognised as an image.');

        return $result;
    }

    /**
     * Puts an APP1 segment with an (empty) EXIF block right after the start of the JPEG, which is
     * where a camera writes it.
     */
    private static function withExif(string $path): string
    {
        $jpeg = (string) file_get_contents($path);
        $exif = "Exif\0\0MM\0*\0\0\0\x08\0\0\0\0\0\0";
        $segment = "\xFF\xE1".pack('n', \strlen($exif) + 2).$exif;

        file_put_contents($path, substr($jpeg, 0, 2).$segment.substr($jpeg, 2));

        return $path;
    }
}

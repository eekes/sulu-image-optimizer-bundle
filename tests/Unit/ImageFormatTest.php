<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Tests\Unit;

use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\ImageFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ImageFormat::class)]
final class ImageFormatTest extends TestCase
{
    public function testTheWaysAProjectSpellsAFormatInItsConfigurationAllWork(): void
    {
        self::assertSame(ImageFormat::Jpeg, ImageFormat::fromName('jpeg'));
        self::assertSame(ImageFormat::Jpeg, ImageFormat::fromName('JPG'));
        self::assertSame(ImageFormat::Webp, ImageFormat::fromName(' webp '));
        self::assertNull(ImageFormat::fromName('bmp'));
    }

    public function testOnlyTheLossyFormatsAreReencodedToSaveSpace(): void
    {
        self::assertSame(
            [ImageFormat::Jpeg, ImageFormat::Webp, ImageFormat::Avif],
            array_values(array_filter(ImageFormat::cases(), static fn (ImageFormat $format): bool => $format->isLossy())),
        );
    }
}

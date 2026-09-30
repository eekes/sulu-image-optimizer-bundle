<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Spatie;

use Eekes\Sulu\ImageOptimizerBundle\Application\Service\ImageEncoderInterface;
use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\Dimensions;
use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\ImageFormat;
use Spatie\Image\Enums\ImageDriver;
use Spatie\Image\Image;

/**
 * Re-encodes through GD. GD rather than Imagick because it is the one extension every shared host
 * has, and it is also what Sulu itself falls back to.
 */
final readonly class SpatieImageEncoder implements ImageEncoderInterface
{
    /**
     * The quality spatie/image turns into zlib level 9 for a PNG. PNG is lossless, so the highest
     * compression only costs a little time and never any quality.
     */
    private const int PNG_MAXIMUM_COMPRESSION = 10;

    public function supports(ImageFormat $format): bool
    {
        $type = match ($format) {
            ImageFormat::Jpeg => \IMG_JPG,
            ImageFormat::Png => \IMG_PNG,
            ImageFormat::Gif => \IMG_GIF,
            ImageFormat::Webp => \IMG_WEBP,
            ImageFormat::Avif => \IMG_AVIF,
            ImageFormat::Svg => 0,
        };

        return $type !== 0 && \function_exists('imagetypes') && (imagetypes() & $type) === $type;
    }

    public function encode(string $path, ImageFormat $format, int $quality, ?Dimensions $resizeTo): Dimensions
    {
        // Loading applies the EXIF orientation to the pixels, which is what keeps a portrait photo
        // upright once the EXIF data is gone.
        $image = Image::useImageDriver(ImageDriver::Gd)->loadFile($path);

        if ($resizeTo !== null) {
            $image->resize($resizeTo->width, $resizeTo->height);
        }

        $image
            ->quality($format === ImageFormat::Png ? self::PNG_MAXIMUM_COMPRESSION : $quality)
            ->format($format->value)
            ->save($path);

        return new Dimensions($image->getWidth(), $image->getHeight());
    }
}

<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Application\Service;

use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\Dimensions;
use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\ImageFormat;

/**
 * Decodes a raster image, optionally scales it down, and writes it back in the same format.
 *
 * Writing the pixels again is what drops the EXIF and GPS data, after the rotation the EXIF
 * orientation asked for has been applied to the pixels themselves.
 */
interface ImageEncoderInterface
{
    /**
     * Whether this server can write the format at all. GD is often built without AVIF.
     */
    public function supports(ImageFormat $format): bool;

    /**
     * Overwrites the file at $path and returns the dimensions it ends up with.
     *
     * @param int<1, 100> $quality
     *
     * @throws \Throwable when the image cannot be decoded or written
     */
    public function encode(string $path, ImageFormat $format, int $quality, ?Dimensions $resizeTo): Dimensions;
}

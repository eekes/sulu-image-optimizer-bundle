<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Tests\Double;

use Eekes\Sulu\ImageOptimizerBundle\Application\Service\ImageEncoderInterface;
use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\Dimensions;
use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\ImageFormat;

/**
 * GD giving up halfway: the file is already truncated when the exception comes, the way a real
 * failure leaves a half-written file behind.
 */
final class FailingEncoder implements ImageEncoderInterface
{
    public function supports(ImageFormat $format): bool
    {
        return true;
    }

    public function encode(string $path, ImageFormat $format, int $quality, ?Dimensions $resizeTo): Dimensions
    {
        file_put_contents($path, 'half');

        throw new \RuntimeException('GD could not decode the image.');
    }
}

<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Application\Service;

/**
 * The current file version of an image in the media library.
 */
final readonly class StoredImage
{
    public function __construct(
        public int $mediaId,
        public int $version,
        public string $fileName,
        public string $mimeType,
        public int $size,
        public ?string $locale,
    ) {
    }
}

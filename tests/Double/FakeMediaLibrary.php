<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Tests\Double;

use Eekes\Sulu\ImageOptimizerBundle\Application\EventListener\MediaStoredListener;
use Eekes\Sulu\ImageOptimizerBundle\Application\Service\MediaLibraryInterface;
use Eekes\Sulu\ImageOptimizerBundle\Application\Service\StoredImage;
use Sulu\Bundle\MediaBundle\Domain\Event\MediaVersionAddedEvent;
use Sulu\Bundle\MediaBundle\Entity\MediaInterface;

/**
 * A media library held in memory. Adding a version does what Sulu does afterwards: it announces
 * the new version, which is how the log entry learns which version it is about.
 */
final class FakeMediaLibrary implements MediaLibraryInterface
{
    /**
     * @var array<int, array{media: MediaInterface, image: StoredImage, file: string}>
     */
    private array $entries = [];

    /**
     * @var list<array{int, int, string}> media id, new version, the contents that were stored
     */
    public array $addedVersions = [];

    public function __construct(
        private readonly MediaStoredListener $mediaStoredListener,
    ) {
    }

    public function add(MediaInterface $media, string $file, int $version = 1): StoredImage
    {
        $mimeType = mime_content_type($file);
        \assert(\is_string($mimeType));

        if (str_ends_with($file, '.svg')) {
            $mimeType = 'image/svg+xml';
        }

        $image = new StoredImage($media->getId(), $version, basename($file), $mimeType, (int) filesize($file), 'en');
        $this->entries[$media->getId()] = ['media' => $media, 'image' => $image, 'file' => $file];

        return $image;
    }

    public function images(array $mimeTypes, ?int $collectionId, int $minimumSize): iterable
    {
        foreach ($this->entries as $entry) {
            if (\in_array($entry['image']->mimeType, $mimeTypes, true) && $entry['image']->size >= $minimumSize) {
                yield $entry['image'];
            }
        }
    }

    public function download(StoredImage $image): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'eekes_download_');
        copy($this->entries[$image->mediaId]['file'], $path);

        return $path;
    }

    public function addVersion(StoredImage $image, string $path): void
    {
        $entry = $this->entries[$image->mediaId];
        $version = $image->version + 1;

        $this->addedVersions[] = [$image->mediaId, $version, (string) file_get_contents($path)];
        $this->entries[$image->mediaId]['image'] = new StoredImage($image->mediaId, $version, $image->fileName, $image->mimeType, (int) filesize($path), $image->locale);

        $this->mediaStoredListener->onMediaVersionAdded(new MediaVersionAddedEvent($entry['media'], $version));
    }

    public function find(int $mediaId): ?MediaInterface
    {
        return ($this->entries[$mediaId] ?? null)['media'] ?? null;
    }

    public function clear(): void
    {
    }
}

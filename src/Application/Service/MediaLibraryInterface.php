<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Application\Service;

use Sulu\Bundle\MediaBundle\Entity\MediaInterface;

/**
 * The media library as far as optimizing what is already in it needs one.
 */
interface MediaLibraryInterface
{
    /**
     * The current version of every image, oldest media first.
     *
     * @param list<string> $mimeTypes
     *
     * @return iterable<StoredImage>
     */
    public function images(array $mimeTypes, ?int $collectionId, int $minimumSize): iterable;

    /**
     * Copies the stored file to a local temporary file and returns its path. The caller removes it.
     */
    public function download(StoredImage $image): string;

    /**
     * Stores the file at $path as a new version of the media, the way uploading a new version in the
     * administration interface does. The previous version stays available in the media's history.
     */
    public function addVersion(StoredImage $image, string $path): void;

    public function find(int $mediaId): ?MediaInterface;

    /**
     * Lets go of everything loaded for one image, so going over a large library does not keep every
     * media in memory.
     */
    public function clear(): void;
}

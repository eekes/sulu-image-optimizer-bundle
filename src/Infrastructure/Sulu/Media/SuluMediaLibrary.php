<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Sulu\Media;

use Doctrine\ORM\EntityManagerInterface;
use Eekes\Sulu\ImageOptimizerBundle\Application\Service\MediaLibraryInterface;
use Eekes\Sulu\ImageOptimizerBundle\Application\Service\StoredImage;
use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\ImageFormat;
use Sulu\Bundle\MediaBundle\Entity\FileVersion;
use Sulu\Bundle\MediaBundle\Entity\MediaInterface;
use Sulu\Bundle\MediaBundle\Entity\MediaRepositoryInterface;
use Sulu\Bundle\MediaBundle\Media\Manager\MediaManagerInterface;
use Sulu\Bundle\MediaBundle\Media\Storage\StorageInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * The media library of Sulu, reached through Sulu's own storage and media manager, so whichever
 * storage a project uses (local, S3, ...) keeps working and a new version is stored exactly the way
 * an upload in the administration interface stores one.
 */
final readonly class SuluMediaLibrary implements MediaLibraryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MediaRepositoryInterface $mediaRepository,
        private MediaManagerInterface $mediaManager,
        private StorageInterface $storage,
    ) {
    }

    public function images(array $mimeTypes, ?int $collectionId, int $minimumSize): iterable
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('media.id AS mediaId')
            ->addSelect('fileVersion.version AS version')
            ->addSelect('fileVersion.name AS fileName')
            ->addSelect('fileVersion.mimeType AS mimeType')
            ->addSelect('fileVersion.size AS size')
            ->addSelect('defaultMeta.locale AS locale')
            ->from(MediaInterface::class, 'media')
            ->innerJoin('media.files', 'file')
            ->innerJoin('file.fileVersions', 'fileVersion', 'WITH', 'fileVersion.version = file.version')
            ->leftJoin('fileVersion.defaultMeta', 'defaultMeta')
            ->where('fileVersion.mimeType IN (:mimeTypes)')
            ->andWhere('fileVersion.size >= :minimumSize')
            ->setParameter('mimeTypes', $mimeTypes)
            ->setParameter('minimumSize', $minimumSize)
            ->orderBy('media.id', 'ASC');

        if ($collectionId !== null) {
            $queryBuilder
                ->andWhere('IDENTITY(media.collection) = :collectionId')
                ->setParameter('collectionId', $collectionId);
        }

        // Scalars rather than entities, so the entity manager can be cleared between images without
        // pulling the rows out from under the loop.
        /** @var iterable<array{mediaId: int|string, version: int|string, fileName: string, mimeType: null|string, size: int|string, locale: null|string}> $rows */
        $rows = $queryBuilder->getQuery()->toIterable([], \Doctrine\ORM\AbstractQuery::HYDRATE_SCALAR);

        foreach ($rows as $row) {
            yield new StoredImage(
                (int) $row['mediaId'],
                (int) $row['version'],
                $row['fileName'],
                $row['mimeType'] ?? '',
                (int) $row['size'],
                $row['locale'],
            );
        }
    }

    public function download(StoredImage $image): string
    {
        $fileVersion = $this->fileVersion($image);
        $format = ImageFormat::fromMimeType($image->mimeType);

        $temporary = tempnam(sys_get_temp_dir(), 'eekes_media_');

        if ($temporary === false) {
            throw new \RuntimeException('No temporary file could be created.');
        }

        $path = $format === null ? $temporary : $temporary.'.'.$format->value;

        if ($path !== $temporary && !rename($temporary, $path)) {
            throw new \RuntimeException(\sprintf('The temporary file could not be renamed to "%s".', $path));
        }

        $source = $this->storage->load($fileVersion->getStorageOptions());
        $target = fopen($path, 'wb');

        if (!\is_resource($source) || $target === false) {
            throw new \RuntimeException(\sprintf('The file of media %d could not be read from the storage.', $image->mediaId));
        }

        try {
            stream_copy_to_stream($source, $target);
        } finally {
            fclose($source);
            fclose($target);
        }

        return $path;
    }

    public function addVersion(StoredImage $image, string $path): void
    {
        // The last argument marks the file as not coming from an HTTP upload, which is what lets
        // Symfony accept a file that PHP did not receive itself.
        $uploadedFile = new UploadedFile($path, $image->fileName, $image->mimeType, null, true);

        $this->mediaManager->save(
            $uploadedFile,
            ['id' => $image->mediaId, 'locale' => $image->locale ?? 'en'],
            null,
        );
    }

    public function find(int $mediaId): ?MediaInterface
    {
        $media = $this->mediaRepository->findMediaById($mediaId);

        return $media instanceof MediaInterface ? $media : null;
    }

    public function clear(): void
    {
        $this->entityManager->clear();
    }

    private function fileVersion(StoredImage $image): FileVersion
    {
        $media = $this->find($image->mediaId);
        $file = $media?->getFiles()->first();
        $fileVersion = $file === false || $file === null ? null : $file->getFileVersion($image->version);

        if ($fileVersion === null) {
            throw new \RuntimeException(\sprintf('Version %d of media %d no longer exists.', $image->version, $image->mediaId));
        }

        return $fileVersion;
    }
}

<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Domain\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Eekes\Sulu\ImageOptimizerBundle\Domain\Repository\ImageOptimizationRepository;
use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\ImageFormat;
use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\OptimizationResult;
use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\OptimizationSource;
use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\OptimizationStatus;
use Sulu\Bundle\MediaBundle\Entity\MediaInterface;

/**
 * Write-once log of what the optimizer did to one file version in the media library.
 *
 * The table name in the attribute is only the default: ImageOptimizationTableListener applies the
 * name a project configured.
 */
#[ORM\Entity(repositoryClass: ImageOptimizationRepository::class)]
#[ORM\Table(name: self::DEFAULT_TABLE_NAME)]
#[ORM\Index(name: 'idx_eekes_image_optimization_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_eekes_image_optimization_status', columns: ['status'])]
#[ORM\Index(name: 'idx_eekes_image_optimization_media_version', columns: ['media_id', 'media_version'])]
class ImageOptimization
{
    public const string DEFAULT_TABLE_NAME = 'eekes_image_optimization';

    final public const string SULU_RESOURCE_KEY = 'eekes_image_optimizations';
    final public const string SULU_LIST_KEY = 'eekes_image_optimizations';
    final public const string SULU_SECURITY_CONTEXT = 'sulu.settings.eekes_image_optimizations';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public int $id;

    /**
     * The media the file belongs to. The log outlives the media on purpose: what an upload saved
     * stays part of the totals after an editor deletes the image, so the reference is cleared by
     * the database instead of taking the row with it.
     */
    #[ORM\ManyToOne(targetEntity: MediaInterface::class)]
    #[ORM\JoinColumn(name: 'media_id', nullable: true, onDelete: 'SET NULL')]
    public ?MediaInterface $media = null;

    /**
     * The file version of the media this entry is about. A new version of a media is optimized, and
     * logged, on its own.
     */
    #[ORM\Column(nullable: true)]
    public ?int $mediaVersion = null;

    /**
     * The file name as it was uploaded, kept so the entry still says something once the media is gone.
     */
    #[ORM\Column(length: 255)]
    public string $fileName;

    /**
     * The locale the media was uploaded in, which the link to the media opens it in.
     */
    #[ORM\Column(length: 15, nullable: true)]
    public ?string $locale = null;

    #[ORM\Column(length: 10)]
    public string $format;

    #[ORM\Column(length: 20)]
    public string $status;

    #[ORM\Column(length: 20)]
    public string $source;

    #[ORM\Column]
    public int $originalSize;

    #[ORM\Column]
    public int $finalSize;

    /**
     * Stored rather than computed, so the list can sort and filter on it.
     */
    #[ORM\Column]
    public int $savedBytes;

    #[ORM\Column(type: Types::FLOAT)]
    public float $savedPercentage;

    #[ORM\Column(nullable: true)]
    public ?int $originalWidth = null;

    #[ORM\Column(nullable: true)]
    public ?int $originalHeight = null;

    #[ORM\Column(nullable: true)]
    public ?int $finalWidth = null;

    #[ORM\Column(nullable: true)]
    public ?int $finalHeight = null;

    /**
     * What processed the file, comma separated - which is also how a missing optimizer binary on
     * the server shows up: its name is simply never there.
     */
    #[ORM\Column(length: 255, nullable: true)]
    public ?string $tools = null;

    /**
     * Why an image was skipped, or what went wrong when it failed.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $message = null;

    #[ORM\Column]
    public int $durationMs;

    /**
     * The name of whoever uploaded the file, as it was at that moment. A copy rather than a relation,
     * so removing a user leaves the log intact.
     */
    #[ORM\Column(length: 255, nullable: true)]
    public ?string $userName = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    public \DateTimeImmutable $createdAt;

    public function __construct(OptimizationResult $result, string $fileName, OptimizationSource $source)
    {
        $this->fileName = mb_substr($fileName, 0, 255);
        $this->format = $result->format->value;
        $this->status = $result->status->value;
        $this->source = $source->value;
        $this->originalSize = $result->originalSize;
        $this->finalSize = $result->finalSize;
        $this->savedBytes = $result->savedBytes();
        $this->savedPercentage = $result->savedPercentage();
        $this->originalWidth = $result->originalDimensions?->width;
        $this->originalHeight = $result->originalDimensions?->height;
        $this->finalWidth = $result->finalDimensions?->width;
        $this->finalHeight = $result->finalDimensions?->height;
        $this->tools = $result->tools === [] ? null : mb_substr(implode(', ', $result->tools), 0, 255);
        $this->message = $result->message;
        $this->durationMs = $result->durationMs;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function status(): OptimizationStatus
    {
        return OptimizationStatus::from($this->status);
    }

    public function format(): ImageFormat
    {
        return ImageFormat::from($this->format);
    }

    public function source(): OptimizationSource
    {
        return OptimizationSource::from($this->source);
    }
}

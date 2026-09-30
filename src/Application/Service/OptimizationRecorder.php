<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Application\Service;

use Eekes\Sulu\ImageOptimizerBundle\Domain\Entity\ImageOptimization;
use Eekes\Sulu\ImageOptimizerBundle\Domain\Repository\ImageOptimizationRepository;
use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\OptimizationResult;
use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\OptimizationSource;
use Psr\Log\LoggerInterface;
use Sulu\Bundle\MediaBundle\Entity\MediaInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Holds on to what the optimizer did until it is known which media the file became.
 *
 * The file is optimized before Sulu has even looked at the upload, so at that moment there is no
 * media to link the log entry to. Sulu announces the media it created (or the version it added)
 * once it flushed, and only then is an entry complete. An upload Sulu refused never gets that far,
 * and its entry is dropped: it would be a log of a file that is not in the media library.
 */
final class OptimizationRecorder implements ResetInterface
{
    /**
     * @var list<ImageOptimization>
     */
    private array $pending = [];

    public function __construct(
        private readonly ImageOptimizationRepository $imageOptimizationRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function record(OptimizationResult $result, string $fileName, ?string $locale, OptimizationSource $source): void
    {
        $imageOptimization = new ImageOptimization($result, $fileName, $source);
        $imageOptimization->locale = $locale === null ? null : mb_substr($locale, 0, 15);

        $this->pending[] = $imageOptimization;
    }

    /**
     * Links everything recorded so far that has no media yet. There is one file per upload, so in
     * practice this is exactly the one entry the request produced.
     */
    public function attachToMedia(MediaInterface $media, int $version, ?string $userName): void
    {
        foreach ($this->pending as $imageOptimization) {
            if ($imageOptimization->media !== null) {
                continue;
            }

            $imageOptimization->media = $media;
            $imageOptimization->mediaVersion = $version;
            $imageOptimization->userName = $userName === null ? null : mb_substr($userName, 0, 255);
        }
    }

    public function hasPending(): bool
    {
        return $this->pending !== [];
    }

    /**
     * Stores the entries that were linked to a media and forgets the rest.
     *
     * A failure here is logged and swallowed. By now the media is stored, and a log that could not
     * be written is no reason to show the editor an error for an upload that worked.
     */
    public function flush(): void
    {
        $pending = $this->pending;
        $this->pending = [];

        $linked = array_filter($pending, static fn (ImageOptimization $entry): bool => $entry->media !== null);

        if (\count($linked) !== \count($pending)) {
            $this->logger->info('{count} optimization(s) were not logged because the upload did not end up in the media library.', [
                'count' => \count($pending) - \count($linked),
            ]);
        }

        if ($linked === []) {
            return;
        }

        try {
            $this->imageOptimizationRepository->saveAll(array_values($linked));
        } catch (\Throwable $exception) {
            $this->logger->error('The image optimization log could not be written: {message}', [
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);
        }
    }

    public function reset(): void
    {
        $this->pending = [];
    }
}

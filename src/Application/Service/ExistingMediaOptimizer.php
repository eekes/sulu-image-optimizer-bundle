<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Application\Service;

use Eekes\Sulu\ImageOptimizerBundle\Domain\Repository\ImageOptimizationRepository;
use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\ImageFormat;
use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\OptimizationResult;
use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\OptimizationSource;
use Psr\Log\LoggerInterface;

/**
 * Runs the optimizer over images that were uploaded before the bundle was installed.
 *
 * An image that got smaller is stored as a new version of its media rather than overwriting the
 * file, so every change can be undone from the media's history and Sulu clears the cached image
 * formats of the old version itself. An image that is not smaller is only logged, which is also
 * what lets the next run skip it.
 */
final readonly class ExistingMediaOptimizer
{
    public function __construct(
        private ImageOptimizer $imageOptimizer,
        private OptimizationRecorder $recorder,
        private MediaLibraryInterface $mediaLibrary,
        private ImageOptimizationRepository $imageOptimizationRepository,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param callable(StoredImage, ExistingMediaOutcome, ?OptimizationResult, ?string): void $report called once per image
     */
    public function run(
        callable $report,
        bool $dryRun = false,
        ?int $collectionId = null,
        int $minimumSize = 0,
        ?int $limit = null,
        bool $force = false,
    ): ExistingMediaSummary {
        $summary = new ExistingMediaSummary();

        foreach ($this->mediaLibrary->images(ImageFormat::allMimeTypes(), $collectionId, $minimumSize) as $image) {
            if ($limit !== null && $summary->processed >= $limit) {
                break;
            }

            if (!$force && $this->imageOptimizationRepository->hasHandled($image->mediaId, $image->version)) {
                $summary->count(ExistingMediaOutcome::AlreadyHandled, null);
                $report($image, ExistingMediaOutcome::AlreadyHandled, null, null);

                continue;
            }

            [$outcome, $result, $error] = $this->handle($image, $dryRun);

            $summary->count($outcome, $result);
            $report($image, $outcome, $result, $error);
        }

        return $summary;
    }

    /**
     * @return array{ExistingMediaOutcome, ?OptimizationResult, ?string}
     */
    private function handle(StoredImage $image, bool $dryRun): array
    {
        $path = null;

        try {
            $path = $this->mediaLibrary->download($image);
            $result = $this->imageOptimizer->optimize($path);

            if ($result === null) {
                return [ExistingMediaOutcome::NotAnImage, null, null];
            }

            if ($dryRun) {
                return [ExistingMediaOutcome::Processed, $result, null];
            }

            $this->recorder->record($result, $image->fileName, $image->locale, OptimizationSource::Command);

            if ($result->changedTheFile()) {
                // Sulu announces the new version, which links the entry to it.
                $this->mediaLibrary->addVersion($image, $path);
            } else {
                $media = $this->mediaLibrary->find($image->mediaId);

                if ($media !== null) {
                    $this->recorder->attachToMedia($media, $image->version, null);
                }
            }

            $this->recorder->flush();

            return [ExistingMediaOutcome::Processed, $result, null];
        } catch (\Throwable $exception) {
            $this->recorder->reset();
            $this->logger->error('Optimizing media {id} failed: {message}', [
                'id' => $image->mediaId,
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            return [ExistingMediaOutcome::Error, null, $exception->getMessage()];
        } finally {
            if ($path !== null && is_file($path)) {
                unlink($path);
            }

            $this->mediaLibrary->clear();
        }
    }
}

<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Application\EventListener;

use Eekes\Sulu\ImageOptimizerBundle\Application\Service\ImageOptimizer;
use Eekes\Sulu\ImageOptimizerBundle\Application\Service\OptimizationRecorder;
use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\OptimizationSource;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Optimizes a file uploaded to the media library before Sulu stores it.
 *
 * Sulu reads the uploaded file from its temporary location, so changing the file there is enough:
 * the storage, the size Sulu records and every image format it generates later all start from the
 * optimized version. That is also what keeps Sulu's own cropping within the limits of a small
 * server - it never gets to see the 40 megapixel original.
 */
final readonly class MediaUploadListener
{
    /**
     * A new media, a new version of an existing one, and the preview image of a video or document -
     * which Sulu stores as a media of its own. The second route also moves media; only the
     * "new-version" action carries a file.
     */
    private const string CREATE_ROUTE = 'sulu_media.post_media';
    private const string TRIGGER_ROUTE = 'sulu_media.post_media_trigger';
    private const string PREVIEW_ROUTE = 'sulu_media.post_media_preview';

    /**
     * The form fields the administration interface uploads the file in.
     */
    private const string FILE_FIELD = 'fileVersion';
    private const string PREVIEW_FIELD = 'previewImage';

    public function __construct(
        private ImageOptimizer $imageOptimizer,
        private OptimizationRecorder $recorder,
    ) {
    }

    /**
     * Registered after the firewall on purpose, so optimizing - the expensive part - only ever runs
     * for a request that is allowed to upload.
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $field = self::fileField($request);

        if ($field === null) {
            return;
        }

        $uploadedFile = $request->files->get($field);

        if (!$uploadedFile instanceof UploadedFile || !$uploadedFile->isValid()) {
            return;
        }

        $result = $this->imageOptimizer->optimize($uploadedFile->getPathname());

        if ($result === null) {
            return;
        }

        $locale = $request->query->getString('locale');

        $this->recorder->record(
            $result,
            $uploadedFile->getClientOriginalName(),
            $locale === '' ? null : $locale,
            OptimizationSource::Upload,
        );
    }

    /**
     * By the time the response goes out, Sulu has stored the media and announced it, so the entry
     * knows which media it belongs to.
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->recorder->hasPending()) {
            return;
        }

        $this->recorder->flush();
    }

    /**
     * The form field holding the file when the request is an upload to the media library.
     */
    private static function fileField(Request $request): ?string
    {
        if (!$request->isMethod('POST')) {
            return null;
        }

        return match ($request->attributes->get('_route')) {
            self::CREATE_ROUTE => self::FILE_FIELD,
            self::TRIGGER_ROUTE => $request->query->getString('action') === 'new-version' ? self::FILE_FIELD : null,
            self::PREVIEW_ROUTE => self::PREVIEW_FIELD,
            default => null,
        };
    }
}

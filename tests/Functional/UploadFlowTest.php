<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Tests\Functional;

use Eekes\Sulu\ImageOptimizerBundle\Application\EventListener\MediaStoredListener;
use Eekes\Sulu\ImageOptimizerBundle\Application\EventListener\MediaUploadListener;
use Eekes\Sulu\ImageOptimizerBundle\Application\Service\ImageOptimizer;
use Eekes\Sulu\ImageOptimizerBundle\Application\Service\OptimizationRecorder;
use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\OptimizationSource;
use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\OptimizationStatus;
use Eekes\Sulu\ImageOptimizerBundle\Tests\Double\Images;
use PHPUnit\Framework\Attributes\CoversClass;
use Sulu\Bundle\MediaBundle\Domain\Event\MediaCreatedEvent;
use Sulu\Bundle\MediaBundle\Domain\Event\MediaVersionAddedEvent;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * An upload from start to finish: the request comes in, Sulu stores the media and announces it,
 * the response goes out.
 *
 * The test kernel has no SuluMediaBundle, so the two listeners are built here with the real
 * optimizer and recorder, and the part Sulu plays - announcing the media - is played by the test.
 */
#[CoversClass(MediaUploadListener::class)]
#[CoversClass(MediaStoredListener::class)]
#[CoversClass(OptimizationRecorder::class)]
final class UploadFlowTest extends FunctionalTestCase
{
    public function testAnUploadedPhotoIsOptimizedAndLoggedAgainstTheMediaItBecame(): void
    {
        $path = Images::jpeg(quality: 100);
        $before = filesize($path);
        $request = $this->upload('sulu_media.post_media', $path, 'Holiday.JPG');

        $this->uploadListener()->onKernelRequest($this->requestEvent($request));
        $media = $this->media();
        $this->storedListener()->onMediaCreated(new MediaCreatedEvent($media, 'nl', []));
        $this->uploadListener()->onKernelResponse($this->responseEvent($request));

        [$entry] = $this->logged();

        self::assertLessThan($before, filesize($path), 'Sulu stores the file at this path, so it has to be the smaller one.');
        self::assertSame(OptimizationStatus::Optimized, $entry->status());
        self::assertSame(OptimizationSource::Upload, $entry->source());
        self::assertSame('Holiday.JPG', $entry->fileName);
        self::assertSame('nl', $entry->locale);
        self::assertSame($before, $entry->originalSize);
        self::assertSame(filesize($path), $entry->finalSize);
        self::assertSame($media->getId(), $entry->media?->getId());
        self::assertSame(1, $entry->mediaVersion);
    }

    public function testANewVersionOfAMediaIsLoggedAgainstThatVersion(): void
    {
        $media = $this->media();
        $request = $this->upload('sulu_media.post_media_trigger', Images::jpeg(), 'photo.jpg', ['action' => 'new-version']);

        $this->uploadListener()->onKernelRequest($this->requestEvent($request));
        $this->storedListener()->onMediaVersionAdded(new MediaVersionAddedEvent($media, 4));
        $this->uploadListener()->onKernelResponse($this->responseEvent($request));

        [$entry] = $this->logged();

        self::assertSame(4, $entry->mediaVersion);
    }

    /**
     * Sulu stores the preview image of a video or a document as a media of its own, and generates
     * its formats from it like from any other image.
     */
    public function testAPreviewImageIsOptimizedToo(): void
    {
        $path = Images::jpeg(quality: 100);
        $before = filesize($path);
        $request = $this->upload('sulu_media.post_media_preview', $path, 'poster.jpg', field: 'previewImage');

        $this->uploadListener()->onKernelRequest($this->requestEvent($request));
        $preview = $this->media();
        $this->storedListener()->onMediaCreated(new MediaCreatedEvent($preview, 'nl', []));
        $this->uploadListener()->onKernelResponse($this->responseEvent($request));

        [$entry] = $this->logged();

        self::assertLessThan($before, filesize($path));
        self::assertSame(OptimizationStatus::Optimized, $entry->status());
        self::assertSame($preview->getId(), $entry->media?->getId());
    }

    /**
     * The same route moves a media to another collection, which carries no file at all.
     */
    public function testMovingAMediaIsNotAnUpload(): void
    {
        $path = Images::jpeg();
        $original = file_get_contents($path);
        $request = $this->upload('sulu_media.post_media_trigger', $path, 'photo.jpg', ['action' => 'move']);

        $this->uploadListener()->onKernelRequest($this->requestEvent($request));

        self::assertFalse($this->recorder()->hasPending());
        self::assertSame($original, file_get_contents($path));
    }

    public function testFilesPostedToAnyOtherRouteAreLeftAlone(): void
    {
        $path = Images::jpeg();
        $original = file_get_contents($path);

        $this->uploadListener()->onKernelRequest($this->requestEvent($this->upload('app_contact_form', $path, 'photo.jpg')));

        self::assertSame($original, file_get_contents($path));
    }

    /**
     * An upload Sulu refused - wrong file type, too large, no permission for the collection - never
     * becomes a media, so there is nothing in the media library for an entry to be about.
     */
    public function testAnUploadSuluRefusedIsNotLogged(): void
    {
        $request = $this->upload('sulu_media.post_media', Images::jpeg(), 'photo.jpg');

        $this->uploadListener()->onKernelRequest($this->requestEvent($request));
        $this->uploadListener()->onKernelResponse($this->responseEvent($request));

        self::assertSame([], $this->logged());
        self::assertFalse($this->recorder()->hasPending());
    }

    public function testAFailedOptimizationIsLoggedTooSoItCanBeLookedInto(): void
    {
        $request = $this->upload('sulu_media.post_media', Images::svg('<rect'), 'broken.svg');

        $this->uploadListener()->onKernelRequest($this->requestEvent($request));
        $this->storedListener()->onMediaCreated(new MediaCreatedEvent($this->media(), 'en', []));
        $this->uploadListener()->onKernelResponse($this->responseEvent($request));

        [$entry] = $this->logged();

        self::assertSame(OptimizationStatus::Failed, $entry->status());
        self::assertNotNull($entry->message);
    }

    /**
     * The log keeps counting what an upload saved after the image itself is deleted; only the link
     * to it goes.
     */
    public function testTheLogOutlivesTheMedia(): void
    {
        $media = $this->media();
        $request = $this->upload('sulu_media.post_media', Images::jpeg(), 'photo.jpg');

        $this->uploadListener()->onKernelRequest($this->requestEvent($request));
        $this->storedListener()->onMediaCreated(new MediaCreatedEvent($media, 'en', []));
        $this->uploadListener()->onKernelResponse($this->responseEvent($request));

        $this->entityManager->getConnection()->executeStatement('PRAGMA foreign_keys = ON');
        $this->entityManager->getConnection()->executeStatement('DELETE FROM test_media');

        [$entry] = $this->logged();

        self::assertNull($entry->media);
        self::assertSame('photo.jpg', $entry->fileName);
    }

    /**
     * @param array<string, string> $query
     */
    private function upload(string $route, string $path, string $clientName, array $query = [], string $field = 'fileVersion'): Request
    {
        // The administration interface sends these in the query string, not in the posted form.
        $request = Request::create('/admin/api/media?'.http_build_query($query + ['locale' => 'nl']), 'POST');
        $request->attributes->set('_route', $route);
        $request->files->set($field, new UploadedFile($path, $clientName, null, null, true));

        return $request;
    }

    private function requestEvent(Request $request): RequestEvent
    {
        return new RequestEvent($this->kernel(), $request, HttpKernelInterface::MAIN_REQUEST);
    }

    private function responseEvent(Request $request): ResponseEvent
    {
        return new ResponseEvent($this->kernel(), $request, HttpKernelInterface::MAIN_REQUEST, new Response());
    }

    private function kernel(): HttpKernelInterface
    {
        $kernel = self::$kernel;
        \assert($kernel instanceof HttpKernelInterface);

        return $kernel;
    }

    private function uploadListener(): MediaUploadListener
    {
        $optimizer = self::getContainer()->get('eekes_test.image_optimizer');
        \assert($optimizer instanceof ImageOptimizer);

        return new MediaUploadListener($optimizer, $this->recorder());
    }

    private function storedListener(): MediaStoredListener
    {
        return new MediaStoredListener($this->recorder());
    }
}

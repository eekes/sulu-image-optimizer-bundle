<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Application\EventListener;

use Eekes\Sulu\ImageOptimizerBundle\Application\Service\OptimizationRecorder;
use Sulu\Bundle\ActivityBundle\Domain\Event\DomainEvent;
use Sulu\Bundle\MediaBundle\Domain\Event\MediaCreatedEvent;
use Sulu\Bundle\MediaBundle\Domain\Event\MediaVersionAddedEvent;
use Sulu\Bundle\MediaBundle\Entity\MediaInterface;

/**
 * Tells the recorder which media an optimized file became.
 *
 * These are the domain events Sulu's activity log is built from. Sulu dispatches them once the
 * media is flushed, so the media has its id by the time they arrive. They are dispatched both
 * under their own class name and under DomainEvent::class; listening to the class name is what
 * keeps this listener from being called for every other change in the administration interface.
 */
final readonly class MediaStoredListener
{
    public function __construct(
        private OptimizationRecorder $recorder,
    ) {
    }

    public function onMediaCreated(MediaCreatedEvent $event): void
    {
        $media = $event->getMedia();

        $this->recorder->attachToMedia($media, self::currentVersion($media), self::userName($event));
    }

    public function onMediaVersionAdded(MediaVersionAddedEvent $event): void
    {
        $this->recorder->attachToMedia($event->getMedia(), $event->getVersion(), self::userName($event));
    }

    private static function currentVersion(MediaInterface $media): int
    {
        // A media has exactly one file; its version is the one that was just created.
        $file = $media->getFiles()->first();

        return $file === false ? 1 : $file->getVersion();
    }

    private static function userName(DomainEvent $event): ?string
    {
        return $event->getUser()?->getFullName();
    }
}

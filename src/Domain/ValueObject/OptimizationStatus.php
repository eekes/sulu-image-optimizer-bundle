<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject;

/**
 * What happened to one image. Every status leaves a file that can be stored: a failure never blocks
 * an upload, it only means the original went into the media library untouched.
 */
enum OptimizationStatus: string
{
    /**
     * The file was made smaller, resized, or (for an SVG) cleaned, and the result was kept.
     */
    case Optimized = 'optimized';

    /**
     * Everything ran, but the result was not smaller than the original, so the original was kept.
     */
    case Unchanged = 'unchanged';

    /**
     * Deliberately not touched, for example because the format is in ignore_types.
     */
    case Skipped = 'skipped';

    /**
     * Something went wrong. The original was kept and the reason is in the message.
     */
    case Failed = 'failed';
}

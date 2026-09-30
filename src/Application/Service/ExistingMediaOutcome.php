<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Application\Service;

/**
 * What happened to one media while going over the existing library. Processed means the optimizer
 * ran; its result says what it did.
 */
enum ExistingMediaOutcome: string
{
    case Processed = 'processed';
    case AlreadyHandled = 'already_handled';
    case NotAnImage = 'not_an_image';
    case Error = 'error';
}

<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject;

/**
 * Where an optimization was started: an upload in the administration interface, or the console
 * command that goes over the images already in the media library.
 */
enum OptimizationSource: string
{
    case Upload = 'upload';
    case Command = 'command';
}

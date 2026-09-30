<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Tests\Double;

use Symfony\Component\Process\ExecutableFinder;

/**
 * A PATH without any optimizer on it, whatever the machine running the suite has installed.
 */
final class NothingOnThePath extends ExecutableFinder
{
    /**
     * @param list<string> $extraDirs
     */
    public function find(string $name, ?string $default = null, array $extraDirs = []): ?string
    {
        return $default;
    }
}

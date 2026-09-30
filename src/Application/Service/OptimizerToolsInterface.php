<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Application\Service;

use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\ImageFormat;

/**
 * The command line optimizers (jpegoptim, pngquant, ...) that squeeze a file further without
 * decoding it.
 */
interface OptimizerToolsInterface
{
    /**
     * Runs every available tool for the format over the file, in place.
     *
     * @return list<string> the names of the tools that ran
     */
    public function optimize(string $path, ImageFormat $format): array;

    /**
     * Every tool the bundle knows, and whether this server has it.
     *
     * @return list<ToolStatus>
     */
    public function status(): array;
}

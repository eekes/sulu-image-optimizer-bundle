<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Tests\Double;

use Eekes\Sulu\ImageOptimizerBundle\Application\Service\ImageEncoderInterface;
use Eekes\Sulu\ImageOptimizerBundle\Application\Service\ImageOptimizer;
use Eekes\Sulu\ImageOptimizerBundle\Application\Service\MemoryBudget;
use Eekes\Sulu\ImageOptimizerBundle\Application\Service\OptimizerSettings;
use Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Spatie\SpatieImageEncoder;
use Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Svg\EnshrinedSvgSanitizer;
use Psr\Log\NullLogger;

/**
 * An ImageOptimizer built from the real encoder and sanitizer, with only the binaries replaced.
 */
final class Optimizers
{
    public static function build(
        OptimizerSettings $settings = new OptimizerSettings(),
        ?FakeOptimizerTools $tools = null,
        ?ImageEncoderInterface $encoder = null,
        MemoryBudget $memoryBudget = new MemoryBudget(-1),
    ): ImageOptimizer {
        return new ImageOptimizer(
            $settings,
            $encoder ?? new SpatieImageEncoder(),
            $tools ?? new FakeOptimizerTools(),
            new EnshrinedSvgSanitizer(),
            $memoryBudget,
            new NullLogger(),
        );
    }
}

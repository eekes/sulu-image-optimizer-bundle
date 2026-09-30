<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Application\Service;

use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\OptimizationResult;
use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\OptimizationStatus;

/**
 * The totals of one run over the existing media library.
 */
final class ExistingMediaSummary
{
    /**
     * Images the optimizer actually ran for; what a --limit counts.
     */
    public int $processed = 0;
    public int $optimized = 0;
    public int $alreadyHandled = 0;
    public int $errors = 0;
    public int $savedBytes = 0;

    public function count(ExistingMediaOutcome $outcome, ?OptimizationResult $result): void
    {
        match ($outcome) {
            ExistingMediaOutcome::AlreadyHandled => ++$this->alreadyHandled,
            ExistingMediaOutcome::Error => ++$this->errors,
            ExistingMediaOutcome::NotAnImage => null,
            ExistingMediaOutcome::Processed => ++$this->processed,
        };

        if ($result?->status === OptimizationStatus::Optimized) {
            ++$this->optimized;
            $this->savedBytes += $result->savedBytes();
        }
    }
}

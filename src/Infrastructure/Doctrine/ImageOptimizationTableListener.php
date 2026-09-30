<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Doctrine;

use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Eekes\Sulu\ImageOptimizerBundle\Domain\Entity\ImageOptimization;

/**
 * Applies the table name a project configured. The mapping attribute on the entity can only hold a
 * constant, so a project that prefixes its own tables differently overrides it here.
 */
final readonly class ImageOptimizationTableListener
{
    public function __construct(
        private string $tableName,
    ) {
    }

    public function __invoke(LoadClassMetadataEventArgs $eventArgs): void
    {
        $metadata = $eventArgs->getClassMetadata();

        if ($metadata->getName() !== ImageOptimization::class) {
            return;
        }

        if ($this->tableName === ImageOptimization::DEFAULT_TABLE_NAME) {
            return;
        }

        // Only the name is passed, so the indexes declared on the entity are kept.
        $metadata->setPrimaryTable(['name' => $this->tableName]);
    }
}

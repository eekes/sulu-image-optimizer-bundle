<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Domain\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Eekes\Sulu\ImageOptimizerBundle\Domain\Entity\ImageOptimization;
use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\OptimizationStatistics;
use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\OptimizationStatus;

/**
 * @extends ServiceEntityRepository<ImageOptimization>
 */
class ImageOptimizationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImageOptimization::class);
    }

    public function save(ImageOptimization $imageOptimization, bool $flush = true): void
    {
        $this->getEntityManager()->persist($imageOptimization);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @param list<ImageOptimization> $imageOptimizations
     */
    public function saveAll(array $imageOptimizations): void
    {
        foreach ($imageOptimizations as $imageOptimization) {
            $this->getEntityManager()->persist($imageOptimization);
        }

        $this->getEntityManager()->flush();
    }

    public function remove(ImageOptimization $imageOptimization, bool $flush = true): void
    {
        $this->getEntityManager()->remove($imageOptimization);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Deletes a selection and returns how many rows went. A DQL delete on purpose: the rows are a
     * log nothing refers to, so loading them into memory first only makes it slower.
     *
     * @param int[] $ids
     */
    public function deleteByIds(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        return (int) $this->createQueryBuilder('imageOptimization')
            ->delete()
            ->where('imageOptimization.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->execute();
    }

    /**
     * Whether a file version was already handled, so the command that goes over the existing media
     * library can be run again without doing - or logging - the same work twice.
     *
     * A failed attempt does not count: the cause may have been fixed since (a missing binary, too
     * little memory), and trying again is exactly what the next run is for.
     */
    public function hasHandled(int $mediaId, int $mediaVersion): bool
    {
        $count = $this->createQueryBuilder('imageOptimization')
            ->select('COUNT(imageOptimization.id)')
            ->where('IDENTITY(imageOptimization.media) = :mediaId')
            ->andWhere('imageOptimization.mediaVersion = :mediaVersion')
            ->andWhere('imageOptimization.status != :failed')
            ->setParameter('mediaId', $mediaId)
            ->setParameter('mediaVersion', $mediaVersion)
            ->setParameter('failed', OptimizationStatus::Failed->value)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $count > 0;
    }

    public function statistics(): OptimizationStatistics
    {
        /** @var list<array{status: string, entries: int|string, originalSize: null|int|string, finalSize: null|int|string}> $rows */
        $rows = $this->createQueryBuilder('imageOptimization')
            ->select('imageOptimization.status AS status')
            ->addSelect('COUNT(imageOptimization.id) AS entries')
            ->addSelect('SUM(imageOptimization.originalSize) AS originalSize')
            ->addSelect('SUM(imageOptimization.finalSize) AS finalSize')
            ->groupBy('imageOptimization.status')
            ->getQuery()
            ->getArrayResult();

        $countPerStatus = array_fill_keys(
            array_map(static fn (OptimizationStatus $status): string => $status->value, OptimizationStatus::cases()),
            0,
        );
        $count = 0;
        $originalSize = 0;
        $finalSize = 0;

        foreach ($rows as $row) {
            $countPerStatus[$row['status']] = (int) $row['entries'];
            $count += (int) $row['entries'];
            $originalSize += (int) $row['originalSize'];
            $finalSize += (int) $row['finalSize'];
        }

        return new OptimizationStatistics($count, $originalSize, $finalSize, $countPerStatus);
    }
}

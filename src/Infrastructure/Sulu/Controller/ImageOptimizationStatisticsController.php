<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Sulu\Controller;

use Eekes\Sulu\ImageOptimizerBundle\Application\Service\OptimizerToolsInterface;
use Eekes\Sulu\ImageOptimizerBundle\Application\Service\ToolStatus;
use Eekes\Sulu\ImageOptimizerBundle\Domain\Repository\ImageOptimizationRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The totals above the list, and whether the server has the tools the optimizer needs - the same
 * check as the eekes:image-optimizer:check command, for whoever has no shell on the server.
 */
final class ImageOptimizationStatisticsController extends AbstractImageOptimizationController
{
    public const string ROUTE = 'eekes_sulu_image_optimizer.get_image_optimization_statistics';

    public function __construct(
        private readonly ImageOptimizationRepository $imageOptimizationRepository,
        private readonly OptimizerToolsInterface $tools,
    ) {
    }

    #[Route(
        path: '/image-optimizations/statistics',
        name: self::ROUTE,
        methods: ['GET'],
    )]
    public function __invoke(): Response
    {
        return $this->json([
            'statistics' => $this->imageOptimizationRepository->statistics()->toArray(),
            'tools' => array_map(static fn (ToolStatus $status): array => $status->toArray(), $this->tools->status()),
        ]);
    }
}

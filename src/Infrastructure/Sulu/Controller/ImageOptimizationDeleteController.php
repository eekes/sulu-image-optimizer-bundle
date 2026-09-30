<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Sulu\Controller;

use Eekes\Sulu\ImageOptimizerBundle\Domain\Entity\ImageOptimization;
use Eekes\Sulu\ImageOptimizerBundle\Domain\Repository\ImageOptimizationRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ImageOptimizationDeleteController extends AbstractImageOptimizationController
{
    public const string ROUTE = 'eekes_sulu_image_optimizer.delete_image_optimization';

    public function __construct(
        private readonly ImageOptimizationRepository $imageOptimizationRepository,
    ) {
    }

    #[Route(
        path: '/image-optimizations/{id}',
        name: self::ROUTE,
        requirements: ['id' => '\d+'],
        methods: ['DELETE'],
    )]
    public function __invoke(ImageOptimization $imageOptimization): Response
    {
        $this->imageOptimizationRepository->remove($imageOptimization);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}

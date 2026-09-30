<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Sulu\Controller;

use Eekes\Sulu\ImageOptimizerBundle\Domain\Repository\ImageOptimizationRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Deletes a selection at once, the way the administration interface deletes a selection of any
 * resource: a DELETE on the collection with the ids as a comma separated query parameter.
 */
final class ImageOptimizationBatchDeleteController extends AbstractImageOptimizationController
{
    public const string ROUTE = 'eekes_sulu_image_optimizer.delete_image_optimizations';

    public function __construct(
        private readonly ImageOptimizationRepository $imageOptimizationRepository,
    ) {
    }

    #[Route(
        path: '/image-optimizations',
        name: self::ROUTE,
        methods: ['DELETE'],
    )]
    public function __invoke(Request $request): Response
    {
        $ids = array_values(array_filter(array_map(
            static fn (string $id): int => (int) trim($id),
            explode(',', $request->query->getString('ids')),
        )));

        if ($ids === []) {
            // Answering 204 here would report a delete that never happened.
            throw new BadRequestHttpException('The "ids" parameter is missing or holds no id.');
        }

        $this->imageOptimizationRepository->deleteByIds($ids);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}

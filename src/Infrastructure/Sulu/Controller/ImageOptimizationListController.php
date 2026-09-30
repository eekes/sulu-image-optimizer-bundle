<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Sulu\Controller;

use Eekes\Sulu\ImageOptimizerBundle\Domain\Entity\ImageOptimization;
use Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Sulu\Admin\ImageOptimizationAdmin;
use Sulu\Component\Rest\ListBuilder\Doctrine\DoctrineListBuilderFactoryInterface;
use Sulu\Component\Rest\ListBuilder\Metadata\FieldDescriptorFactoryInterface;
use Sulu\Component\Rest\ListBuilder\PaginatedRepresentation;
use Sulu\Component\Rest\RestHelperInterface;
use Sulu\Component\Security\Authentication\UserInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ImageOptimizationListController extends AbstractImageOptimizationController
{
    /**
     * Registered with Sulu as the list route of the resource by the container extension.
     */
    public const string ROUTE = 'eekes_sulu_image_optimizer.get_image_optimizations';

    public function __construct(
        private readonly RestHelperInterface $restHelper,
        private readonly FieldDescriptorFactoryInterface $fieldDescriptorFactory,
        private readonly DoctrineListBuilderFactoryInterface $listBuilderFactory,
    ) {
    }

    #[Route(
        path: '/image-optimizations',
        name: self::ROUTE,
        methods: ['GET'],
    )]
    public function __invoke(): Response
    {
        $listBuilder = $this->listBuilderFactory->create(ImageOptimization::class);
        $fieldDescriptors = $this->fieldDescriptorFactory->getFieldDescriptors(ImageOptimization::SULU_LIST_KEY);

        if ($fieldDescriptors === null) {
            throw new \LogicException(\sprintf('The list "%s" is not registered. Check that the bundle could add its list directory to sulu_admin.', ImageOptimization::SULU_LIST_KEY));
        }

        $this->restHelper->initializeListBuilder($listBuilder, $fieldDescriptors);

        // The link to the media needs these whether or not the columns are shown. Added after the
        // initialization, which selects only the columns the administration interface asked for.
        foreach (['mediaId', 'locale'] as $name) {
            if (isset($fieldDescriptors[$name])) {
                $listBuilder->addSelectField($fieldDescriptors[$name]);
            }
        }

        $rows = $listBuilder->execute();

        $list = new PaginatedRepresentation(
            array_map($this->withMediaLink(...), $rows),
            ImageOptimization::SULU_RESOURCE_KEY,
            (int) $listBuilder->getCurrentPage(),
            (int) $listBuilder->getLimit(),
            (int) $listBuilder->count(),
        );

        return $this->json($list->toArray());
    }

    /**
     * Adds what Sulu's detail_link item action reads to open the media of a row. The media detail
     * view has the locale in its URL, so the row brings the one the file was uploaded in, falling
     * back to the language of the administrator for entries that did not record one.
     *
     * @return array<mixed> a row as the list builder returns it, with the link added
     */
    private function withMediaLink(mixed $row): array
    {
        if (!\is_array($row)) {
            throw new \LogicException('The list builder returned a row that is not an array.');
        }

        if (($row['mediaId'] ?? null) === null) {
            return $row + ['mediaResourceKey' => null, 'mediaRouterAttributes' => []];
        }

        $locale = \is_string($row['locale'] ?? null) && $row['locale'] !== '' ? $row['locale'] : $this->administratorLocale();

        return $row + [
            'mediaResourceKey' => ImageOptimizationAdmin::MEDIA_LINK_RESOURCE_KEY,
            'mediaRouterAttributes' => ['locale' => $locale],
        ];
    }

    private function administratorLocale(): string
    {
        $user = $this->getUser();

        return $user instanceof UserInterface ? $user->getLocale() : 'en';
    }
}

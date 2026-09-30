<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Sulu\Admin;

use Eekes\Sulu\ImageOptimizerBundle\Domain\Entity\ImageOptimization;
use Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Sulu\Controller\ImageOptimizationStatisticsController;
use Sulu\Bundle\AdminBundle\Admin\Admin;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItem;
use Sulu\Bundle\AdminBundle\Admin\Navigation\NavigationItemCollection;
use Sulu\Bundle\AdminBundle\Admin\View\ListItemAction;
use Sulu\Bundle\AdminBundle\Admin\View\ToolbarAction;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactoryInterface;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Puts the log of optimized uploads under Settings.
 *
 * A log entry is never edited, so there is no form: the list holds everything, and each row links
 * to the media it is about. That link only appears for users who may open the media library, since
 * the view it leads to does not exist for anyone else.
 */
final class ImageOptimizationAdmin extends Admin
{
    public const string LIST_VIEW = 'eekes_image_optimizer.image_optimizations.list';

    /**
     * A resource key of its own that only knows the media detail view, so a row can open its media
     * through Sulu's detail_link item action without touching the configuration of Sulu's media
     * resource.
     */
    public const string MEDIA_LINK_RESOURCE_KEY = 'eekes_image_optimizer_media';
    public const string MEDIA_DETAIL_VIEW = 'sulu_media.form.details';

    private const string MEDIA_SECURITY_CONTEXT = 'sulu.media.collections';

    public function __construct(
        private readonly ViewBuilderFactoryInterface $viewBuilderFactory,
        private readonly SecurityCheckerInterface $securityChecker,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly int $navigationPosition,
    ) {
    }

    public function configureNavigationItems(NavigationItemCollection $navigationItemCollection): void
    {
        if (!$this->securityChecker->hasPermission(ImageOptimization::SULU_SECURITY_CONTEXT, PermissionTypes::VIEW)) {
            return;
        }

        $navigationItem = new NavigationItem('eekes_image_optimizer.image_optimizations');
        $navigationItem->setPosition($this->navigationPosition);
        $navigationItem->setView(self::LIST_VIEW);

        $navigationItemCollection->get(Admin::SETTINGS_NAVIGATION_ITEM)->addChild($navigationItem);
    }

    public function configureViews(ViewCollection $viewCollection): void
    {
        if (!$this->securityChecker->hasPermission(ImageOptimization::SULU_SECURITY_CONTEXT, PermissionTypes::VIEW)) {
            return;
        }

        $toolbarActions = [
            new ToolbarAction('eekes_image_optimizer.overview', [
                'statistics_url' => $this->urlGenerator->generate(ImageOptimizationStatisticsController::ROUTE),
            ]),
        ];

        $mayDelete = $this->securityChecker->hasPermission(ImageOptimization::SULU_SECURITY_CONTEXT, PermissionTypes::DELETE);

        if ($mayDelete) {
            $toolbarActions[] = new ToolbarAction('sulu_admin.delete');
        }

        $listView = $this->viewBuilderFactory
            ->createListViewBuilder(self::LIST_VIEW, '/image-optimizer/optimizations')
            ->setResourceKey(ImageOptimization::SULU_RESOURCE_KEY)
            ->setListKey(ImageOptimization::SULU_LIST_KEY)
            ->setTitle('eekes_image_optimizer.image_optimizations')
            ->addListAdapters(['table'])
            ->addToolbarActions($toolbarActions);

        if ($this->securityChecker->hasPermission(self::MEDIA_SECURITY_CONTEXT, PermissionTypes::VIEW)) {
            $listView->addItemActions([
                new ListItemAction('detail_link', [
                    'icon' => 'su-eye',
                    'resource_key_property' => 'mediaResourceKey',
                    'resource_id_property' => 'mediaId',
                    'resource_view_attributes_property' => 'mediaRouterAttributes',
                ]),
            ]);
        }

        if (!$mayDelete) {
            $listView->disableSelection();
        }

        $viewCollection->add($listView);
    }

    /**
     * @return array<string, array<string, array<string, string[]>>>
     */
    public function getSecurityContexts()
    {
        return [
            self::SULU_ADMIN_SECURITY_SYSTEM => [
                'Settings' => [
                    ImageOptimization::SULU_SECURITY_CONTEXT => [
                        PermissionTypes::VIEW,
                        PermissionTypes::DELETE,
                    ],
                ],
            ],
        ];
    }
}

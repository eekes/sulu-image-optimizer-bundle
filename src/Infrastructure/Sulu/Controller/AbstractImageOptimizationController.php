<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Sulu\Controller;

use Eekes\Sulu\ImageOptimizerBundle\Domain\Entity\ImageOptimization;
use Sulu\Component\Security\SecuredControllerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

/**
 * Centralises the security context, so every endpoint of the resource is guarded by the same
 * permission as the list in ImageOptimizationAdmin.
 */
abstract class AbstractImageOptimizationController extends AbstractController implements SecuredControllerInterface
{
    public function getSecurityContext(): string
    {
        return ImageOptimization::SULU_SECURITY_CONTEXT;
    }

    public function getLocale(Request $request): ?string
    {
        return $request->query->getString('locale') ?: null;
    }
}

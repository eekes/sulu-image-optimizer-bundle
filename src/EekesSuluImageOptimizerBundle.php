<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle;

use Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Symfony\DependencyInjection\EekesSuluImageOptimizerExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * The bundle follows the directory layout Symfony recommends for new bundles: src/ holds nothing
 * but PHP, and config/, translations/ and assets/ sit next to it at the root.
 *
 * getPath() is overridden because that layout parts ways with what the base class assumes: it is
 * what makes config/ and translations/ discoverable - the routing file a project imports and the
 * translations Symfony registers on its own both hang off it.
 */
class EekesSuluImageOptimizerBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    /**
     * The container extension is Symfony glue like everything else under Infrastructure, so it does
     * not sit in the DependencyInjection/ folder the base class would look in.
     */
    public function getContainerExtension(): ExtensionInterface
    {
        return new EekesSuluImageOptimizerExtension();
    }
}

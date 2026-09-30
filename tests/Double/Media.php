<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Tests\Double;

use Doctrine\ORM\Mapping as ORM;
use Sulu\Bundle\MediaBundle\Entity\Media as SuluMedia;

/**
 * Sulu's media, reduced to the one column the log refers to.
 *
 * It extends the real class, so everything the bundle reads from a media behaves the way it does
 * in a project; only the mapping is the test suite's own, since Sulu's lives in SuluMediaBundle.
 */
#[ORM\Entity]
#[ORM\Table(name: 'test_media')]
class Media extends SuluMedia
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    protected int $id;
}

<?php

declare(strict_types=1);

use Doctrine\Persistence\ManagerRegistry;
use Eekes\Sulu\ImageOptimizerBundle\Tests\Application\Kernel;

// Only the autoloader, not bootstrap.php: that one empties the kernel's var/ directory, and PHPStan
// runs this file in every one of its parallel workers - each would pull the cache out from under
// the others.
require \dirname(__DIR__).'/vendor/autoload.php';

// Its own environment, so the relation to MediaInterface is analysed unresolved: see config.yaml.
$kernel = new Kernel('phpstan', false);
$kernel->boot();

$registry = $kernel->getContainer()->get('doctrine');
\assert($registry instanceof ManagerRegistry);

return $registry->getManager();

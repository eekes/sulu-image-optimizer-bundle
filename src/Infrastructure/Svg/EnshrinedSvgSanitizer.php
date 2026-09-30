<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Svg;

use Eekes\Sulu\ImageOptimizerBundle\Application\Service\SvgSanitizerInterface;
use enshrined\svgSanitize\Sanitizer;

final readonly class EnshrinedSvgSanitizer implements SvgSanitizerInterface
{
    public function sanitize(string $svg): ?string
    {
        $sanitizer = new Sanitizer();
        // A reference to another domain is how an SVG loads a script or tracks who opens it.
        $sanitizer->removeRemoteReferences(true);

        $clean = $sanitizer->sanitize($svg);

        return \is_string($clean) && $clean !== '' ? $clean : null;
    }
}

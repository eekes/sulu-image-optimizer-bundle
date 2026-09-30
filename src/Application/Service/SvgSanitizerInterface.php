<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Application\Service;

/**
 * Removes what makes an SVG dangerous to serve: scripts, event handler attributes and references to
 * other domains. Sulu serves uploaded SVGs from the site's own domain, so an SVG with a script in it
 * is a stored XSS for anyone who opens its URL.
 */
interface SvgSanitizerInterface
{
    /**
     * @return null|string the cleaned document, or null when it is not a document that can be parsed
     */
    public function sanitize(string $svg): ?string;
}

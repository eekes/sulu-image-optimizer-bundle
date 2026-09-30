<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject;

/**
 * The image formats the bundle knows how to handle.
 *
 * The format is always detected from the content of a file, never from its name: an upload called
 * "photo.JPG" that is really a PNG has to be treated as a PNG, or the encoder writes the wrong
 * format and the file breaks.
 */
enum ImageFormat: string
{
    case Jpeg = 'jpg';
    case Png = 'png';
    case Gif = 'gif';
    case Webp = 'webp';
    case Avif = 'avif';
    case Svg = 'svg';

    private const array MIME_TYPES = [
        'image/jpeg' => self::Jpeg,
        'image/pjpeg' => self::Jpeg,
        'image/png' => self::Png,
        'image/gif' => self::Gif,
        'image/webp' => self::Webp,
        'image/avif' => self::Avif,
        'image/svg+xml' => self::Svg,
    ];

    public static function fromMimeType(string $mimeType): ?self
    {
        return self::MIME_TYPES[strtolower($mimeType)] ?? null;
    }

    /**
     * Reads a format from configuration, where "jpeg" and "JPG" mean the same as "jpg".
     */
    public static function fromName(string $name): ?self
    {
        $name = strtolower(trim($name));

        return self::tryFrom($name === 'jpeg' ? 'jpg' : $name);
    }

    /**
     * Formats where re-encoding at a lower quality is the whole point. Re-encoding a PNG or a GIF
     * gains nothing and usually makes the file larger, so those are left to the optimizer tools.
     */
    public function isLossy(): bool
    {
        return match ($this) {
            self::Jpeg, self::Webp, self::Avif => true,
            default => false,
        };
    }

    public function isRaster(): bool
    {
        return $this !== self::Svg;
    }

    /**
     * @return list<string>
     */
    public function mimeTypes(): array
    {
        return array_keys(array_filter(self::MIME_TYPES, fn (self $format): bool => $format === $this));
    }

    /**
     * @return list<string>
     */
    public static function allMimeTypes(): array
    {
        return array_keys(self::MIME_TYPES);
    }
}

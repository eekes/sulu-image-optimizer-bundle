<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Tests\Double;

/**
 * Real image files, drawn with GD when a test needs one, so the suite carries no binary fixtures and
 * every image has exactly the properties the test is about.
 */
final class Images
{
    /**
     * @var list<string>
     */
    private static array $created = [];

    /**
     * A photo-like JPEG: noise compresses badly, so re-encoding it at a lower quality really does
     * make it smaller, the way it does for a photo straight from a phone.
     *
     * @param int<1, max> $width
     * @param int<1, max> $height
     */
    public static function jpeg(int $width = 400, int $height = 300, int $quality = 100): string
    {
        $path = self::path('jpg');
        imagejpeg(self::noise($width, $height), $path, $quality);

        return $path;
    }

    /**
     * @param int<1, max> $width
     * @param int<1, max> $height
     */
    public static function png(int $width = 400, int $height = 300): string
    {
        $path = self::path('png');
        imagepng(self::noise($width, $height), $path, 0);

        return $path;
    }

    /**
     * @param int<1, max> $width
     * @param int<1, max> $height
     */
    public static function webp(int $width = 400, int $height = 300): string
    {
        $path = self::path('webp');
        imagewebp(self::noise($width, $height), $path, 100);

        return $path;
    }

    /**
     * @param int<1, max> $width
     * @param int<1, max> $height
     */
    public static function gif(int $width = 40, int $height = 30): string
    {
        $path = self::path('gif');
        imagegif(self::noise($width, $height), $path);

        return $path;
    }

    /**
     * Two 4x4 frames, red and blue, as gifsicle writes them. Built once and embedded, because putting
     * frames together by hand means parsing GD's colour tables.
     */
    public static function animatedGif(): string
    {
        $path = self::path('gif');
        file_put_contents($path, base64_decode('R0lGODlhBAAEAPAAAP8AAAAA/yH5BAAKAP8ALAAAAAAEAAQAAAIEhI8JBQAh+QQACgD/ACwAAAAABAAEAAACBIyPGQUAOw==', true));

        return $path;
    }

    public static function svg(string $body = '<rect width="10" height="10"/>'): string
    {
        $path = self::path('svg');
        file_put_contents($path, '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10">'.$body.'</svg>');

        return $path;
    }

    public static function text(string $contents = 'Not an image at all.', string $extension = 'txt'): string
    {
        $path = self::path($extension);
        file_put_contents($path, $contents);

        return $path;
    }

    public static function cleanUp(): void
    {
        foreach (self::$created as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }

        self::$created = [];
    }

    /**
     * @param int<1, max> $width
     * @param int<1, max> $height
     */
    private static function noise(int $width, int $height): \GdImage
    {
        $image = imagecreatetruecolor($width, $height);
        \assert($image instanceof \GdImage);
        mt_srand($width * $height);

        for ($x = 0; $x < $width; ++$x) {
            for ($y = 0; $y < $height; ++$y) {
                $colour = imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
                \assert(\is_int($colour));
                imagesetpixel($image, $x, $y, $colour);
            }
        }

        return $image;
    }

    private static function path(string $extension): string
    {
        $temporary = tempnam(sys_get_temp_dir(), 'eekes_test_');
        \assert($temporary !== false);
        $path = $temporary.'.'.$extension;
        rename($temporary, $path);
        self::$created[] = $path;

        return $path;
    }
}

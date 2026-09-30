<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Application\Service;

use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\Dimensions;
use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\ImageFormat;
use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\OptimizationResult;
use Psr\Log\LoggerInterface;

/**
 * Optimizes one file in place and reports what it did.
 *
 * Every step works on a copy. The file at the given path is only replaced once the copy turned out
 * smaller (or had to be resized), so whatever goes wrong halfway, the original is what gets stored.
 * That is also why nothing here throws: a failure is a result like any other.
 */
final readonly class ImageOptimizer
{
    public function __construct(
        private OptimizerSettings $settings,
        private ImageEncoderInterface $encoder,
        private OptimizerToolsInterface $tools,
        private SvgSanitizerInterface $svgSanitizer,
        private MemoryBudget $memoryBudget,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return null|OptimizationResult null when the file is not an image this bundle handles
     */
    public function optimize(string $path): ?OptimizationResult
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $format = self::detectFormat($path);

        if ($format === null) {
            return null;
        }

        if ($format === ImageFormat::Svg) {
            return $this->settings->sanitizeSvg ? $this->sanitizeSvg($path) : null;
        }

        $size = self::sizeOf($path);
        $dimensions = self::dimensionsOf($path);

        if ($this->settings->ignores($format)) {
            return OptimizationResult::skipped($format, $size, $dimensions, \sprintf('The format "%s" is in ignore_types.', $format->value));
        }

        $animated = self::isAnimated($path, $format);

        if ($animated && $format === ImageFormat::Webp) {
            // GD decodes only the first frame and cwebp cannot read an animation: whatever ran would
            // turn the animation into a still.
            return OptimizationResult::skipped($format, $size, $dimensions, 'Animated WebP images are left alone.');
        }

        return $this->optimizeRaster($path, $format, $size, $dimensions, $animated);
    }

    private function optimizeRaster(string $path, ImageFormat $format, int $size, ?Dimensions $dimensions, bool $animated): OptimizationResult
    {
        $start = hrtime(true);
        $tools = [];
        $messages = [];
        $finalDimensions = $dimensions;
        $work = null;

        try {
            $work = self::copyToTemporaryFile($path, $format);
            $resizeTo = $this->resizeTarget($dimensions);

            if ($animated && $resizeTo !== null) {
                // Resizing through GD keeps only the first frame, which is worse than a large file.
                $messages[] = 'Animated image, so it was not resized.';
                $resizeTo = null;
            }

            $reencode = !$animated && ($resizeTo !== null || ($format->isLossy() && $this->settings->stripMetadata));

            if ($reencode && !$this->encoder->supports($format)) {
                $messages[] = \sprintf('GD on this server cannot write %s, so the image was not re-encoded.', $format->value);
                $reencode = false;
                $resizeTo = null;
            }

            if ($reencode && $dimensions !== null && !$this->memoryBudget->allows($dimensions, $resizeTo)) {
                return OptimizationResult::failed(
                    $format,
                    $size,
                    $dimensions,
                    \sprintf('A %dx%d image needs more memory than PHP may use here. The original was kept.', $dimensions->width, $dimensions->height),
                    $tools,
                    self::millisecondsSince($start),
                );
            }

            if ($reencode) {
                $finalDimensions = $this->encoder->encode($work, $format, $this->settings->quality($format), $resizeTo);
                $tools[] = 'gd';
            }

            $tools = [...$tools, ...$this->tools->optimize($work, $format)];
            $finalSize = self::sizeOf($work);
            $resized = $finalDimensions !== null && $dimensions !== null && !$finalDimensions->equals($dimensions);
            $message = $messages === [] ? null : implode(' ', $messages);

            // A resized image is kept even in the unlikely case it grew: the maximum size is a limit
            // the project asked for, not a suggestion.
            if ($finalSize >= $size && !$resized) {
                return OptimizationResult::unchanged($format, $size, $dimensions, $tools, self::millisecondsSince($start), $message);
            }

            self::replace($path, $work);

            return OptimizationResult::optimized($format, $size, $finalSize, $dimensions, $finalDimensions, $tools, self::millisecondsSince($start), $message);
        } catch (\Throwable $exception) {
            $this->logger->error('Optimizing {path} failed: {message}', [
                'path' => $path,
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            return OptimizationResult::failed($format, $size, $dimensions, $exception->getMessage(), $tools, self::millisecondsSince($start));
        } finally {
            if ($work !== null && is_file($work)) {
                unlink($work);
            }
        }
    }

    private function sanitizeSvg(string $path): OptimizationResult
    {
        $start = hrtime(true);
        $size = self::sizeOf($path);
        $tools = ['svg-sanitizer'];
        $work = null;

        try {
            $dirty = file_get_contents($path);
            $clean = $dirty === false ? null : $this->svgSanitizer->sanitize($dirty);

            if ($clean === null) {
                // Stored as it is: refusing the upload is not this bundle's call to make, but it has
                // to be visible in the log that this SVG was not cleaned.
                return OptimizationResult::failed(ImageFormat::Svg, $size, null, 'The SVG could not be parsed, so it was not sanitized.', $tools, self::millisecondsSince($start));
            }

            $work = self::copyToTemporaryFile($path, ImageFormat::Svg);
            file_put_contents($work, $clean);
            $tools = [...$tools, ...$this->tools->optimize($work, ImageFormat::Svg)];

            if (file_get_contents($work) === $dirty) {
                return OptimizationResult::unchanged(ImageFormat::Svg, $size, null, $tools, self::millisecondsSince($start));
            }

            $finalSize = self::sizeOf($work);
            // Unlike a raster image, a cleaned SVG is kept even when it grew: the cleaning is the point.
            self::replace($path, $work);

            return OptimizationResult::optimized(ImageFormat::Svg, $size, $finalSize, null, null, $tools, self::millisecondsSince($start));
        } catch (\Throwable $exception) {
            $this->logger->error('Sanitizing {path} failed: {message}', [
                'path' => $path,
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            return OptimizationResult::failed(ImageFormat::Svg, $size, null, $exception->getMessage(), $tools, self::millisecondsSince($start));
        } finally {
            if ($work !== null && is_file($work)) {
                unlink($work);
            }
        }
    }

    private function resizeTarget(?Dimensions $dimensions): ?Dimensions
    {
        if (!$this->settings->resizeEnabled || $dimensions === null) {
            return null;
        }

        $target = $dimensions->fitWithin($this->settings->maxSize);

        return $target->equals($dimensions) ? null : $target;
    }

    /**
     * The format as the content says it is, whatever the file name claims.
     */
    public static function detectFormat(string $path): ?ImageFormat
    {
        $imageInfo = @getimagesize($path);

        if (\is_array($imageInfo)) {
            return ImageFormat::fromMimeType($imageInfo['mime']);
        }

        return self::isSvg($path) ? ImageFormat::Svg : null;
    }

    /**
     * getimagesize() does not know SVG, which is XML and has to be recognised by its content: a
     * document whose root element is <svg>.
     *
     * The root is found by a streaming parser rather than by looking at the first few kilobytes.
     * Padding the prolog with a long comment would otherwise be enough to get a script past the
     * sanitizer, and a browser still renders such a file as an SVG - also when it is served as XML.
     */
    private static function isSvg(string $path): bool
    {
        $head = file_get_contents($path, false, null, 0, 4096);

        // Rules out everything else a media library holds - PDFs, videos, archives - without parsing.
        if ($head === false || !str_starts_with(ltrim($head, "\xEF\xBB\xBF \t\r\n"), '<')) {
            return false;
        }

        $reader = new \XMLReader();
        $useInternalErrors = libxml_use_internal_errors(true);

        try {
            if (!$reader->open($path, null, \LIBXML_NONET)) {
                return false;
            }

            while ($reader->read()) {
                if ($reader->nodeType === \XMLReader::ELEMENT) {
                    return $reader->localName === 'svg';
                }
            }

            // Not well-formed before the root element: no browser renders that, but it still has to
            // show up in the log as an SVG that could not be sanitized.
            return preg_match('/<svg[\s>]/i', $head) === 1;
        } finally {
            $reader->close();
            libxml_clear_errors();
            libxml_use_internal_errors($useInternalErrors);
        }
    }

    private static function dimensionsOf(string $path): ?Dimensions
    {
        $imageInfo = @getimagesize($path);

        if (!\is_array($imageInfo) || $imageInfo[0] < 1 || $imageInfo[1] < 1) {
            return null;
        }

        return new Dimensions($imageInfo[0], $imageInfo[1]);
    }

    private static function isAnimated(string $path, ImageFormat $format): bool
    {
        $contents = match ($format) {
            ImageFormat::Gif, ImageFormat::Webp => file_get_contents($path),
            default => false,
        };

        if ($contents === false) {
            return false;
        }

        if ($format === ImageFormat::Webp) {
            // An extended WebP (VP8X) carries its animation flag in the byte after the chunk header.
            return substr($contents, 12, 4) === 'VP8X' && (\ord($contents[20] ?? "\0") & 0x02) === 0x02;
        }

        // Every frame of an animated GIF starts with a graphic control extension (21 F9 04, four
        // bytes, a terminator) directly followed by its image descriptor or another extension.
        return preg_match_all('/\x21\xF9\x04.{4}\x00[\x2C\x21]/s', $contents) > 1;
    }

    private static function copyToTemporaryFile(string $path, ImageFormat $format): string
    {
        $temporary = tempnam(sys_get_temp_dir(), 'eekes_image_');

        if ($temporary === false) {
            throw new \RuntimeException('No temporary file could be created.');
        }

        // The optimizer tools and GD pick the format partly by extension, so the copy gets the right one.
        $work = $temporary.'.'.$format->value;

        if (!rename($temporary, $work) || !copy($path, $work)) {
            throw new \RuntimeException(\sprintf('The file could not be copied to "%s".', $work));
        }

        return $work;
    }

    /**
     * Writes the optimized copy over the original. Copying instead of renaming keeps the original's
     * inode and permissions, which matters for the upload PHP still holds a handle to.
     */
    private static function replace(string $path, string $work): void
    {
        if (!copy($work, $path)) {
            throw new \RuntimeException(\sprintf('The optimized file could not be written to "%s".', $path));
        }

        // The size of the original is cached by PHP, and Sulu reads the size of the upload after this.
        clearstatcache(true, $path);
    }

    private static function sizeOf(string $path): int
    {
        clearstatcache(true, $path);
        $size = filesize($path);

        return $size === false ? 0 : $size;
    }

    private static function millisecondsSince(int|float $start): int
    {
        return (int) round((hrtime(true) - $start) / 1_000_000);
    }
}

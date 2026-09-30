<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Spatie;

use Eekes\Sulu\ImageOptimizerBundle\Application\Service\ImageEncoderInterface;
use Eekes\Sulu\ImageOptimizerBundle\Application\Service\OptimizerSettings;
use Eekes\Sulu\ImageOptimizerBundle\Application\Service\OptimizerToolsInterface;
use Eekes\Sulu\ImageOptimizerBundle\Application\Service\ToolStatus;
use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\ImageFormat;
use Psr\Log\LoggerInterface;
use Spatie\ImageOptimizer\OptimizerChain;
use Spatie\ImageOptimizer\Optimizers\Avifenc;
use Spatie\ImageOptimizer\Optimizers\BaseOptimizer;
use Spatie\ImageOptimizer\Optimizers\Cwebp;
use Spatie\ImageOptimizer\Optimizers\Gifsicle;
use Spatie\ImageOptimizer\Optimizers\Jpegoptim;
use Spatie\ImageOptimizer\Optimizers\Optipng;
use Spatie\ImageOptimizer\Optimizers\Pngquant;
use Spatie\ImageOptimizer\Optimizers\Svgo;
use Symfony\Component\Process\ExecutableFinder;

/**
 * The optimizer binaries of spatie/image-optimizer, limited to the ones this server has.
 *
 * Spatie's own chain runs every optimizer and logs a failure for each binary that is missing. The
 * chain is built here instead, from the binaries that were actually found: that is what makes the
 * list of tools in the log true, and what lets a binary outside the PATH of PHP be used at all.
 */
final class SpatieOptimizerTools implements OptimizerToolsInterface
{
    /**
     * @var array<string, null|string> binary name => directory it was found in, null when missing
     */
    private array $found = [];

    public function __construct(
        private readonly OptimizerSettings $settings,
        private readonly ImageEncoderInterface $encoder,
        private readonly LoggerInterface $logger,
        private readonly ?string $binaryPath = null,
        private readonly int $timeout = 60,
        private readonly ExecutableFinder $executableFinder = new ExecutableFinder(),
    ) {
    }

    public function optimize(string $path, ImageFormat $format): array
    {
        // Spatie's fluent methods are untyped, so the chain is configured step by step.
        $chain = new OptimizerChain();
        $chain->useLogger($this->logger);
        $chain->setTimeout($this->timeout);

        $used = [];

        foreach ($this->definitions() as $name => $definition) {
            if (!\in_array($format, $definition['formats'], true) || !$this->hasAll($definition['binaries'])) {
                continue;
            }

            $optimizer = $definition['optimizer']();
            $optimizer->setBinaryPath($this->directoryOf($definition['binaries'][0]) ?? '');
            $chain->addOptimizer($optimizer);
            $used[] = $name;
        }

        if ($used !== []) {
            $chain->optimize($path);
        }

        return $used;
    }

    public function status(): array
    {
        $statuses = [
            new ToolStatus('gd', [ImageFormat::Jpeg, ImageFormat::Png, ImageFormat::Gif], \extension_loaded('gd')),
            new ToolStatus('gd-webp', [ImageFormat::Webp], $this->encoder->supports(ImageFormat::Webp)),
            new ToolStatus('gd-avif', [ImageFormat::Avif], $this->encoder->supports(ImageFormat::Avif)),
        ];

        foreach ($this->definitions() as $name => $definition) {
            if ($definition['formats'] === [ImageFormat::Svg] && !$this->settings->sanitizeSvg) {
                continue;
            }

            $directory = $this->directoryOf($definition['binaries'][0]);

            $statuses[] = new ToolStatus(
                $name,
                $definition['formats'],
                $this->hasAll($definition['binaries']),
                $directory === null ? null : $directory.\DIRECTORY_SEPARATOR.$definition['binaries'][0],
            );
        }

        return $statuses;
    }

    /**
     * The options mirror the defaults of spatie/image-optimizer, with the quality and the metadata
     * handling taken from the bundle configuration.
     *
     * @return array<string, array{formats: list<ImageFormat>, binaries: non-empty-list<string>, optimizer: callable(): BaseOptimizer}>
     */
    private function definitions(): array
    {
        $jpeg = $this->settings->quality(ImageFormat::Jpeg);
        $png = $this->settings->quality(ImageFormat::Png);
        $webp = $this->settings->quality(ImageFormat::Webp);
        $avif = $this->settings->quality(ImageFormat::Avif);
        $strip = $this->settings->stripMetadata;

        return [
            'jpegoptim' => [
                'formats' => [ImageFormat::Jpeg],
                'binaries' => ['jpegoptim'],
                'optimizer' => static fn (): BaseOptimizer => new Jpegoptim(['-m'.$jpeg, '--force', $strip ? '--strip-all' : '--strip-none', '--all-progressive']),
            ],
            'pngquant' => [
                'formats' => [ImageFormat::Png],
                'binaries' => ['pngquant'],
                'optimizer' => static fn (): BaseOptimizer => new Pngquant(['--quality=0-'.$png, '--force', '--skip-if-larger']),
            ],
            'optipng' => [
                'formats' => [ImageFormat::Png],
                'binaries' => ['optipng'],
                'optimizer' => static fn (): BaseOptimizer => new Optipng($strip ? ['-i0', '-o2', '-quiet', '-strip all'] : ['-i0', '-o2', '-quiet']),
            ],
            'gifsicle' => [
                'formats' => [ImageFormat::Gif],
                'binaries' => ['gifsicle'],
                'optimizer' => static fn (): BaseOptimizer => new Gifsicle(['-b', '-O3']),
            ],
            'cwebp' => [
                'formats' => [ImageFormat::Webp],
                'binaries' => ['cwebp'],
                'optimizer' => static fn (): BaseOptimizer => new Cwebp(['-m 6', '-pass 10', '-mt', '-q '.$webp, $strip ? '-metadata none' : '-metadata all']),
            ],
            'avifenc' => [
                'formats' => [ImageFormat::Avif],
                // Spatie decodes to PNG with avifdec first and encodes that again.
                'binaries' => ['avifenc', 'avifdec'],
                'optimizer' => static fn (): BaseOptimizer => new Avifenc([
                    '-a cq-level='.(int) round(63 - $avif * 0.63),
                    '-j all',
                    '--min 0',
                    '--max 63',
                    '--minalpha 0',
                    '--maxalpha 63',
                    '-a end-usage=q',
                    '-a tune=ssim',
                ]),
            ],
            'svgo' => [
                'formats' => [ImageFormat::Svg],
                'binaries' => ['svgo'],
                'optimizer' => static fn (): BaseOptimizer => new Svgo([]),
            ],
        ];
    }

    /**
     * @param list<string> $binaries
     */
    private function hasAll(array $binaries): bool
    {
        foreach ($binaries as $binary) {
            if ($this->directoryOf($binary) === null) {
                return false;
            }
        }

        return true;
    }

    private function directoryOf(string $binary): ?string
    {
        if (!\array_key_exists($binary, $this->found)) {
            // A configured directory wins over the PATH: it is set precisely because the binary on
            // the PATH is missing, or is not the one to use.
            $configured = $this->binaryPath === null ? null : rtrim($this->binaryPath, \DIRECTORY_SEPARATOR).\DIRECTORY_SEPARATOR.$binary;
            $path = $configured !== null && is_file($configured) && is_executable($configured)
                ? $configured
                : $this->executableFinder->find($binary);

            $this->found[$binary] = $path === null ? null : \dirname($path);
        }

        return $this->found[$binary];
    }
}

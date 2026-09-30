<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Tests\Unit;

use Eekes\Sulu\ImageOptimizerBundle\Application\Service\OptimizerSettings;
use Eekes\Sulu\ImageOptimizerBundle\Application\Service\ToolStatus;
use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\ImageFormat;
use Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Spatie\SpatieImageEncoder;
use Eekes\Sulu\ImageOptimizerBundle\Infrastructure\Spatie\SpatieOptimizerTools;
use Eekes\Sulu\ImageOptimizerBundle\Tests\Double\NothingOnThePath;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Finding and running the optimizer binaries, with a stand-in "jpegoptim" that is a shell script:
 * the test has to give the same answer whatever the machine running it has installed.
 */
#[CoversClass(SpatieOptimizerTools::class)]
final class SpatieOptimizerToolsTest extends TestCase
{
    private string $binaries;
    private string $image;

    protected function setUp(): void
    {
        $this->binaries = sys_get_temp_dir().'/eekes_binaries_'.bin2hex(random_bytes(4));
        mkdir($this->binaries);

        // Appends a marker to the file it is given, so the test can see it ran and on what.
        $script = $this->binaries.'/jpegoptim';
        file_put_contents($script, "#!/bin/sh\nfor last; do true; done\nprintf 'ran' >> \"\$last\"\n");
        chmod($script, 0o755);

        $this->image = $this->binaries.'/photo.jpg';
        $gd = imagecreatetruecolor(4, 4);
        \assert($gd instanceof \GdImage);
        imagejpeg($gd, $this->image);
    }

    protected function tearDown(): void
    {
        foreach ((array) glob($this->binaries.'/*') as $file) {
            unlink((string) $file);
        }

        rmdir($this->binaries);
    }

    /**
     * On shared hosting the binaries often live in the home directory, outside the PATH that PHP
     * runs with - which is exactly where the PATH cannot be relied on to find them.
     */
    public function testABinaryInTheConfiguredDirectoryIsFoundAndUsed(): void
    {
        $tools = $this->tools($this->binaries);

        $used = $tools->optimize($this->image, ImageFormat::Jpeg);

        self::assertSame(['jpegoptim'], $used);
        self::assertStringEndsWith('ran', (string) file_get_contents($this->image));
        self::assertSame($this->binaries.'/jpegoptim', $this->statusOf($tools, 'jpegoptim')->path);
    }

    public function testNothingRunsForAFormatWithoutAnyAvailableTool(): void
    {
        $tools = $this->tools($this->binaries);

        self::assertSame([], $tools->optimize($this->image, ImageFormat::Gif));
    }

    public function testAMissingBinaryIsReportedAsMissing(): void
    {
        $status = $this->statusOf($this->tools(null), 'jpegoptim');

        self::assertFalse($status->available);
        self::assertNull($status->path);
    }

    public function testGdIsReportedAsATool(): void
    {
        self::assertTrue($this->statusOf($this->tools(null), 'gd')->available);
    }

    public function testSvgoIsNotReportedWhenSvgsAreNotHandled(): void
    {
        $names = array_map(
            static fn (ToolStatus $status): string => $status->name,
            $this->tools(null, new OptimizerSettings(sanitizeSvg: false))->status(),
        );

        self::assertNotContains('svgo', $names);
    }

    private function tools(?string $binaryPath, OptimizerSettings $settings = new OptimizerSettings()): SpatieOptimizerTools
    {
        return new SpatieOptimizerTools(
            $settings,
            new SpatieImageEncoder(),
            new NullLogger(),
            $binaryPath,
            10,
            new NothingOnThePath(),
        );
    }

    private function statusOf(SpatieOptimizerTools $tools, string $name): ToolStatus
    {
        foreach ($tools->status() as $status) {
            if ($status->name === $name) {
                return $status;
            }
        }

        self::fail(\sprintf('"%s" is not reported at all.', $name));
    }
}

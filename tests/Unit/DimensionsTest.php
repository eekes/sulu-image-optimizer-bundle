<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Tests\Unit;

use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\Dimensions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Dimensions::class)]
final class DimensionsTest extends TestCase
{
    /**
     * @return iterable<string, array{Dimensions, int, Dimensions}>
     */
    public static function images(): iterable
    {
        yield 'landscape' => [new Dimensions(8000, 6000), 4000, new Dimensions(4000, 3000)];
        yield 'portrait' => [new Dimensions(3000, 6000), 4000, new Dimensions(2000, 4000)];
        yield 'square' => [new Dimensions(5000, 5000), 4000, new Dimensions(4000, 4000)];
        yield 'already small enough' => [new Dimensions(1200, 800), 4000, new Dimensions(1200, 800)];
        yield 'exactly the maximum' => [new Dimensions(4000, 10), 4000, new Dimensions(4000, 10)];
        yield 'a sliver never becomes zero pixels' => [new Dimensions(10000, 1), 100, new Dimensions(100, 1)];
    }

    #[DataProvider('images')]
    public function testTheLongestSideEndsUpAtMostTheMaximum(Dimensions $dimensions, int $maxSize, Dimensions $expected): void
    {
        self::assertEquals($expected, $dimensions->fitWithin($maxSize));
    }
}

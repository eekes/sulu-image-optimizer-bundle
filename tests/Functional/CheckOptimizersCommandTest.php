<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Tests\Functional;

use Eekes\Sulu\ImageOptimizerBundle\Application\Command\CheckOptimizersCommand;
use Eekes\Sulu\ImageOptimizerBundle\Application\Service\ToolStatus;
use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\ImageFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(CheckOptimizersCommand::class)]
final class CheckOptimizersCommandTest extends FunctionalTestCase
{
    public function testAMissingBinaryIsNamed(): void
    {
        $this->tools()->statuses = [
            new ToolStatus('jpegoptim', [ImageFormat::Jpeg], true, '/usr/bin/jpegoptim'),
            new ToolStatus('pngquant', [ImageFormat::Png], false),
        ];

        $tester = $this->check([]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode(), 'Missing a binary is not an error of the command on its own.');
        self::assertStringContainsString('/usr/bin/jpegoptim', $tester->getDisplay());
        self::assertStringContainsString('Missing: pngquant', $tester->getDisplay());
    }

    public function testADeployScriptCanFailOnAMissingBinary(): void
    {
        $this->tools()->statuses = [new ToolStatus('pngquant', [ImageFormat::Png], false)];

        self::assertSame(Command::FAILURE, $this->check(['--strict' => true])->getStatusCode());
    }

    public function testNothingMissingIsASuccessEvenWhenStrict(): void
    {
        $this->tools()->statuses = [new ToolStatus('jpegoptim', [ImageFormat::Jpeg], true, '/usr/bin/jpegoptim')];

        self::assertSame(Command::SUCCESS, $this->check(['--strict' => true])->getStatusCode());
    }

    /**
     * @param array<string, mixed> $input
     */
    private function check(array $input): CommandTester
    {
        $command = self::getContainer()->get('eekes_test.check_command');
        \assert($command instanceof Command);

        $tester = new CommandTester($command);
        $tester->execute($input);

        return $tester;
    }
}

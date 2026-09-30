<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Application\Command;

use Eekes\Sulu\ImageOptimizerBundle\Application\Service\OptimizerToolsInterface;
use Eekes\Sulu\ImageOptimizerBundle\Application\Service\ToolStatus;
use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\ImageFormat;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Shows which optimizer binaries this server has.
 *
 * A missing binary is not an error anywhere else: the optimizer quietly skips it, so on a host
 * without jpegoptim every JPEG is only re-encoded by GD and nobody finds out. Running this after a
 * deploy, or with --strict in a deploy script, is how it does get noticed.
 */
#[AsCommand(
    name: 'eekes:image-optimizer:check',
    description: 'Shows which image optimizer binaries are available on this server',
)]
final class CheckOptimizersCommand extends Command
{
    public function __construct(
        private readonly OptimizerToolsInterface $tools,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('strict', null, InputOption::VALUE_NONE, 'Fail when anything is missing, for use in a deploy script');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $statuses = $this->tools->status();

        $io->table(
            ['Tool', 'Formats', 'Available', 'Path'],
            array_map(static fn (ToolStatus $status): array => [
                $status->name,
                implode(', ', array_map(static fn (ImageFormat $format): string => $format->value, $status->formats)),
                $status->available ? '<info>yes</info>' : '<comment>no</comment>',
                $status->path ?? '',
            ], $statuses),
        );

        $missing = array_values(array_filter($statuses, static fn (ToolStatus $status): bool => !$status->available));

        if ($missing === []) {
            $io->success('Everything the optimizer can use is available.');

            return Command::SUCCESS;
        }

        $io->warning(\sprintf(
            'Missing: %s. Images of those formats are still stored, just without that step. Install them, or set binary_path when they live outside the PATH of PHP.',
            implode(', ', array_map(static fn (ToolStatus $status): string => $status->name, $missing)),
        ));

        return $input->getOption('strict') === true ? Command::FAILURE : Command::SUCCESS;
    }
}

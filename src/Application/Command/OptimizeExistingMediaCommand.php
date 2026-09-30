<?php

declare(strict_types=1);

namespace Eekes\Sulu\ImageOptimizerBundle\Application\Command;

use Eekes\Sulu\ImageOptimizerBundle\Application\Service\ExistingMediaOptimizer;
use Eekes\Sulu\ImageOptimizerBundle\Application\Service\ExistingMediaOutcome;
use Eekes\Sulu\ImageOptimizerBundle\Application\Service\StoredImage;
use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\OptimizationResult;
use Eekes\Sulu\ImageOptimizerBundle\Domain\ValueObject\OptimizationStatus;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Helper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'eekes:image-optimizer:optimize-existing',
    description: 'Optimizes the images that are already in the media library, as a new version of each media',
)]
final class OptimizeExistingMediaCommand extends Command
{
    public function __construct(
        private readonly ExistingMediaOptimizer $existingMediaOptimizer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be saved without storing anything')
            ->addOption('collection', null, InputOption::VALUE_REQUIRED, 'Only the media of this collection id')
            ->addOption('min-size', null, InputOption::VALUE_REQUIRED, 'Only files of at least this many kilobytes', '0')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Stop after this many images')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Also process images the log says were already handled')
            ->setHelp(<<<'HELP'
                Goes over every image in the media library that the log has no entry for yet and runs the
                optimizer over it. An image that gets smaller is stored as a <info>new version</info> of its
                media, so the original stays in the media's history and can be restored from there.

                Start with <info>--dry-run</info> to see what it would save, and use <info>--limit</info> to
                spread a large library over several runs. Running it again skips what is already done.
                HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run') === true;

        $collectionId = self::optionalPositiveInt($input->getOption('collection'), 'collection');
        $limit = self::optionalPositiveInt($input->getOption('limit'), 'limit');
        $minimumSize = (self::optionalPositiveInt($input->getOption('min-size'), 'min-size') ?? 0) * 1024;

        if ($dryRun) {
            $io->note('Dry run: nothing is stored and nothing is logged.');
        }

        $summary = $this->existingMediaOptimizer->run(
            static function (StoredImage $image, ExistingMediaOutcome $outcome, ?OptimizationResult $result, ?string $error) use ($io): void {
                $line = self::describe($image, $outcome, $result, $error);

                if ($line !== null) {
                    $io->writeln($line);
                }
            },
            dryRun: $dryRun,
            collectionId: $collectionId,
            minimumSize: $minimumSize,
            limit: $limit,
            force: $input->getOption('force') === true,
        );

        $io->newLine();
        $io->definitionList(
            ['Processed' => (string) $summary->processed],
            [($dryRun ? 'Would be optimized' : 'Optimized') => (string) $summary->optimized],
            [($dryRun ? 'Would be saved' : 'Saved') => Helper::formatMemory($summary->savedBytes)],
            ['Already handled before' => (string) $summary->alreadyHandled],
            ['Errors' => (string) $summary->errors],
        );

        return $summary->errors === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    private static function describe(StoredImage $image, ExistingMediaOutcome $outcome, ?OptimizationResult $result, ?string $error): ?string
    {
        $name = \sprintf('#%d %s', $image->mediaId, $image->fileName);

        return match ($outcome) {
            ExistingMediaOutcome::AlreadyHandled, ExistingMediaOutcome::NotAnImage => null,
            ExistingMediaOutcome::Error => \sprintf('<error>%s: %s</error>', $name, $error ?? 'unknown error'),
            ExistingMediaOutcome::Processed => match ($result?->status) {
                OptimizationStatus::Optimized => \sprintf(
                    '<info>%s</info>: %s → %s (-%s%%)',
                    $name,
                    Helper::formatMemory($result->originalSize),
                    Helper::formatMemory($result->finalSize),
                    $result->savedPercentage(),
                ),
                OptimizationStatus::Failed => \sprintf('<comment>%s: %s</comment>', $name, $result->message),
                default => \sprintf('%s: %s', $name, $result?->status->value ?? ''),
            },
        };
    }

    private static function optionalPositiveInt(mixed $value, string $option): ?int
    {
        if ($value === null) {
            return null;
        }

        if (!\is_string($value) || !ctype_digit($value)) {
            throw new \InvalidArgumentException(\sprintf('The --%s option expects a positive whole number.', $option));
        }

        return (int) $value;
    }
}

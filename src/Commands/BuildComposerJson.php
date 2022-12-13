<?php

declare(strict_types = 1);

namespace App\Commands;

use App\ConstraintParser;
use App\FileSystem;
use App\Http\ProjectReleaseFetcher;
use App\Http\UpdateFetcher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use function App\time;

#[AsCommand(name: 'build:composer')]
final class BuildComposerJson extends Command
{
    public function __construct(
        private readonly FileSystem $fileSystem,
        private readonly ProjectReleaseFetcher $projectManager,
        private readonly UpdateFetcher $releaseFetcher
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->addArgument(
                'category',
                description: 'The release category. Enter "legacy" for Drupal 7 and "current" for Drupal 8+.',
                default: 'current'
            );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $versionParser = new ConstraintParser();
        $category = $input->getArgument('category');

        ['output' => $target, 'release' => $release] = match ($category) {
            'legacy' => [
                'output' => 'composer-7.x.json',
                'release' => '7.x',
            ],
            default => [
                'output' => 'composer-9.x.json',
                'release' => 'current',
            ],
        };

        if (!$composer = $this->fileSystem->getContent($target)) {
            $composer = [
                'name' => 'drupal-composer/drupal-security-advisories',
                'description' => 'Prevents installation of composer packages with known security vulnerabilities',
                'type' => 'metapackage',
                'license' => 'GPL-2.0-or-later',
                'extra' => ['changed' => 0],
                'conflict' => []
            ];
        }
        $projects = $this->projectManager->get($category, $composer['extra']['changed']);

        foreach ($projects as $name) {
            $output->write(sprintf('<info>Fetching release data</info>: %s ... ', $name));

            $project = $this->releaseFetcher
                ->get($name, $release);

            if (!$constraint = $versionParser->format($project)) {
                $output->write('<comment>No valid constraints found!</comment>');

                continue;
            }
            $output->write('<info>Generated constraint:</info> ' . $constraint . PHP_EOL);

            $composer['conflict']['drupal/' . $name] = $constraint;
        }

        if (isset($composer['conflict']['drupal/drupal'])) {
            $composer['conflict']['drupal/core'] = $composer['conflict']['drupal/drupal'];
        }
        $composer['extra']['changed'] = time();

        ksort($composer['conflict']);

        return $this->fileSystem->saveContent($target, $composer) ? Command::SUCCESS : Command::FAILURE;
    }
}

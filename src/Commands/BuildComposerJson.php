<?php

declare(strict_types = 1);

namespace App\Commands;

use App\ConstraintParser;
use App\Http\ProjectReleaseFetcher;
use App\Http\UpdateFetcher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'build:composer')]
final class BuildComposerJson extends Command
{
    public function __construct(
        private readonly string $buildDir,
        private readonly ProjectReleaseFetcher $projectManager,
        private readonly UpdateFetcher $releaseFetcher
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        $this
            ->addArgument(
                'release',
                description: 'The release category. Enter "7.x" for Drupal 7 and "current" for Drupal 8+.',
                default: 'current'
            );
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        ['release' => $release, 'version' => $version, 'file' => $file] = match ($input->getArgument('release')) {
            '7.x', 'legacy' => [
                'version' => '7.x',
                'release' => 'legacy',
                'file' => 'legacy.json',
            ],
            default => [
                'version' => 'current',
                'release' => 'current',
                'file' => 'current.json',
            ],
        };

        $composer = [
            'name' => 'drupal-composer/drupal-security-advisories',
            'description' => 'Prevents installation of composer packages with known security vulnerabilities',
            'type' => 'metapackage',
            'license' => 'GPL-2.0-or-later',
            'conflict' => []
        ];

        foreach ($this->projectManager->get($release) as $name) {
            $output->write(sprintf('<info>Fetching release data</info>: %s ... ', $name));

            $project = $this->releaseFetcher
                ->get($name, $version);

            if (!$constraint = ConstraintParser::format($project)) {
                $output->write('<comment>No valid constraints found!</comment>');

                continue;
            }
            $output->write('<info>Generated constraint:</info> ' . $constraint . PHP_EOL);

            $composer['conflict']['drupal/' . $name] = $constraint;
        }
        if (isset($composer['conflict']['drupal/drupal'])) {
            $composer['conflict']['drupal/core'] = $composer['conflict']['drupal/drupal'];
        }
        ksort($composer['conflict']);

        $json = json_encode($composer, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if (file_put_contents(sprintf('%s/%s', $this->buildDir, $file), $json . PHP_EOL) === false) {
            return Command::FAILURE;
        }
        return Command::SUCCESS;
    }
}

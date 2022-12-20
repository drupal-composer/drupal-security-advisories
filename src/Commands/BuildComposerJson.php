<?php

declare(strict_types=1);

namespace App\Commands;

use App\ConstraintParser;
use App\Container;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'build:composer')]
final class BuildComposerJson extends BuildBase
{
    private function generateConstraints(
        string $releaseCategory,
        string $updateEndpoint,
        OutputInterface $output
    ): array {
        $conflicts = [];

        foreach ($this->releaseManager->getReleases($releaseCategory) as $name => $versions) {
            $namespacedName = sprintf('drupal/%s', $name);

            if (isset($conflicts[$namespacedName])) {
                continue;
            }
            $output->write(sprintf('<info>Fetching release data</info>: %s ... ', $name));

            $project = $this->releaseManager
                ->getUpdateData($name, $updateEndpoint);

            if (!$constraint = ConstraintParser::format($project)) {
                $output->write('<comment>No valid constraints found!</comment>');

                continue;
            }
            $output->write('<info>Generated constraint:</info> ' . $constraint . PHP_EOL);

            $conflicts[$namespacedName] = $constraint;
        }
        if (isset($conflicts['drupal/drupal'])) {
            $conflicts['drupal/core'] = $conflicts['drupal/drupal'];
        }
        ksort($conflicts);

        return $conflicts;
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        [
            'release' => $releaseCategory,
            'updateEndpoint' => $updateEndpoint,
            'file' => $file,
        ] = $this->getInputSettings($input);

        $constraints = $this->generateConstraints($releaseCategory, $updateEndpoint, $output);

        $composer = [
            'name' => 'drupal-composer/drupal-security-advisories',
            'description' => 'Prevents installation of composer packages with known security vulnerabilities',
            'type' => 'metapackage',
            'license' => 'GPL-2.0-or-later',
            'conflict' => $constraints,
        ];

        $content = json_encode($composer, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

        $this->fileSystem->dumpFile($file, $content);

        return Command::SUCCESS;
    }
}

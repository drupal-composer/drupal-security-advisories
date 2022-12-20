<?php

declare(strict_types=1);

namespace App\Commands;

use App\Container;
use App\ReleaseManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Filesystem\Filesystem;

abstract class BuildBase extends Command
{
    public function __construct(
        protected readonly Filesystem $fileSystem,
        protected readonly ReleaseManager $releaseManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'release',
                description: 'The release category. Enter "legacy" for Drupal 7 and "current" for Drupal 8+.',
                default: 'current'
            );
    }

    protected function getInputSettings(InputInterface $input): array
    {
        return match ($input->getArgument('release')) {
            '7.x', 'legacy' => [
                'updateEndpoint' => '7.x',
                'composerRepository' => 'https://packages.drupal.org/7/',
                'release' => 'legacy',
                'file' => Container::baseDir() . '/legacy.json',
            ],
            default => [
                'updateEndpoint' => 'current',
                'composerRepository' => 'https://packages.drupal.org/8/',
                'release' => 'current',
                'file' => Container::baseDir() . '/current.json',
            ],
        };
    }
}

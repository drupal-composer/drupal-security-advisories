<?php

declare(strict_types=1);

namespace App;

use App\DTO\Project;
use App\DTO\Release;
use App\Http\DrupalApi;
use App\Http\UpdateFetcher;
use Symfony\Contracts\Cache\CacheInterface;

final class ReleaseManager
{
    private array $releases = [];

    public function __construct(
        private readonly DrupalApi $drupalApiFetcher,
        private readonly UpdateFetcher $updateFetcher,
        private readonly CacheInterface $cache,
    ) {
    }

    private function parseProjectReleaseUrl(string $url): string
    {
        [, $base, $name] = explode('/', parse_url($url, PHP_URL_PATH));

        if ('project' !== $base) {
            throw new \InvalidArgumentException('Failed to parse project name.');
        }

        return $name;
    }

    public function getReleases(string $category): array
    {
        if ($this->releases) {
            return $this->releases;
        }

        $items = $this->cache->get('release-'.$category, function () use ($category) {
            $releases = [];

            // Fetch all releases marked as 'insecure' (tid = 188131) or 'Security update' (tid = 100).
            foreach ([188131 => 'insecure', 100 => 'security_update'] as $tid => $type) {
                $filters = [
                    'type' => 'project_release',
                    'field_release_build_type' => 'static',
                    'field_release_category' => $category,
                    'taxonomy_vocabulary_7' => $tid,
                    'status' => 1,
                ];
                /* @var \App\DTO\Release[] $releases */
                $releases[$tid] = $this->drupalApiFetcher
                    ->filterList('node', $filters, function (array $item) use ($type) {
                        // Parse the project name from release URL. The URL should be something like
                        // https://drupal.org/project/drupal/releases/9.4.7.
                        $name = $this->parseProjectReleaseUrl($item['url']);

                        $item['body']['value'] ??= '';
                        // Some D7 releases don't have a project associated with them.
                        $item['field_release_project']['id'] ??= null;

                        return Release::createFromArray($item, $name, $type);
                    });
            }

            return $releases;
        });

        foreach ($items as $releases) {
            foreach ($releases as $release) {
                $this->releases[$release->getName()][$release->getVersion()] = $release;
            }
        }

        return $this->releases;
    }

    public function getUpdateData(string $name, string $endpoint): Project
    {
        return $this->updateFetcher->get($name, $endpoint);
    }
}

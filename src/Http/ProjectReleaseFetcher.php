<?php

declare(strict_types = 1);

namespace App\Http;

use Symfony\Contracts\HttpClient\ResponseInterface;

final class ProjectReleaseFetcher extends HttpBase
{
    private array $projects = [];

    private function parseProjectName(string $url) : string
    {
        [, $base, $name] = explode('/', parse_url($url, PHP_URL_PATH));

        if ($base !== 'project') {
            throw new \InvalidArgumentException('Failed to parse project name.');
        }
        return $name;
    }

    private function getPagerValue(string $url) : int
    {
        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        return isset($query['page']) ? (int) $query['page'] : 0;
    }

    private function getUrl(int $tid, int $page, string $releaseCategory): string
    {
        $query = http_build_query([
            'type' => 'project_release',
            'taxonomy_vocabulary_7' => $tid,
            'field_release_build_type' => 'static',
            'page' => $page,
            'field_release_category' => $releaseCategory,
        ]);
        return sprintf('https://www.drupal.org/api-d7/node.json?%s', $query);
    }

    public function get(string $releaseCategory) : \Generator
    {
        $responses = [];

        // Fetch all releases marked as 'insecure' (tid = 188131) or 'Security update' (tid = 100).
        // Some modules seem to have security releases where previous releases are not marked as insecure.
        foreach ([188131, 100] as $tid) {
            $content = $this->request($this->getUrl($tid, 0, $releaseCategory));
            $totalPages = $this
                ->getPagerValue($this->parseResponse($content)->last);

            for ($page = 0; $page <= $totalPages; $page++) {
                $responses[] = $this->request($this->getUrl($tid, $page, $releaseCategory));
            }
        }

        /** @var \Symfony\Contracts\HttpClient\ResponseInterface $response */
        foreach ($responses as $response) {
            foreach ($this->parseResponse($response)->list as $item) {
                // Parse the project name from release URL. The URL should be something like
                // https://drupal.org/project/drupal/releases/9.4.7.
                $name = $this->parseProjectName($item->url);

                if (isset($this->projects[$name])) {
                    continue;
                }
                $this->projects[$name] = $name;

                yield $name;
            }
        }
    }

    protected function parseResponse(ResponseInterface $content): object
    {
        return json_decode($content->getContent());
    }

    protected function getContentType(): string
    {
        return 'application/json';
    }

}

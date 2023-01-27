<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Contracts\HttpClient\ResponseInterface;

final class DrupalApi extends HttpBase
{
    private function getPagerValue(string $url): int
    {
        parse_str(parse_url($url, PHP_URL_QUERY), $query);

        return isset($query['page']) ? (int) $query['page'] : 0;
    }

    private function getListUrl(string $type, array $query): string
    {
        $query = http_build_query($query);

        return sprintf('https://www.drupal.org/api-d7/%s.json?%s', $type, $query);
    }

    public function getEntity(string $type, string|int $id): array
    {
        $content = $this
            ->request(sprintf('https://www.drupal.org/api-d7/%s/%s', $type, $id));

        return $this->parseResponse($content);
    }

    public function filterList(string $type, array $filters, callable $callback): array
    {
        $responses = [];

        $content = $this->request($this->getListUrl($type, ['page' => 0] + $filters));
        $totalPages = $this
            ->getPagerValue($this->parseResponse($content)['last']);

        for ($page = 0; $page <= $totalPages; ++$page) {
            $responses[] = $this->request($this->getListUrl($type, ['page' => $page] + $filters));
        }

        $releases = [];
        /** @var \Symfony\Contracts\HttpClient\ResponseInterface $response */
        foreach ($responses as $response) {
            foreach ($this->parseResponse($response)['list'] as $item) {
                if (!$value = $callback($item)) {
                    continue;
                }
                $releases[] = $value;
            }
        }

        return $releases;
    }

    protected function parseResponse(ResponseInterface $content): array
    {
        return json_decode($content->getContent(), true);
    }

    protected function getContentType(): string
    {
        return 'application/json';
    }
}

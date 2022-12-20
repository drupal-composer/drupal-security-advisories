<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

abstract class HttpBase
{
    public function __construct(
        protected readonly HttpClientInterface $httpClient,
    ) {
    }

    protected function request(string $url): ResponseInterface
    {
        return $this->httpClient
            ->request('GET', $url, [
                'headers' => ['Accept' => $this->getContentType()],
            ]);
    }

    abstract protected function parseResponse(ResponseInterface $response): mixed;

    abstract protected function getContentType(): string;
}

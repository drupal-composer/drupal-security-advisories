<?php

declare(strict_types = 1);

namespace App\Http;

use Symfony\Contracts\HttpClient\HttpClientInterface;

abstract class HttpBase
{
    public function __construct(
        protected readonly HttpClientInterface $httpClient,
    ) {
    }

    protected function request(string $url) : mixed
    {
        $content = $this->httpClient
            ->request('GET', $url, [
                'headers' => ['Accept' => $this->getContentType()],
            ])->getContent();

        return $this->parseRequest($content);
    }

    abstract protected function parseRequest(string $content): mixed;

    abstract protected function getContentType() : string;

}

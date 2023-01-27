<?php

namespace App;

final class Container
{
    private array $service = [];

    public function add(string $name, mixed $object): self
    {
        $this->service[$name] = $object;

        return $this;
    }

    public function get(string $name): mixed
    {
        return $this->service[$name];
    }

    public static function baseDir(): string
    {
        return getenv('DSA_BUILD_DIR') ?: 'build';
    }

    public static function cacheDir(): string
    {
        return getenv('DSA_HTTP_CACHE_DIR') ?: '/tmp/symfony-http-cache';
    }
}

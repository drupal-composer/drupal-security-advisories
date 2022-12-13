<?php

declare(strict_types = 1);

namespace App;

final class FileSystem
{
    public function __construct(
        private readonly string $basePath,
    ) {
    }

    private function getFilePath(string $target) : string
    {
        return sprintf('%s/%s', $this->basePath, $target);
    }

    public function getContent(string $target): array
    {
        $target = $this->getFilePath($target);

        if (!file_exists($target)) {
            return [];
        }
        $json = file_get_contents($target);

        return \json_decode($json, true, flags: JSON_THROW_ON_ERROR) ?? [];
    }

    public function saveContent(string $target, array $content): bool
    {
        $json = json_encode($content, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return file_put_contents($this->getFilePath($target), $json . PHP_EOL) === false;
    }

}

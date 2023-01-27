<?php

declare(strict_types=1);

namespace App;

trait SemanticVersionTrait
{
    protected static function normalizeSupportedBranch(string $branch): string
    {
        $branch = sprintf('%s.0', rtrim($branch, '.'));

        return static::convertLegacyVersionToSemantic($branch);
    }

    protected static function convertLegacyVersionToSemantic(string $version): string
    {
        $prefix = substr($version, 0, 4);

        // Leave versions without core prefix intact.
        if (!in_array($prefix, ['7.x-', '8.x-'])) {
            return $version;
        }
        $version = substr($version, 4);
        $parts = explode('-', $version);

        if (2 === count($parts)) {
            return sprintf('%s.0-%s', $parts[0], $parts[1]);
        }

        return sprintf('%s.0', $parts[0]);
    }
}

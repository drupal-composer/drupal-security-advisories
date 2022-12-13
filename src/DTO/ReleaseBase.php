<?php

declare(strict_types = 1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Validation;

abstract class ReleaseBase
{
    abstract public static function createFromArray(array $data): self;

    protected function convertLegacyVersionToSemantic(string $version): string
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

    protected static function validate(Collection $collectionConstraint, array $data): void
    {
        $violations = Validation::createValidator()->validate($data, $collectionConstraint);

        if (count($violations)) {
            $messages = [];

            foreach ($violations as $violation) {
                $messages[] = 'Field '.$violation->getPropertyPath().': '.$violation->getMessage();
            }
            throw new \UnexpectedValueException('Malformed data: '.implode(",\n", $messages));
        }
    }
}

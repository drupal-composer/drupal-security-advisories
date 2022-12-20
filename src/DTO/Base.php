<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Validation;

abstract class Base
{
    protected static function validate(Collection $collectionConstraint, mixed $data): void
    {
        $violations = Validation::createValidator()->validate($data, $collectionConstraint);

        if (count($violations)) {
            $messages = [];

            foreach ($violations as $violation) {
                $messages[] = 'Field '.$violation->getPropertyPath().': '.$violation->getMessage();
            }

            throw new \UnexpectedValueException(
                sprintf("Malformed data: %s\nData given:\n %s", implode(",\n", $messages), print_r($data, true))
            );
        }
    }
}

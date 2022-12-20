<?php

declare(strict_types=1);

namespace App\DTO;

use App\SemanticVersionTrait;
use Symfony\Component\Validator\Constraints as Assert;

final class Release extends Base
{
    use SemanticVersionTrait;

    private function __construct(
        private readonly string $name,
        private readonly int|string|null $project,
        private readonly string $version,
        private readonly string $body,
        private readonly string $type,
    ) {
    }

    public function getSemanticVersion(): string
    {
        return $this->convertLegacyVersionToSemantic($this->version);
    }

    public static function createFromArray(array $item, string $name, string $type): self
    {
        $notBlankConstraint = [
            new Assert\Type('string'),
            new Assert\NotBlank(),
        ];

        $collectionConstraint = new Assert\Collection([
            'fields' => [
                'field_release_version' => $notBlankConstraint,
                'field_release_project' => new Assert\Collection([
                    'fields' => ['id' => $notBlankConstraint],
                    'allowExtraFields' => true
                ]),
                'body' => new Assert\Collection([
                    'fields' => ['value' => new Assert\Type('string')],
                    'allowExtraFields' => true,
                ]),
            ],
            'allowExtraFields' => true,
        ]);

        self::validate($collectionConstraint, $item);

        return new self(
            $name,
            $item['field_release_project']['id'],
            $item['field_release_version'],
            $item['body']['value'],
            $type,
        );
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getProject(): int|string|null
    {
        return $this->project;
    }
}

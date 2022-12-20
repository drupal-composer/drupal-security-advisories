<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class SecurityAdvisory extends Base
{
    private function __construct(
        private readonly string $id,
        private readonly string $project,
        private readonly string $title,
        private readonly string $link,
        private readonly int $created,
    ) {
    }

    public static function createFromArray(string $id, array $item): self
    {
        $notBlankConstraint = [
            new Assert\Type('string'),
            new Assert\NotBlank(),
        ];
        $constraint = new Assert\Collection([
            'fields' => [
                'title' => $notBlankConstraint,
                'url' => $notBlankConstraint,
                'created' => $notBlankConstraint,
                'field_project' => new Assert\Collection([
                    'fields' => ['id' => $notBlankConstraint],
                    'allowExtraFields' => true
                ]),
            ],
            'allowExtraFields' => true,
        ]);
        parent::validate($constraint, $item);

        return new self(
            $id,
            $item['field_project']['id'],
            $item['title'],
            $item['url'],
            (int) $item['created']
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getCreated(): int
    {
        return $this->created;
    }

    public function getLink(): string
    {
        return $this->link;
    }

    public function getProject(): string
    {
        return $this->project;
    }
}

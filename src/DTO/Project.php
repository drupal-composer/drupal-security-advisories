<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final class Project extends Base
{
    private function __construct(
        private readonly string $status,
        private readonly array $releases,
        private readonly array $supportedBranches,
    ) {
    }

    public function isUnsupported(): bool
    {
        return $this->status === 'unsupported';
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public static function createFromArray(array $data): self
    {
        $constraint = new Assert\Collection([
            'fields' => [
                'project_status' => new Assert\Choice(['published', 'unpublished', 'unsupported']),
                'supported_branches' => new Assert\Optional([
                    new Assert\Type('array'),
                ]),
                'releases' => new Assert\All(
                    new Assert\Type(UpdateRelease::class),
                ),
            ],
            'allowExtraFields' => true,
        ]);
        self::validate($constraint, $data);

        return new self(
            $data['project_status'],
            $data['releases'],
            $data['supported_branches'] ?? [],
        );
    }

    /**
     * @return \App\DTO\UpdateRelease[]
     */
    public function getReleases(): array
    {
        return $this->releases;
    }

    public function getSupportedBranches(): array
    {
        return $this->supportedBranches;
    }
}

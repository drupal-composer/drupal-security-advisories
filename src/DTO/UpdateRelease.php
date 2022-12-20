<?php

namespace App\DTO;

use App\SemanticVersionTrait;
use Symfony\Component\Validator\Constraints as Assert;

final class UpdateRelease extends Base
{
    use SemanticVersionTrait;

    private function __construct(
        private readonly string $version,
        private readonly ?array $releaseTypes,
    ) {
    }

    public static function createFromArray(array $release): self
    {
        $notBlankConstraint = [
            new Assert\Type('string'),
            new Assert\NotBlank(),
        ];
        $collectionConstraint = new Assert\Collection([
            'fields' => [
                'version' => $notBlankConstraint,
                'status' => new Assert\Choice(['published', 'unpublished']),
                'terms' => new Assert\Optional([
                    new Assert\Type('array'),
                    new Assert\Collection([
                        'Release type' => new Assert\Optional([
                            new Assert\Type('array'),
                        ]),
                    ]),
                ]),
            ],
            'allowExtraFields' => true,
        ]);
        self::validate($collectionConstraint, $release);

        return new self(
            $release['version'],
            $release['terms']['Release type'] ?? null,
        );
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getSemanticVersion(): string
    {
        return $this->convertLegacyVersionToSemantic($this->version);
    }

    /**
     * Determines if the release is a security release.
     *
     * @return bool true if the release is security release, or false otherwise
     */
    public function isSecurityRelease(): bool
    {
        return $this->isReleaseType('Security update');
    }

    /**
     * Determines if the release is unsupported.
     *
     * @return bool true if the release is unsupported, or false otherwise
     */
    public function isUnsupported(): bool
    {
        return $this->isReleaseType('Unsupported');
    }

    /**
     * Determines if the release is insecure.
     *
     * @return bool true if the release is insecure, or false otherwise
     */
    public function isInsecure(): bool
    {
        return $this->isReleaseType('Insecure');
    }

    /**
     * Determines if the release is matches a type.
     *
     * @param string $type The release type
     *
     * @return bool true if the release matches the type, or false otherwise
     */
    private function isReleaseType(string $type): bool
    {
        return $this->releaseTypes && in_array($type, $this->releaseTypes, true);
    }
}

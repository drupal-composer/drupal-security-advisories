<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

final class ComposerSecurityAdvisory extends Base
{
    private function __construct(
        private readonly string $advisoryId,
        private readonly string $packageName,
        private readonly string $remoteId,
        private readonly string $title,
        private readonly ?string $cve,
        private readonly string $affectedVersions,
        private readonly string $link,
        private readonly string $reportedAt,
        private readonly string $composerRepository,
    ) {
    }

    public static function formatDate(int $timestamp): string
    {
        return (new \DateTimeImmutable('@' . $timestamp))
            ->format('Y-m-d H:i:s');
    }

    public static function createFromSecurityAdvisory(SecurityAdvisory $advisory, array $item): self
    {
        return self::createFromArray($item + [
            'advisoryId' => $advisory->getId(),
            'remoteId' => $advisory->getId(),
            'title' => $advisory->getTitle(),
            'link' => $advisory->getLink(),
            'reportedAt' => self::formatDate($advisory->getCreated()),
        ]);
    }

    public static function createFromArray(array $item): self
    {
        $notBlankConstraint = [
            new Assert\Type('string'),
            new Assert\NotBlank(),
        ];
        $collectionConstraint = new Assert\Collection([
            'fields' => [
                'packageName' => $notBlankConstraint,
                'advisoryId' => $notBlankConstraint,
                'remoteId' => $notBlankConstraint,
                'title' => $notBlankConstraint,
                'cve' => new Assert\Type('string'),
                'affectedVersions' => $notBlankConstraint,
                'link' => [new Assert\Url(), new Assert\NotBlank()],
                'composerRepository' => [new Assert\Url(), new Assert\NotBlank()],
                'reportedAt' => [new Assert\DateTime(), new Assert\NotBlank()],
            ],
        ]);

        parent::validate($collectionConstraint, $item);

        return new static(
            $item['advisoryId'],
            $item['packageName'],
            $item['remoteId'],
            $item['title'],
            $item['cve'],
            $item['affectedVersions'],
            $item['link'],
            $item['reportedAt'],
            $item['composerRepository'],
        );
    }

    public function toArray(): array
    {
        return [
            'advisoryId' => $this->advisoryId,
            'packageName' => $this->packageName,
            'remoteId' => $this->remoteId,
            'title' => $this->title,
            'affectedVersions' => $this->affectedVersions,
            'cve' => $this->cve,
            'link' => $this->link,
            'reportedAt' => $this->reportedAt,
            'composerRepository' => $this->composerRepository,
        ];
    }
}

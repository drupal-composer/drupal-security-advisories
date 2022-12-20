<?php

declare(strict_types = 1);

namespace App\Tests;

use App\DTO\ComposerSecurityAdvisory;
use PHPUnit\Framework\TestCase;

class ComposerSecurityAdvisoryTest extends TestCase
{
    public function testCreateFromArray(): void
    {
        $data = [
            'packageName' => 'drupal/core',
            'remoteId' => 'SA-CORE-2022-022',
            'advisoryId' => 'SA-CORE-2022-022',
            'title' => 'Drupal core - Critical - Arbitrary PHP code execution - SA-CORE-2020-013',
            'cve' => null,
            'affectedVersions' => '*',
            'link' => 'https://www.drupal.org/sa-core-2020-013',
            'composerRepository' => 'https://packages.drupal.org/8/',
            'reportedAt' => '2022-12-25 13:33:33',
        ];
        $dto = ComposerSecurityAdvisory::createFromArray($data);

        $this->assertEquals($data, $dto->toArray());
    }
}

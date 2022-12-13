<?php

declare(strict_types = 1);

namespace App\Tests;

use App\DTO\Release;
use PHPUnit\Framework\TestCase;

class ReleaseTest extends TestCase
{

    private function getSut(string $version, string $releaseType): Release
    {
        return Release::createFromArray([
            'version' => $version,
            'status' => 'published',
            'terms' => ['Release type' => [$releaseType]],
        ]);
    }

    public function testIsSecurityRelease(): void
    {
        $this->assertTrue($this->getSut('1.9.0', 'Security update')->isSecurityRelease());
        $this->assertFalse($this->getSut('1.9.0', 'Bug fixes')->isSecurityRelease());
    }

    public function testIsUnsupported(): void
    {
        $this->assertTrue($this->getSut('1.9.0', 'Unsupported')->isUnsupported());
        $this->assertFalse($this->getSut('1.9.0', 'Bug fixes')->isUnsupported());
    }

    public function testIsInsecure(): void
    {
        $this->assertTrue($this->getSut('1.9.0', 'Insecure')->isInsecure());
        $this->assertFalse($this->getSut('1.9.0', 'Bug fixes')->isInsecure());
    }

    /**
     * @dataProvider versionData
     */
    public function testRelease(Release $release, string $expected) : void
    {
        $this->assertEquals($expected, $release->getSemanticVersion());
    }

    public function versionData() : array
    {
        $versions = [
            '7.x-1.9-beta1' => '1.9.0-beta1',
            '7.x-1.9' => '1.9.0',
            '1.9-beta1' => '1.9-beta1',
            '1.9.0' => '1.9.0',
            '1.9' => '1.9',
            '8.x-1.9-beta1' => '1.9.0-beta1',
            '8.x-1.9' => '1.9.0',
        ];

        $data = [];
        foreach ($versions as $version => $expected) {
            $data[] = [
                $this->getSut($version, 'Bug fixes'),
                $expected,
            ];
        }
        return $data;
    }
}

<?php

declare(strict_types = 1);

namespace App\Tests;

use App\DTO\Project;
use App\DTO\UpdateRelease;
use App\ConstraintParser;
use Composer\Semver\Semver;
use PHPUnit\Framework\TestCase;

class VersionParserTest extends TestCase
{

    /**
     * @dataProvider versionData
     */
    public function testVersionConstraint(Project $project, string $expected, array $insecure, array $secure): void
    {
        $actual = ConstraintParser::format($project);
        $this->assertEquals($expected, $actual);

        // Assert that given insecure versions are matched against the constraint.
        foreach ($insecure as $version) {
            $this->assertTrue(
                Semver::satisfies($version, $expected),
                sprintf('%s is insecure', $version),
            );
        }

        // Assert that secure versions are not matches against the constraint.
        foreach ($secure as $version) {
            $this->assertFalse(
                Semver::satisfies($version, $expected),
                sprintf('%s is secure.', $version),
            );
        }
    }

    public function versionData() : array
    {
        $items = [
            // Test project with no security release (abandoned project).
            [
                'data' => [
                    '8.x-1.26' => ['Insecure'],
                    '8.x-1.25' => ['Insecure'],
                ],
                'supported_branches' => '8.x-1.',
                'expected' => '<=1.26.0',
                'insecure' => ['1.1.0'],
                'secure' => [],
            ],
            // Test project with security updates in 2.x branch and
            // no insecure releases in 1.x branch.
            [
                'data' => [
                    '2.0.1' => ['Security update'],
                    '2.0.0' => ['Insecure'],
                    '8.x-1.2' => ['Bug fixes'],
                    '8.x-1.1' => ['Bug fixes'],
                    '8.x-1.0' => ['Bug fixes'],
                ],
                'supported_branches' => '8.x-1.,2.0.',
                'expected' => '<1.0.0|>=2.0.0,<2.0.1',
                'insecure' => ['2.0.0'],
                'secure' => ['2.0.1'],
            ],
            [
                'data' => [
                    '7.x-2.9' => ['Bug fixes'],
                    '7.x-2.8' => ['Security update'],
                    '7.x-2.7' => ['Insecure'],
                    '7.x-2.6' => ['Insecure'],
                    '7.x-1.9' => ['Bug fixes'],
                    '7.x-1.8' => ['Security update'],
                    '7.x-1.7' => ['Insecure'],
                    '7.x-1.6' => ['Insecure'],
                ],
                'supported_branches' => '7.x-2.,7.x-1.',
                'expected' => '<1.0.0|>=1.0.0,<1.8.0|>=2.0.0,<2.8.0',
                'insecure' => ['1.6.0', '1.7.0', '2.6.0-rc1', '2.7.0-alpha1'],
                'secure' => ['2.9', '2.8-beta1', '2.8-rc1', '1.9'],
            ],
            [
                'data' => [
                    '11.9.5' => ['Bug fixes'],
                    '11.9.4' => ['Security update'],
                    '11.9.3' => ['Insecure'],
                    '11.9.2' => ['Insecure'],
                    '11.8.1' => ['Bug fixes'],
                    '11.8.0' => ['Security update'],
                    '11.7.3' => ['Bug fixes'],
                    '11.7.2' => ['Security update'],
                    '11.7.1' => ['Insecure'],
                    '11.7.0' => ['Insecure'],
                    '10.0.0' => ['Insecure'],
                ],
                'supported_branches' => '11.9.,11.8.,11.7.',
                'expected' => '<11.7.0|>=11.7.0,<11.7.2|>=11.9.0,<11.9.4',
                'insecure' => ['9.0.0', '10.0.0', '10.0.1', '11.7.1-beta1'],
                'secure' => ['11.7.2', '11.9.5'],
            ],
            // Test project with no stable release.
            [
                'data' => [
                    '8.x-1.11-alpha6' => ['Bug fixes'],
                    '8.x-1.11-alpha5' => ['Security update'],
                    '8.x-1.11-alpha4' => ['Insecure'],
                    '8.x-1.11-alpha3' => ['Insecure'],
                ],
                'supported_branches' => '8.x-1.',
                'expected' => '<1.0.0|>=1.0.0,<1.11.0-alpha5',
                'insecure' => ['1.11.alpha4'],
                'secure' => ['1.11-alpha5', '1.12'],
            ],
            // Test project with no-stable lower bound.
            [
                'data' => [
                    '8.x-1.13' => ['Bug fixes'],
                    '8.x-1.12' => ['Security update'],
                    '8.x-1.11-alpha4' => ['Insecure'],
                    '8.x-1.11-alpha3' => ['Insecure'],
                ],
                'supported_branches' => '8.x-1.',
                'expected' => '<1.0.0|>=1.0.0,<1.12.0',
                'insecure' => ['1.11-alpha4', '1.11', '1.11-rc1', '1.11-beta1'],
                'secure' => ['1.12']
            ],
            // Test project with no-stable upper bound.
            [
                'data' => [
                    '8.x-1.13-alpha1' => ['Bug fixes'],
                    '8.x-1.11' => ['Insecure'],
                ],
                'supported_branches' => '8.x-1.',
                'expected' => '<1.0.0|>=1.0.0,<1.13.0-alpha1',
                'insecure' => ['1.11-alpha4', '1.11', '1.12-rc1', '1.11-beta1'],
                'secure' => ['1.13-alpha1', '1.13', '1.13-alpha2'],
            ],
            // Test projects with security release where previous releases were
            // not marked as insecure.
            [
                'data' => [
                    '2.0.1' => ['Security update'],
                    '2.0.0' => ['Bug fixes'],
                    '2.0.0-alpha1' => ['Bug fixes'],
                ],
                'supported_branches' => '2.',
                'expected' => '<2.0.1',
                'insecure' => ['2.0.0', '2.0.0-alpha1', '1.9.0'],
                'secure' => ['2.0.1', '2.1.0'],
            ],
            // Test project with back to back security updates with no
            // releases marked as insecure.
            [
                'data' => [
                    '3.0.2' => ['Security update'],
                    '3.0.1' => ['Security update'],
                    '3.0.0' => ['Bug fixes'],
                    '3.0.0-alpha1' => ['Bug fixes'],
                ],
                'supported_branches' => '3.0.',
                'expected' => '<3.0.2',
                'insecure' => ['3.0.0', '3.0.0-alpha1', '2.9.0'],
                'secure' => ['3.0.2', '3.1.0'],
            ],
        ];

        $data = [];
        foreach ($items as $item) {
            $releases = [];

            foreach ($item['data'] as $version => $releaseType) {
                $releases[] = UpdateRelease::createFromArray([
                    'version' => $version,
                    'status' => 'published',
                    'terms' => ['Release type' => $releaseType]
                ]);
            }
            $project = Project::createFromArray([
                'project_status' => 'published',
                'releases' => $releases,
                'supported_branches' => explode(',', $item['supported_branches']),
            ]);

            $data[] = [
                $project,
                $item['expected'],
                $item['insecure'],
                $item['secure'],
            ];
        }
        return $data;
    }
}

<?php

declare(strict_types=1);

namespace App;

use App\DTO\Project;
use App\DTO\UpdateRelease;
use Composer\Semver\Constraint\Constraint;
use Composer\Semver\Constraint\ConstraintInterface;
use Composer\Semver\Constraint\MatchAllConstraint;
use Composer\Semver\Constraint\MultiConstraint;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use UnexpectedValueException;

final class ConstraintParser
{
    use SemanticVersionTrait;

    private static function constraintToString(Constraint $constraint): string
    {
        return $constraint->getOperator().$constraint->getVersion();
    }

    public static function format(Project $project, string $separator = '|'): ?string
    {
        if (!$constraints = self::createConstraints($project)) {
            return null;
        }
        $parts = [];

        foreach ($constraints as $constraint) {
            if ($constraint instanceof MultiConstraint) {
                $parts[] = implode(',', array_map(function (ConstraintInterface $item) {
                    return self::constraintToString($item);
                }, $constraint->getConstraints()));
            }

            if ($constraint instanceof Constraint) {
                $parts[] = self::constraintToString($constraint);
            }

            if ($constraint instanceof MatchAllConstraint) {
                $parts[] = (string) $constraint;
            }
        }

        return implode($separator, $parts);
    }

    private static function getNormalizedVersion(string $version): ?string
    {
        $versionParser = new VersionParser();

        try {
            $version = self::convertLegacyVersionToSemantic($version);

            // Dev releases are never marked as insecure, so we can safely
            // ignore them.
            if ('dev' === $versionParser::parseStability($version)) {
                return null;
            }
            // Attempt to normalize the version, so we can skip versions that won't
            //  work with composer.
            $versionParser->normalize($version);

            return $version;
        } catch (UnexpectedValueException) {
        }

        return null;
    }

    /**
     * @return UpdateRelease[]
     */
    private static function filterReleases(Project $project): array
    {
        $releases = [];

        foreach ($project->getReleases() as $release) {
            if (!$version = self::getNormalizedVersion($release->getVersion())) {
                continue;
            }

            if (!self::isSupportedBranch($project, $release->getVersion())) {
                continue;
            }
            $releases[$version] = $release;
        }

        return $releases;
    }

    private static function isSupportedBranch(Project $project, string $version): bool
    {
        foreach ($project->getSupportedBranches() as $branch) {
            if (str_starts_with($version, $branch)) {
                return true;
            }
        }

        return false;
    }

    private static function getBranchFromVersion(array $supportedBranches, string $version): string
    {
        foreach ($supportedBranches as $branch) {
            if (Semver::satisfies($version, '~'.$branch)) {
                return $branch;
            }
        }

        foreach ($supportedBranches as $branch) {
            if (Semver::satisfies($version, '^'.$branch)) {
                return $branch;
            }
        }

        return '0.0.0';
    }

    public static function createConstraints(Project $project): array
    {
        // Mark unsupported projects as insecure.
        if ($project->isUnsupported() || !$project->getSupportedBranches()) {
            return [new MatchAllConstraint()];
        }
        $insecureGroups = $constraints = $branches = [];
        $latestSecurityUpdate = null;

        $releases = self::filterReleases($project);
        $supportedBranches = $project->getNormalizedSupportedBranches();

        foreach (array_reverse($releases) as $version => $release) {
            $branch = self::getBranchFromVersion($supportedBranches, $version);

            // Some projects have security releases where previous releases are not marked as
            // insecure. Capture the latest known security release.
            if ($release->isSecurityRelease()) {
                $latestSecurityUpdate = $version;
            }

            if (!$release->isInsecure()) {
                $branches[$branch][] = $version;
            } else {
                // Mark branch as insecure if there is at least one release marked as insecure.
                $insecureGroups[$branch] = $version;
            }
        }

        // Filter out branches without known security releases.
        $branches = array_filter(array_reverse($branches), function (string $group) use ($insecureGroups) {
            return isset($insecureGroups[$group]);
        }, ARRAY_FILTER_USE_KEY);

        foreach ($branches as $branch => $versions) {
            $constraints[] = MultiConstraint::create([
                new Constraint('>=', $branch),
                new Constraint('<', reset($versions)),
            ]);
        }

        if (!$constraints) {
            // Mark all previous releases as insecure if project has at least one security release, but
            // has no other releases marked as insecure. This can cause some false positives since there
            // is no way of telling what versions are *actually* insecure.
            if ($latestSecurityUpdate) {
                return [new Constraint('<', $latestSecurityUpdate)];
            }

            // Mark the whole project as insecure if project has branches marked as insecure, but no
            // constraints were generated.
            if (count($insecureGroups) > 0) {
                return [new Constraint('<=', end($insecureGroups))];
            }
        }

        usort($supportedBranches, 'version_compare');

        // Use the oldest supported version as baseline lower bound.
        $constraints[] = new Constraint('<', reset($supportedBranches));

        return array_reverse($constraints);
    }
}

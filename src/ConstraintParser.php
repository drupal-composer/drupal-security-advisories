<?php

declare(strict_types=1);

namespace App;

use App\DTO\Project;
use App\DTO\UpdateRelease;
use Composer\Semver\Constraint\Constraint;
use Composer\Semver\Constraint\ConstraintInterface;
use Composer\Semver\Constraint\MatchAllConstraint;
use Composer\Semver\Constraint\MultiConstraint;
use Composer\Semver\VersionParser;
use UnexpectedValueException;

final class ConstraintParser
{
    private static function constraintToString(Constraint $constraint): string
    {
        return $constraint->getOperator() . $constraint->getVersion();
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
        }

        return implode($separator, $parts);
    }

    private static function getNormalizedVersion(UpdateRelease $release): ?string
    {
        $versionParser = new VersionParser();

        try {
            $version = $release->getSemanticVersion();

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
            if (!$version = self::getNormalizedVersion($release)) {
                continue;
            }
            $releases[$version] = $release;
        }
        return $releases;
    }

    private static function isNewSecurityRelease(UpdateRelease $current, ?UpdateRelease $previous): bool
    {
        if ($current->isSecurityRelease()) {
            return true;
        }
        // Some releases are not marked as 'Security update' (usually alpha and beta releases).
        // Make sure these are taken into account by checking if previous release was marked as
        // 'insecure' but the current one is not.
        if (!$current->isInsecure() && $previous?->isInsecure()) {
            return true;
        }

        return false;
    }

    private static function reduceGroups(array $group, array $releases): array
    {
        // Filter out group if project has no insecure releases after the first item
        // of the given group.
        // For example, given the following constraints '<1.0.0, >1.2.0, <2.0.1', the '<1.0.0'
        // group is redundant since there's no security releases before the 1.0.0 release.
        return array_filter($group, function (ConstraintInterface $constraint) use ($releases) {
            if (!$constraint instanceof Constraint) {
                return true;
            }
            $version = $constraint->getVersion();

            reset($releases);
            // Move the internal pointer to given constraint version.
            while (current($releases)) {
                if (key($releases) === $version) {
                    break;
                }
                next($releases);
            }
            // Loop through the remaining releases to check if there are any insecure
            // releases.
            while ($item = next($releases)) {
                if ($item->isInsecure()) {
                    return true;
                }
            }
            return false;
        });
    }

    public static function createConstraints(Project $project): array
    {
        $constraintGroups = $groups = [];
        /** @var UpdateRelease|null $previous */
        $insecureRelease = $latestSecurityUpdate = $previous = null;

        $releases = self::filterReleases($project);
        $group = 0;

        // Collect and group all known releases between two insecure releases.
        foreach (array_reverse($releases) as $version => $release) {
            // Some projects have security updates where previous releases are not marked as
            // insecure. Capture the latest known security release.
            if ($release->isSecurityRelease()) {
                $latestSecurityUpdate = $version;
            }

            if (self::isNewSecurityRelease($release, $previous)) {
                $group++;
            }

            if (!$release->isInsecure()) {
                $constraintGroups[$group][] = $version;
            } else {
                $insecureRelease = $version;
            }
            $previous = $release;
        }

        $constraintGroups = array_reverse($constraintGroups);

        // Compare the first item of current group against the last item of the next group and
        // group them together, like '>{next groups last item}, <{current groups first item}'.
        while ($current = current($constraintGroups)) {
            $lowerBound = $upperBound = new Constraint('<', reset($current));

            if (!$next = next($constraintGroups)) {
                $groups[] = $lowerBound;
                break;
            }
            $lowerBound =  new Constraint('>', end($next));

            $groups[] = MultiConstraint::create([$lowerBound, $upperBound]);
        }

        $groups = self::reduceGroups($groups, $releases);

        if (!$groups) {
            // Mark the whole project as insecure if no constraints were generated. The project
            // is most likely abandoned and has publicly known security issue(s).
            return $previous ? [new Constraint('<=', $previous->getSemanticVersion())] : [new MatchAllConstraint()];
        }

        // Mark all previous releases as insecure if project has at least one security release, but
        // has no other releases marked as insecure. This can cause some false positives since there
        // is no way of telling what versions are *actually* insecure.
        if (!$insecureRelease && $latestSecurityUpdate) {
            return [new Constraint('<', $latestSecurityUpdate)];
        }
        return array_reverse($groups);
    }
}

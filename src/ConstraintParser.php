<?php

declare(strict_types = 1);

namespace App;

use App\DTO\Project;
use Composer\Semver\Constraint\Constraint;
use Composer\Semver\Constraint\ConstraintInterface;
use Composer\Semver\Constraint\MatchAllConstraint;
use Composer\Semver\Constraint\MultiConstraint;
use Composer\Semver\Intervals;
use Composer\Semver\VersionParser;

final class ConstraintParser
{
    private static function constraintToString(Constraint $constraint) : string
    {
        return $constraint->getOperator() . $constraint->getVersion();
    }

    public static function format(Project $project) : ?string
    {
        if (!$constraints = self::createConstraints($project)) {
            return null;
        }

        if (!$constraints instanceof MultiConstraint) {
            return $constraints->getPrettyString();
        }
        $parts = array_map(function (ConstraintInterface $constraint) {
            if ($constraint instanceof MultiConstraint) {
                return implode(',', array_map(function (ConstraintInterface $item) {
                    return self::constraintToString($item);
                }, $constraint->getConstraints()));
            }
            return self::constraintToString($constraint);
        }, $constraints->getConstraints());

        if ($constraints->isConjunctive()) {
            return implode(',', $parts);
        }

        return implode('|', $parts);
    }

    private static function createConstraints(Project $project) : ?ConstraintInterface
    {
        $constraints = [];
        $latestSecureRelease = $upperBound = null;

        foreach ($project->getReleases() as $release) {
            $version = $release->getSemanticVersion();

            // Dev releases are never marked as insecure, so we can safely
            // ignore them.
            if ('dev' === VersionParser::parseStability($version)) {
                continue;
            }
            $constraintGroup = [];

            // Some projects have security updates where previous releases are not marked as
            // insecure. Capture the version of the latest known security release and use it as
            // upper bound.
            if (!$latestSecureRelease && $release->isSecurityRelease()) {
                $latestSecureRelease = $version;
            }

            if (!$release->isInsecure()) {
                // Mark latest know secure release as upper bound.
                $upperBound = $version;
            } else {
                $constraintGroup[] = new Constraint('>=', $version);

                if ($upperBound) {
                    $constraintGroup[] = new Constraint('<', $upperBound);
                }
            }

            if ($constraintGroup) {
                $constraints[] = MultiConstraint::create($constraintGroup);
            }
        }
        // If the upper bound was not set, the project is most likely abandoned and has publicly
        // known security issue(s). In that case we can mark the whole project as insecure.
        if (!$upperBound) {
            return new MatchAllConstraint();
        }

        // Mark all previous releases as insecure if project has at least one security release
        // and no other constraints were generated.
        // This usually happens when project has a release marked as 'Security update', but
        // previous releases were not marked as 'Insecure'. This can cause some false positives
        // since there is no way of telling what versions are *actually* insecure.
        if (!$constraints && $latestSecureRelease) {
            return new Constraint('<', $latestSecureRelease);
        }
        // MultiConstraint requires at least two constraints. Return the first available constraint
        // since there is nothing to compact.
        if (count($constraints) < 2) {
            return !$constraints ? null : reset($constraints);
        }
        return Intervals::compactConstraint(new MultiConstraint($constraints, false));
    }
}

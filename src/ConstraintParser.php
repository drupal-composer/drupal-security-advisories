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
    private function constraintToString(Constraint $constraint) : string
    {
        return $constraint->getOperator() . $constraint->getVersion();
    }

    public function format(Project $project) : ?string
    {
        if (!$constraints = $this->createConstraints($project)) {
            return null;
        }

        if (!$constraints instanceof MultiConstraint) {
            return $constraints->getPrettyString();
        }
        $parts = array_map(function (ConstraintInterface $constraint) {
            if ($constraint instanceof MultiConstraint) {
                return implode(',', array_map(function (ConstraintInterface $item) {
                    return $this->constraintToString($item);
                }, $constraint->getConstraints()));
            }
            return $this->constraintToString($constraint);
        }, $constraints->getConstraints());

        if ($constraints->isConjunctive()) {
            return implode(',', $parts);
        }

        return implode('|', $parts);
    }

    private function createConstraints(Project $project) : ?ConstraintInterface
    {
        $constraints = [];
        $upperBound = null;

        foreach ($project->getReleases() as $release) {
            $version = $release->getSemanticVersion();

            // Dev releases are never marked as insecure, so we can safely
            // ignore them.
            if ('dev' === VersionParser::parseStability($version)) {
                continue;
            }
            $constraintGroup = [];

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
        // known security issues. In that case we can mark the whole project as insecure.
        if (!$upperBound) {
            return new MatchAllConstraint();
        }

        // MultiConstraint requires at least two constraints. Return the first available constraint
        // since there is nothing to compact.
        if (count($constraints) < 2) {
            return !$constraints ? null : reset($constraints);
        }
        return Intervals::compactConstraint(new MultiConstraint($constraints, false));
    }
}

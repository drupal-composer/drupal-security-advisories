<?php

use Doctrine\Common\Cache\FilesystemCache;
use DrupalComposer\DrupalSecurityAdvisories\Projects;
use DrupalComposer\DrupalSecurityAdvisories\UrlHelper;
use DrupalComposer\DrupalSecurityAdvisories\VersionParser;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Storage\DoctrineCacheStorage;
use Kevinrob\GuzzleCache\Strategy\GreedyCacheStrategy;

require __DIR__ . '/vendor/autoload.php';

date_default_timezone_set('UTC');

$results = array();

$stack = HandlerStack::create();
$stack->push(
  new CacheMiddleware(
    new GreedyCacheStrategy(
      new DoctrineCacheStorage(
        new FilesystemCache(__DIR__ . '/cache')
      ),
      3600
    )
  ),
  'cache'
);
$client = new Client(['handler' => $stack]);
$projects = new Projects($client);
$conflict = [];

/**
 * @param $url
 * @param \GuzzleHttp\Client $client
 *
 * @return array
 */
function fetchAllData($url, Client $client) {
  $results = [];
  $data = json_decode($client->get($url)->getBody());
  while (isset($data) && isset($data->list)) {
    $results = array_merge($results, $data->list);

    if (isset($data->next)) {
      $data = json_decode($client->get(UrlHelper::prepareUrl($data->next))->getBody());
    }
    else {
      $data = NULL;
    }
  }
  return $results;
}

// Security releases
$results = fetchAllData('https://www.drupal.org/api-d7/node.json?type=project_release&taxonomy_vocabulary_7=100&field_release_build_type=static', $client);
$securityVersions = [];
foreach ($results as $result) {
  $nid = $result->field_release_project->id;
  $core = (int) substr($result->field_release_version, 0, 1);

  // Skip D6 and older.
  if ($core < 7) {
    continue;
  }

  $project = $projects->getFromNid($nid);

  if (!$project) {
    // @todo: log error
    continue;
  }

  try {
    $is_core = ($project->field_project_machine_name == 'drupal');
    $versionGroup = $result->field_release_version_major . (($is_core && $core == 8) ? '.' . $result->field_release_version_minor : '');

    if (empty($securityVersions[$core]['drupal/' . $project->field_project_machine_name][$versionGroup])
      ||
      version_compare($securityVersions[$core]['drupal/' . $project->field_project_machine_name][$versionGroup], $result->field_release_version, '<')
    ) {
      $securityVersions[$core]['drupal/' . $project->field_project_machine_name][$versionGroup] = $result->field_release_version;
    }
  } catch (\Exception $e) {
    // @todo: log exception
    continue;
  }
}

foreach ($securityVersions as $core => $packages) {
  foreach ($packages as $package => $majorVersions) {
    foreach ($majorVersions as $versionGroup => $version) {
      $constraint = VersionParser::generateRangeConstraint($version, ($package == 'drupal/drupal'));
      if (!$constraint) {
        throw new InvalidArgumentException('Invalid version number.');
      }
      $conflict[$core][$package][] = $constraint;
    }
  }
}

// Insecure releases
$results = fetchAllData('https://www.drupal.org/api-d7/node.json?type=project_release&taxonomy_vocabulary_7=188131&field_release_build_type=static', $client);
foreach ($results as $result) {
  $nid = $result->field_release_project->id;
  $core = (int) substr($result->field_release_version, 0, 1);

  // Skip D6 and older.
  if ($core < 7) {
    continue;
  }

  $project = $projects->getFromNid($nid);

  if (!$project) {
    // @todo: log error
    continue;
  }

  try {
    $is_core = ($project->field_project_machine_name == 'drupal');
    $versionGroup = $result->field_release_version_major . (($is_core && $core == 8) ? '.' . $result->field_release_version_minor : '');

    // Cleanup core versions prior to SemVer (e.g. 8.0-alpha1).
    if ($is_core && $core == 8 && empty($result->field_release_version_patch)) {
      continue;
    }

    // Filter any individual releases older than a security release.
    if (
      !empty($securityVersions[$core]['drupal/' . $project->field_project_machine_name][$versionGroup])
      &&
      version_compare($securityVersions[$core]['drupal/' . $project->field_project_machine_name][$versionGroup], $result->field_release_version, '>')
    ) {
      continue;
    }

    $constraint = VersionParser::generateExplicitConstraint($result->field_release_version, $is_core);
    if (!$constraint) {
      throw new InvalidArgumentException('Invalid version number.');
    }
    $conflict[$core]['drupal/' . $project->field_project_machine_name][] = $constraint;
  } catch (\Exception $e) {
    // @todo: log exception
    continue;
  }
}

$target = [
  7 => 'build-7.x',
  8 => 'build-8.x',
];

foreach ($conflict as $core => $packages) {
  $composer = [
    'name' => 'drupal-composer/drupal-security-advisories',
    'description' => 'Prevents installation of composer packages with known security vulnerabilities',
    'type' => 'metapackage',
    'license' => 'GPL-2.0-or-later',
    'conflict' => []
  ];

  foreach ($packages as $package => $constraints) {
    usort($constraints, function ($a,  $b) {
      preg_match('/<?(\d+(?:.\d+)+?(?:-.+)?)$/', $a,  $aMatches);
      preg_match('/<?(\d+(?:.\d+)+?(?:-.+)?)$/', $b,  $bMatches);
      return version_compare($aMatches[1], $bMatches[1]);
    });
    $composer['conflict'][$package] = implode('|', $constraints);
  }

  // drupal/core is a subtree split for drupal/drupal and has no own SAs.
  // @see https://github.com/drush-ops/drush/issues/3448
  if (isset($composer['conflict']['drupal/drupal']) && !isset($composer['conflict']['drupal/core'])) {
    $composer['conflict']['drupal/core'] = $composer['conflict']['drupal/drupal'];
  }

  ksort($composer['conflict']);
  file_put_contents(__DIR__ . '/' . $target[$core] . '/composer.json', json_encode($composer, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
}

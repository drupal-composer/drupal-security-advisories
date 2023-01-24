<?php

use DrupalComposer\DrupalSecurityAdvisories\Projects;
use DrupalComposer\DrupalSecurityAdvisories\UrlHelper;
use DrupalComposer\DrupalSecurityAdvisories\VersionParser;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Kevinrob\GuzzleCache\CacheMiddleware;
use Kevinrob\GuzzleCache\Storage\FlysystemStorage;
use Kevinrob\GuzzleCache\Strategy\GreedyCacheStrategy;
use League\Flysystem\Adapter\Local;

require __DIR__ . '/vendor/autoload.php';

date_default_timezone_set('UTC');

$results = array();

$stack = HandlerStack::create();
$stack->push(
  new CacheMiddleware(
    new GreedyCacheStrategy(
      new FlysystemStorage(
        new Local(__DIR__ . '/cache')
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
      $url = UrlHelper::prepareUrl($data->next);
      $data = json_decode($client->get($url)->getBody());
    }
    else {
      $data = NULL;
    }
  }
  return $results;
}

// Security releases
$results = fetchAllData('https://www.drupal.org/api-d7/node.json?type=project_release&taxonomy_vocabulary_7=100&field_release_build_type=static&field_release_category[value][]=legacy&field_release_category[value][]=current', $client);
foreach ($results as $result) {
  // Skip releases with incomplete data.
  if (!property_exists($result, 'field_release_project')) {
    continue;
  }

  $nid = $result->field_release_project->id;
  $core_compat = getCoreCompat($result);

  if ($core_compat < 7) {
    continue;
  }

  $project = $projects->getFromNid($nid);

  if (!$project) {
    // @todo: log error
    continue;
  }

  try {
    $is_core = $project->field_project_machine_name == 'drupal';
    $constraint = VersionParser::generateRangeConstraint($result->field_release_version, $is_core, $result);
    if (!$constraint) {
      throw new InvalidArgumentException('Invalid version number.');
    }
    $conflict[$core_compat]['drupal/' . $project->field_project_machine_name][] = $constraint;
  } catch (\Exception $e) {
    // @todo: log exception
    continue;
  }
}

// Insecure releases
$results = fetchAllData('https://www.drupal.org/api-d7/node.json?type=project_release&taxonomy_vocabulary_7=188131&field_release_build_type=static&field_release_category[value][]=legacy&field_release_category[value][]=current', $client);
foreach ($results as $result) {
  // Skip releases with incomplete data.
  if (!property_exists($result, 'field_release_project')) {
    continue;
  }

  $nid = $result->field_release_project->id;
  $core_compat = getCoreCompat($result);

  // Skip D6 and older.
  if ($core_compat < 7) {
    continue;
  }

  $project = $projects->getFromNid($nid);

  if (!$project) {
    // @todo: log error
    continue;
  }

  try {
    $is_core = $project->field_project_machine_name == 'drupal';
    $constraint = VersionParser::generateExplicitConstraint($result->field_release_version, $is_core, $result);
    if (!$constraint) {
      throw new InvalidArgumentException('Invalid version number.');
    }
    $conflict[$core_compat]['drupal/' . $project->field_project_machine_name][] = $constraint;
  } catch (\Exception $e) {
    // @todo: log exception
    continue;
  }
}

$target = [
  7 => 'build-7.x',
  8 => 'build-9.x',
];

foreach ($conflict as $core_compat => $packages) {
  $composer = [
    'name' => 'drupal-composer/drupal-security-advisories',
    'description' => 'Prevents installation of composer packages with known security vulnerabilities',
    'type' => 'metapackage',
    'license' => 'GPL-2.0-or-later',
    'conflict' => []
  ];

  foreach ($packages as $package => $constraints) {
    natsort($constraints);
    $composer['conflict'][$package] = implode('|', $constraints);
  }

  // drupal/core is a subtree split for drupal/drupal and has no own SAs.
  // @see https://github.com/drush-ops/drush/issues/3448
  if (isset($composer['conflict']['drupal/drupal']) && !isset($composer['conflict']['drupal/core'])) {
    $composer['conflict']['drupal/core'] = $composer['conflict']['drupal/drupal'];
  }

  ksort($composer['conflict']);
  file_put_contents(__DIR__ . '/' . $target[$core_compat] . '/composer.json', json_encode($composer, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
}

/**
 * @param $result
 *
 * @return int
 */
function getCoreCompat($result) {
  return match ($result->field_release_category) {
    'legacy' => 7,
    // Drupal's module API goes no higher than 8. Drupal 9 core advisories are published in this project's 8.x branch.
    'current' => 8,
    default => -1
  };
}

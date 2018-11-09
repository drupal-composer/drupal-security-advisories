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
    $is_core = ($project->field_project_machine_name == 'drupal') ? TRUE : FALSE;
    $constraint = VersionParser::generateRangeConstraint($result->field_release_version, $is_core);
    if (!$constraint) {
      throw new InvalidArgumentException('Invalid version number.');
    }
    $conflict[$core]['drupal/' . $project->field_project_machine_name][] = $constraint;
  } catch (\Exception $e) {
    // @todo: log exception
    continue;
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
    $is_core = ($project->field_project_machine_name == 'drupal') ? TRUE : FALSE;
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
    natsort($constraints);
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

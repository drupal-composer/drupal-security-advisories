<?php

use GuzzleHttp\Subscriber\Cache\CacheSubscriber;

require __DIR__ . '/vendor/autoload.php';

date_default_timezone_set('UTC');

$results = array();

$client = new \GuzzleHttp\Client();
$storage = new \GuzzleHttp\Subscriber\Cache\CacheStorage(new \Doctrine\Common\Cache\FilesystemCache(__DIR__ . '/cache'));
CacheSubscriber::attach($client, ['storage' => $storage]);

$data = json_decode($client->get('https://www.drupal.org/api-d7/node.json?type=project_release&taxonomy_vocabulary_7=100&field_release_build_type=static')->getBody());

$projects = [];
$conflict = [];

class UrlHelper {

  public static function prepareUrl($url) {
    return str_replace('https://www.drupal.org/api-d7/node', 'https://www.drupal.org/api-d7/node.json', $url);
  }

}

class VersionParser {

  public static function getSemVer($version, $isCore) {
    $version = $isCore ? static::handleCore($version) : static::handleContrib($version);
    return static::isValid($version) ? $version : FALSE;
  }

  public static function handleCore($version) {
    return $version;
  }

  public static function handleContrib($version) {
    list($core, $version) = explode('-', $version, 2);
    return $version;
  }

  public static function isValid($version) {
    return (strpos($version, 'unstable') === FALSE);
  }

}

while (isset($data) && isset($data->list)) {
  $results = array_merge($results, $data->list);

  if (isset($data->next)) {
    $data = json_decode($client->get(UrlHelper::prepareUrl($data->next))->getBody());
  }
  else {
    $data = NULL;
  }
}

foreach ($results as $result) {
  $nid = $result->field_release_project->id;
  $core = (int) substr($result->field_release_version, 0, 1);

  // Skip D6 and older.
  if ($core < 7) {
    continue;
  }

  try {
    if (!isset($projects[$nid])) {
      $project = json_decode($client->get('https://www.drupal.org/api-d7/node.json?nid=' . $nid)->getBody());
      $projects[$nid] = $project->list[0];
    }
  } catch (\GuzzleHttp\Exception\ServerException $e) {
    // @todo: log exception
    continue;
  }

  try {
    $project = $projects[$nid];
    $is_core = ($project->field_project_machine_name == 'drupal') ? TRUE : FALSE;
    $version = VersionParser::getSemVer($result->field_release_version, $is_core);
    if (!$version) {
      throw new InvalidArgumentException('Invalid version number.');
    }
    $conflict[$core]['drupal/' . $project->field_project_machine_name][] = '<' . $version;
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
    'type' => 'metapackage',
    'license' => 'GPL-2.0+',
    'conflict' => []
  ];

  foreach ($packages as $package => $constraints) {
    natsort($constraints);
    $composer['conflict'][$package] = implode(',', $constraints);
  }

  ksort($composer['conflict']);
  file_put_contents(__DIR__ . '/' . $target[$core] . '/composer.json', json_encode($composer, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
}

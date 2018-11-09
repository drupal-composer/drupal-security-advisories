<?php

namespace DrupalComposer\DrupalSecurityAdvisories;

class Projects {

  protected $storage = [];

  protected $client;

  public function __construct(\GuzzleHttp\Client $client) {
    $this->client = $client;
  }

  public function getFromNid($nid) {
    try {
      if (!isset($this->storage[$nid])) {
        $project = json_decode($this->client->get('https://www.drupal.org/api-d7/node.json?nid=' . $nid)->getBody());
        $this->storage[$nid] = $project->list[0];
      }
    } catch (\GuzzleHttp\Exception\ServerException $e) {
      $this->storage[$nid] = [];
    }
    return $this->storage[$nid];
  }

}

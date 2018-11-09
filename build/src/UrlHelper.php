<?php

namespace DrupalComposer\DrupalSecurityAdvisories;

class UrlHelper {

  public static function prepareUrl($url) {
    return str_replace('https://www.drupal.org/api-d7/node', 'https://www.drupal.org/api-d7/node.json', $url);
  }

}

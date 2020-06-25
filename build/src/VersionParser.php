<?php

namespace DrupalComposer\DrupalSecurityAdvisories;

class VersionParser {

  public static function generateRangeConstraint($version, $isCore, $result) {
    if (!static::isValid($version)) {
      return FALSE;
    }
    return $isCore ? static::handleCore($version) : static::handleContrib($version, $result);
  }

  public static function generateExplicitConstraint($version, $isCore, $result) {
    if (!static::isValid($version)) {
      return FALSE;
    }
    if ($isCore) {
      return $version;
    }
    else {
      // $result->taxonomy_vocabulary_6 is usually a term like 8.x (https://www.drupal.org/taxonomy/term/7234).
      // Its absence indicates a semver release (or a core release).
      list($core, $version) = empty($result->taxonomy_vocabulary_6) ? [NULL, $version] : explode('-', $version, 2);
    }
    return $version;
  }

  public static function handleCore($version) {
    list($major, $minor) = explode('.', $version);
    return ">=$major.$minor,<$version";
  }

  public static function handleContrib($version, $result) {
    // $result->taxonomy_vocabulary_6 is usually a term like 8.x (https://www.drupal.org/taxonomy/term/7234).
    // Its absence indicates a semver release (or a core release).
    list($core, $version) = empty($result->taxonomy_vocabulary_6) ? [NULL, $version] : explode('-', $version, 2);
    list($major, $minor) = explode('.', $version, 2);
    return ">=$major.$minor,<$version";
  }

  public static function isValid($version) {
    return (strpos($version, 'unstable') === FALSE);
  }

}

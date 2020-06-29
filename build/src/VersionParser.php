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
    $version_components = explode('.', $version);
    if (count($version_components) === 2) {
      // Only major.minor, either Drupal core 7, or contrib that had an API compatibility prefix.
      list($major) = $version_components;
      return ">=$major,<$version";
    }
    elseif (count($version_components) === 3) {
      // Semver, either Drupal core 8 or later, or contrib using semver.
      list($major, $minor) = $version_components;
      return ">=$major.$minor,<$version";
    }
    else {
      // Should not happen. An exception will be thrown by the caller.
      return NULL;
    }
  }

  public static function isValid($version) {
    return (strpos($version, 'unstable') === FALSE);
  }

}

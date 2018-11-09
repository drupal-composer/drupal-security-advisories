<?php

namespace DrupalComposer\DrupalSecurityAdvisories;

class VersionParser {

  public static function generateRangeConstraint($version, $isCore) {
    if (!static::isValid($version)) {
      return FALSE;
    }
    return $isCore ? static::handleCore($version) : static::handleContrib($version);
  }

  public static function generateExplicitConstraint($version, $isCore) {
    if (!static::isValid($version)) {
      return FALSE;
    }
    if ($isCore) {
      return $version;
    }
    else {
      list($core, $version) = explode('-', $version, 2);
    }
    return $version;
  }

  public static function handleCore($version) {
    list($major, $minor) = explode('.', $version);
    return ">=$major.$minor,<$version";
  }

  public static function handleContrib($version) {
    list($core, $version) = explode('-', $version, 2);
    list($major) = explode('.', $version);
    return ">=$major,<$version";
  }

  public static function isValid($version) {
    return (strpos($version, 'unstable') === FALSE);
  }

}

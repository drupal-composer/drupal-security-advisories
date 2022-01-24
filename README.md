# Drupal Security Advisories for Composer

This package ensures that your application doesn't have installed dependencies with known security vulnerabilities. Inspired by [Roave Security Advisories](https://github.com/Roave/SecurityAdvisories).

[![Circle CI](https://circleci.com/gh/drupal-composer/drupal-security-advisories/tree/main.svg?style=svg)](https://circleci.com/gh/drupal-composer/drupal-security-advisories/tree/main)

## Installation

### Drupal 9+ ([composer.json](https://github.com/drupal-composer/drupal-security-advisories/blob/9.x/composer.json))

```sh
~$ composer require drupal-composer/drupal-security-advisories:dev-9.x
```

### Drupal 7 ([composer.json](https://github.com/drupal-composer/drupal-security-advisories/blob/7.x/composer.json))

```sh
~$ composer require drupal-composer/drupal-security-advisories:dev-7.x
```

# Usage

This package does not provide any API or usable classes: its only purpose is to prevent installation of software with known and documented security issues.

# Stability

This package can only be required in its dev-* version: there will never be stable/tagged versions because of the nature of the problem being targeted. Security issues are in fact a moving target, and locking your project to a specific tagged version of the package would not make any sense.

This package is therefore only suited for installation in the root of your deployable project.

# Handling Failures

In the rare event that a security release does not affect your project, and upgrading to latest release is undesireable, you can suppress a build failure by specifying a particular SHA project in composer.json. For example, assume that drupal/dynamic_entity_reference 8.1.0-beta2 just came out as a Security release. In order to keep using 8.1.0-beta1, you can specify the following in composer.json:

```
"require": {
  "drupal/dynamic_entity_reference": "dev-8.x-1.x#8713890"
},

 ```

Note: that this approach opts your package out of any future security releases. You can check for future security releases with `drush pm:security` (drush9) or `drush pm-updatestatus` (drush8).

# Sources

This packages gets information form Drupal.org APIs.

Build command: ```./build/build.sh```

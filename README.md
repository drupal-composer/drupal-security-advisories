# Drupal Security Advisories for Composer

This package ensures that your application doesn't have installed dependencies with known security vulnerabilities. Inspired by [Roave Security Advisories](https://github.com/Roave/SecurityAdvisories).

## Installation

### Drupal 8 ([composer.json](https://github.com/webflo/drupal-security-advisories/blob/8.0.x/composer.json))

```sh
~$ composer require webflo/drupal-security-advisories:8.0.x-dev
```

### Drupal 7 ([composer.json](https://github.com/webflo/drupal-security-advisories/blob/7.x/composer.json))

```sh
~$ composer require webflo/drupal-security-advisories:7.x-dev
```

# Usage

This package does not provide any API or usable classes: its only purpose is to prevent installation of software with known and documented security issues.

# Stability

This package can only be required in its dev-* version: there will never be stable/tagged versions because of the nature of the problem being targeted. Security issues are in fact a moving target, and locking your project to a specific tagged version of the package would not make any sense.

This package is therefore only suited for installation in the root of your deployable project.

# Sources

This packages gets information form Drupal.org APIs.

Build command: ```./build/build.sh```

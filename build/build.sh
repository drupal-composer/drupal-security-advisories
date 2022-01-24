#!/bin/sh -xe

REPO="https://github.com/drupal-composer/drupal-security-advisories.git"
cd build
composer install --no-interaction --no-progress
rm -rf build-7.x build-9.x

git clone --branch 7.x $REPO build-7.x
git clone --branch 9.x $REPO build-9.x

php build-composer-json.php

cd build-7.x
if [ ! -z "$(git status --porcelain)" ]
then
  git add composer.json
  git commit -m "Update composer.json"
  git push origin HEAD

  # Publish 7.x as 7.x-v2
  git push origin HEAD:7.x-v2
fi
cd ..

cd build-9.x
if [ ! -z "$(git status --porcelain)" ]
then
  git add composer.json
  git commit -m "Update composer.json"
  git push origin HEAD

  # Publish 9.x as 8.x-v2 (used by drush pm:security)
  git push origin HEAD:8.x-v2
fi
cd ..

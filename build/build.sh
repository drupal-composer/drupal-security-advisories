#!/bin/sh -xe

REPO="https://github.com/drupal-composer/drupal-security-advisories.git"
cd build
composer install --no-interaction --no-progress
rm -rf build-7.x build-8.x

git clone --branch 7.x $REPO build-7.x
git clone --branch 8.x $REPO build-8.x

php build-composer-json.php

cd build-7.x
if [ ! -z "$(git status --porcelain)" ]
then
  git add composer.json
  git commit -m "Update composer.json"
  git push origin HEAD
fi
cd ..

cd build-8.x
if [ ! -z "$(git status --porcelain)" ]
then
  git add composer.json
  git commit -m "Update composer.json"
  git push origin HEAD
fi
cd ..

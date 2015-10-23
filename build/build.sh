#!/bin/sh -x

REPO="https://github.com/webflo/drupal-security-advisories.git"
cd build
composer install --no-interaction
rm -rf build-7.x build-8.0.x

git clone --branch 7.x $REPO build-7.x
git clone --branch 8.0.x $REPO build-8.0.x

php build-composer-json.php

cd build-7.x
git add composer.json
git commit -m "Update composer.json"
git push origin HEAD
cd ..

cd build-8.0.x
git add composer.json
git commit -m "Update composer.json"
git push origin HEAD
cd ..

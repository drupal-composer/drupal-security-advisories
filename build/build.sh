#!/bin/sh -x

REPO="https://github.com/webflo/drupal-security-advisories.git"
cd build
composer install
rm -rf build-7.x build-8.0.x

if [ ! -d "build-7.x" ]; then
  git clone --branch 7.x $REPO build-7.x
  if [ ! -d "build-7.x" ]; then
    git clone $REPO build-7.x
    cd build-7.x
    git checkout --orphan 7.x
    git rm --cached -- *
    cd ..
  fi
fi

if [ ! -d "build-8.0.x" ]; then
  git clone --branch 8.0.x $REPO build-8.0.x
  if [ ! -d "build-8.0.x" ]; then
    git clone $REPO build-8.0.x
    cd build-8.0.x
    git checkout --orphan 8.0.x
    git rm --cached -- *
    cd ..
  fi
fi

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

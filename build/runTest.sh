#!/usr/bin/env bash

cd $1
SLUG=$(grep -Pho 'cache/[A-Za-z-\.]+' composer.json | xargs | awk '{print $1}')

printf "\n\n************ Opening directory: $1 ************\n"
printf     "************ Running tests for: $SLUG ************\n\n"

if [ ! -z "$BUILD_ALL" ]; then composer require --no-update mongodb/mongodb:^1.0; fi
if [ ! -z "$BUILD_ALL" ]; then composer require --no-update predis/predis:^1.0; fi

composer install --no-interaction || exit -1

TEST="./vendor/bin/phpunit"
if [ ! -z "$BUILD_ALL" ]; then TEST="$TEST --coverage-clover=coverage.xml"; fi

sh -c "$TEST" || exit -1

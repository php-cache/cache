#!/usr/bin/env bash

cd $1
SLUG=$(grep -Pho 'cache/[A-Za-z-\.]+' composer.json | xargs | awk '{print $1}')

printf "\n\n************ Opening directory: $1 ************\n"
printf     "************ Running tests for: $SLUG ************\n\n"


if [ ! -z "$BUILD_ALL" ]; then composer require --no-update mongodb/mongodb:^1.0; fi
composer install --no-interaction

TEST="./vendor/bin/paratest -p 6 --runner WrapperRunner"
if [[ ! -z "$BUILD_ALL" && "$TRAVIS_PHP_VERSION" == '7.0' ]]; then TEST="$TEST --coverage-clover=coverage.xml"; fi

if [ "$TRAVIS_PHP_VERSION" == '7.0' ]
then
    phpdbg -qrr $TEST
else
    sh -c "$TEST"
fi

#!/usr/bin/env bash

SLUG=$(grep -Pho 'cache/[A-Za-z-\.]+' $1/composer.json | xargs | awk '{print $1}')
printf "\n\n************ Running tests for $SLUG ************\n\n"

cd $1
if [ $TRAVIS_PHP_VERSION = 5.5 ]; then composer require --no-update phpunit/phpunit:~4.0 cache/integration-tests:$INTEGRATION_TEST_VERSION; fi
composer install --no-interaction --prefer-source --ignore-platform-reqs

TEST="./vendor/bin/phpunit $2"

printf "On $SLUG\n"
printf "Command: $TEST\n\n"
if [ "$TRAVIS_PHP_VERSION" == '7.0' ]
then
    phpdbg -qrr $TEST
else
    sh -c "$TEST"
fi

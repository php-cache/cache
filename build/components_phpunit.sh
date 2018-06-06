#!/usr/bin/env bash

source ./build/tfold.sh

COMPONENTS=$(find src -mindepth 2 -type f -name phpunit.xml.dist -printf '%h\n')

set -e

if [[ "$DEPS" = "high" ]]; then
    echo "$COMPONENTS" | parallel --gnu -j10% "tfold {} 'cd {} && $COMPOSER_UP && $PHPUNIT_X'"
elif [[ "$DEPS" = "low" ]]; then
    sleep 3
    echo "$COMPONENTS" | parallel --gnu -j10% "tfold {} 'cd {} && $COMPOSER_UP --prefer-lowest --prefer-stable && $PHPUNIT_X'"
else
    tfold "Composer install" "$COMPOSER_UP"

    echo "$COMPONENTS" | parallel --gnu "tfold {} $PHPUNIT_X {}"
fi

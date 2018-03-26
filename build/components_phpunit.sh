#!/usr/bin/env bash

source ./build/try_catch.sh
source ./build/tfold.sh

COMPONENTS=$(find src -mindepth 2 -type f -name phpunit.xml.dist -printf '%h\n')

try
    if [[ "$DEPS" = "high" ]]; then
        echo "$COMPONENTS" | parallel --gnu -j10% "tfold {} 'cd {} && $COMPOSER_UP && $PHPUNIT_X'"
    elif [[ "$DEPS" = "low" ]]; then
        sleep 3
        echo "$COMPONENTS" | parallel --gnu -j10% "tfold {} 'cd {} && $COMPOSER_UP --prefer-lowest --prefer-stable && $PHPUNIT_X'"
    else
        echo "$COMPONENTS" | parallel --gnu "tfold {} $PHPUNIT_X {}"

        tfold tty-group $PHPUNIT --group tty
    fi
catch || {
    exit 1
}

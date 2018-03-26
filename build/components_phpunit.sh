#!/usr/bin/env bash

source ./build/try_catch.sh
source ./build/tfold.sh

COMPONENTS=$(find src -mindepth 2 -type f -name phpunit.xml.dist -printf '%h\n')

try
    echo -e '{\n"minimum-stability":"dev"}' > composer.json
    php ./build/travis/build_packages.php HEAD^ $COMPONENTS

    if [[ "$DEPS" = "high" ]]; then
        echo "$COMPONENTS" | parallel --gnu -j10% "tfold {} 'cd {} && $COMPOSER_UP && $TEST --verbose'"
    elif [[ "$DEPS" = "low" ]]; then
        echo "$COMPONENTS" | parallel --gnu -j10% "tfold {} 'cd {} && $COMPOSER_UP --prefer-lowest --prefer-stable && $TEST --verbose'"
    else
        echo "$COMPONENTS" | parallel --gnu "tfold {} $PHPUNIT_X {}"
        tfold tty-group $PHPUNIT --group tty
    fi
catch || {
    exit 1
}

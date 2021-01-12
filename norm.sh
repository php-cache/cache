#!/bin/bash

CURRENT_DIR=$(pwd)
ok=0
for PACKAGE in $(find src -maxdepth 4 -type f -name phpunit.xml.dist -exec dirname {} \; | sort)
do
    echo ::group::$PACKAGE
    echo "$CURRENT_DIR/$PACKAGE"
    cd "$CURRENT_DIR/$PACKAGE"

    localExit=0
    composer update --no-interaction --prefer-dist --optimize-autoloader $COMPOSER_OPTIONS || localExit=1
    ok=$(( $localExit || $ok ))
    echo ::endgroup::
    if [ $localExit -ne 0 ]; then
      echo "::error::$PACKAGE error"
    fi
    composer normalize
done

#!/usr/bin/env bash

if [ $# -eq 0 ]
  then
    echo "No arguments supplied. You need to specify the version to composer."
    exit 1
fi

VERSION=$1
ROOT=$(pwd)

# Run for each components
find src -mindepth 2 -type f -name phpunit.xml.dist | while read line; do
 DIR=$(dirname $line)
 cd $DIR
 pwd
 composer require --dev --no-update cache/integration-test:$VERSION
 cd $ROOT
 echo ""
done

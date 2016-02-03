#!/usr/bin/env bash

if [ $# -eq 0 ]
then
    echo "No arguments supplied. You need to specify the package, and the version to composer."
    exit 1
fi

PACKAGE=$1
VERSION=$2
ROOT=$(pwd)

# Run for each components
find src -mindepth 2 -type f -name phpunit.xml.dist | while read line; do
   # Save the directory name
   DIR=$(dirname $line)

   # Go to that directory
   cd $DIR
   pwd

   # Let composer update the composer.json
   composer require --dev --no-update $PACKAGE:$VERSION

   # Go back to the root
   cd $ROOT
   echo ""
done

# Update integration test for the root
pwd
composer require --dev --no-update $PACKAGE:$VERSION
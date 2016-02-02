#!/usr/bin/env bash

# Run for each components
COMPONENTS=$(find src/ -mindepth 3 -type f -name phpunit.xml.dist -printf '%h\n')
if [ command -v parallel >/dev/null 2>&1 ]
then
    # Exists
    echo $COMPONENTS | parallel --gnu './build/runTest.sh {}'

else
    # Doesn't Exist
    echo $COMPONENTS | xargs -n 1 ./build/runTest.sh
fi

# Run for main repo. Generate coverage
COVERAGE=coverage.xml
if [ -f $COVERAGE ]; then rm $COVERAGE; fi

./build/runTest.sh ./ --coverage-clover=$COVERAGE
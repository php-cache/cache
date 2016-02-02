# Build Scripts

This directory contains the scripts that travis uses to build the project.

The scripts in [the php directory](php/) are ran when travis is testing the given version. [`all.sh`](php/all.sh) is ran for every version.

Tests are ran using [`runTests.sh`](runTests.sh). This file grabs all the sub projects, and runs [`runTest.sh`](runTest.sh).
[`runTest.sh`](runTest.sh) goes into each directory, installs composer (ignoring platform reqs), and then runs the tests.
In the case of `php 5.5`, `phpunit ^4.0` is installed, to prevent test failures.

After running tests on all of the sub projects, [`runTests.sh`](runTests.sh) runs tests on the entire [`php-cache/cache`](https://github.com/php-cache/cache) repository, 
and then sends code coverage reports out to [Codecov](https://codecov.io/github/php-cache/cache).
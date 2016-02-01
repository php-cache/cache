#!/usr/bin/env bash

# Disable xdebug when hhvm or when SEND_COVERAGE is false
if [ $TRAVIS_PHP_VERSION != hhvm && $SEND_COVERAGE != true ]; then phpenv config-rm xdebug.ini; fi

# Doing something with phpenv
if [ $TRAVIS_PHP_VERSION != 'hhvm' ]; then phpenv config-add ./tests/travis/php.ini; fi

# Install codecov
pip install --user codecov

# Install Memcached extension
if [ $TRAVIS_PHP_VERSION != 'hhvm' ]; then echo "extension = memcache.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini; fi;
if [ $TRAVIS_PHP_VERSION != 'hhvm' ]; then echo "extension = memcached.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini; fi;

# Install Redis extension
mkdir -p ~/.phpenv/versions/$(phpenv version-name)/etc/conf.d
echo "extension=redis.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/conf.d/travis.ini
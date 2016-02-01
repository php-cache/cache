#!/usr/bin/env bash

# Disable xdebug when hhvm or when SEND_COVERAGE is false
if [ $TRAVIS_PHP_VERSION != hhvm && $TRAVIS_PHP_VERSION != '7.0' ]; then phpenv config-rm xdebug.ini; fi

# Doing something with phpenv
if [ $TRAVIS_PHP_VERSION != 'hhvm' ]; then phpenv config-add ./build/php.ini; fi

# Install codecov
pip install --user codecov

# Enable extension
mkdir -p ~/.phpenv/versions/$(phpenv version-name)/etc/conf.d
echo "extension = mongodb.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
echo "extension=redis.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/conf.d/travis.ini
echo "extension = memcache.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
echo "extension = memcached.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini

#!/usr/bin/env bash

# Disable xdebug
phpenv config-rm xdebug.ini

# Add php.ini settings
phpenv config-add ./build/php/php.ini

# Install codecov
pip install --user codecov

# Enable extension
echo "extension = redis.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/conf.d/travis.ini
echo "extension = memcached.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini


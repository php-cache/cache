#!/usr/bin/env bash

echo "Install APCu Adapter dependencies"
yes '' | pecl install apcu-5.1.0

echo "Install memcache(d)"
yes '' | pecl install memcache memcached

echo "Enable extension"
echo "extension = redis.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/conf.d/travis.ini

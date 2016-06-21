#!/usr/bin/env bash

echo "Install redis"
yes '' | pecl install redis-3.0.0

echo "Enable extension"
echo "extension = redis.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/conf.d/travis.ini

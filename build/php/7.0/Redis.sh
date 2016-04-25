#!/usr/bin/env bash

echo "Install redis"
yes '' | pecl install redis

echo "Enable extension"
echo "extension = redis.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/conf.d/travis.ini
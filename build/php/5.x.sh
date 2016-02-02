#!/usr/bin/env bash

echo "Install APC Adapter & APCu Adapter dependencies"
yes '' | pecl install apcu-4.0.8

echo "Install redis"
yes '' | pecl install redis

echo "Enable extension"
echo "extension = mongodb.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
echo "extension = memcache.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini

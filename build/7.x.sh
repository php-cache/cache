#!/usr/bin/env bash

# Install APCu Adapter dependencies
yes '' | pecl install apcu-5.1.0

# Install memcache(d)
yes '' | pecl install memcache memcached

# Install redis
yes '' | pecl install redis

# Install Mongo
yes '' | pecl install mongodb

echo "extension = apcu.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
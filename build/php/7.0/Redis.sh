#!/usr/bin/env bash

echo "Install Redis"
yes '' | pecl install -f redis-3.0.0

echo "extension=redis.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini

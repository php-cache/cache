#!/usr/bin/env bash

echo "Install redis"
yes '' | pecl install -f redis-3.1.6

echo "extension=redis" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini

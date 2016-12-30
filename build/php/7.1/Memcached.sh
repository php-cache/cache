#!/usr/bin/env bash

echo "Install memcache(d)"
yes '' | pecl install memcached

echo "Enable extension"
echo "extension = memcached.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini
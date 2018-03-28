#!/usr/bin/env bash

echo "Install memcache(d)"
yes '' | pecl install memcached

echo "extension=memcached.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini

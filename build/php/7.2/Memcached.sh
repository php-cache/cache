#!/usr/bin/env bash

echo "Install memcache(d)"
yes '' | pecl install memcached-3.0.4

echo "extension=memcached" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini

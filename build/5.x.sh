#!/usr/bin/env bash

# Install APC Adapter & APCu Adapter dependencies
yes '' | pecl install apc
yes '' | pecl install apcu-4.0.8

echo "extension = apc.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini;
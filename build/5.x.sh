#!/usr/bin/env bash

# Install APC Adapter & APCu Adapter dependencies
yes '' | pecl install apcu-4.0.8

# Install Mongo
echo "extension = mongodb.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini;

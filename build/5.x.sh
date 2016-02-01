#!/usr/bin/env bash

# Install APC Adapter & APCu Adapter dependencies
echo "extension = apc.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini;
yes '' | pecl install apcu-4.0.8

# Install Mongo
echo "extension = mongo.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini;
echo "extension = mongodb.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini;

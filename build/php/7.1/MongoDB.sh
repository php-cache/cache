#!/usr/bin/env bash

echo "Install MongoDB"
pecl install -f mongodb-1.1.2

echo "extension=mongo.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini

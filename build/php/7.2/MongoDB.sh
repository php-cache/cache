#!/usr/bin/env bash

echo "Install MongoDB"
pecl install -f mongodb-1.4.2

echo "extension=mongo" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini

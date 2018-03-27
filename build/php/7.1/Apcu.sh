#!/usr/bin/env bash

echo "Install APCu Adapter dependencies"
yes '' | pecl install -f apcu-5.1.8

echo "Add php.ini settings"
phpenv config-add ./build/php/apc.ini


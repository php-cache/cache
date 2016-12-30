#!/usr/bin/env bash

echo "Add php.ini settings"
phpenv config-add ./build/php/apc.ini

echo "Install APCu Adapter dependencies"
yes '' | pecl install apcu
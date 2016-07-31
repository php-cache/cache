#!/usr/bin/env bash

PHP_INI_FILE=`php -r "echo php_ini_loaded_file();"`

echo "Add php.ini settings"
phpenv config-add ./build/php/apc.ini

echo "Install APCu Adapter dependencies"
yes '' | pecl install apcu

echo "Install APCu_bc Adapter dependencies"
yes '' | pecl install apcu_bc-1.0.3

# deleting apc.so from php.ini

sed -i '1d' ${PHP_INI_FILE}

phpenv config-add ./build/php/apcu_bc.ini

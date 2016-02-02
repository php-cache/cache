#!/usr/bin/env bash

echo "Disable xdebug"
phpenv config-rm xdebug.ini

echo "Add php.ini settings"
phpenv config-add ./build/php/php.ini

echo "Install codecov"
pip install --user codecov

echo "Install Mongo"
sudo apt-key adv --keyserver hkp://keyserver.ubuntu.com:80 --recv 7F0CEB10
sudo apt-key adv --keyserver hkp://keyserver.ubuntu.com:80 --recv EA312927
echo "deb http://repo.mongodb.com/apt/ubuntu precise/mongodb-enterprise/3.2 multiverse" | sudo tee /etc/apt/sources.list.d/mongodb.list
sudo apt-get update -qq
sudo apt-get install mongodb-enterprise
sudo apt-get -y install gdb

if ! nc -z localhost 27017; then sudo service mongod start; fi
mongod --version
pecl install -f mongodb-1.1.2
mongo --eval 'tojson(db.runCommand({buildInfo:1}))'
php --ri mongodb

echo "Enable extension"
echo "extension = memcached.so" >> ~/.phpenv/versions/$(phpenv version-name)/etc/php.ini


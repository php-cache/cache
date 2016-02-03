#!/usr/bin/env bash

echo "Install Mongo"
sudo apt-key adv --keyserver hkp://keyserver.ubuntu.com:80 --recv 7F0CEB10
sudo apt-key adv --keyserver hkp://keyserver.ubuntu.com:80 --recv EA312927
echo "deb http://repo.mongodb.com/apt/ubuntu precise/mongodb-enterprise/3.2 multiverse" | sudo tee /etc/apt/sources.list.d/mongodb.list
sudo apt-get update -qq
sudo apt-get install mongodb-enterprise
sudo apt-get -y install gdb

if ! nc -z localhost 27017; then sudo service mongod start; fi

pecl install -f mongodb-1.1.2


#!/usr/bin/env bash

# Install APC Adapter & APCu Adapter dependencies
yes '' | pecl install apcu-5.1.0

# Install Mongo

export KEY_SERVER="hkp://keyserver.ubuntu.com:80"
export MONGO_REPO_URI="http://repo.mongodb.com/apt/ubuntu"
export MONGO_REPO_TYPE="precise/mongodb-enterprise/"

sudo apt-key adv --keyserver ${KEY_SERVER} --recv 7F0CEB10
sudo apt-key adv --keyserver ${KEY_SERVER} --recv EA312927
echo "deb ${MONGO_REPO_URI} ${MONGO_REPO_TYPE}${SERVER_VERSION} multiverse" | sudo tee /etc/apt/sources.list.d/mongodb.list
sudo apt-get update -qq
sudo apt-get install mongodb-enterprise
sudo apt-get -y install gdb


if ! nc -z localhost 27017; then sudo service mongod start; fi
mongod --version
pecl install -f mongodb-1.1.2
mongo --eval 'tojson(db.runCommand({buildInfo:1}))'
php --ri mongodb
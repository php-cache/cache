#!/usr/bin/env bash

# Install APC Adapter & APCu Adapter dependencies
yes '' | pecl install apcu-4.0.8

# Install MongoDB Adapter dependencies
./installMongo.sh
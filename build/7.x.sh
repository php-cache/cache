#!/usr/bin/env bash

# Install APC Adapter & APCu Adapter dependencies
yes '' | pecl install apcu

yes '' | pecl install apcu_bc-beta

# Install memcache(d)
yes '' | pecl install memcache memcached

# Install redis
yes '' | pecl install redis

# Install Mongo
yes '' | pecl install mongodb
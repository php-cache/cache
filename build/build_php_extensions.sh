#!/usr/bin/env bash

source ./build/tpecl.sh

if [ $APCU_VERSION ] && [ ${TRAVIS_PHP_VERSION:0:3} != "7.2" ]; then tpecl apcu-${APCU_VERSION} apcu.so; fi
if [ $MEMCACHE_VERSION ] && [ ${TRAVIS_PHP_VERSION:0:1} == "5" ]; then tpecl memcache-${MEMCACHE_VERSION} memcache.so; fi
if [[ $MEMCACHED_VERSION ]]; then echo extension=memcached.so >> ~/.phpenv/versions/$(phpenv version-name)/etc/conf.d/travis.ini; fi
if [[ $MONGODB_VERSION ]]; then
    tpecl mongodb-${MONGODB_VERSION} mongodb.so
    
    if [[ ! $DEPS ]]; then
        composer require mongodb/mongodb
    fi
fi
if [[ $REDIS_VERSION ]]; then tpecl redis-${REDIS_VERSION} redis.so; fi

if [[ $APCU_VERSION ]]; then phpenv config-add ./build/php/apc.ini; fi
if [ $MEMCACHE_VERSION ] && [ ${TRAVIS_PHP_VERSION:0:1} == "5" ]; then phpenv config-add ./build/php/memcache.ini; fi

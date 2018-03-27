#!/usr/bin/env bash

source ./build/tpecl.sh

FILE_EXTENSION='.so';

if [[ ${TRAVIS_PHP_VERSION:0:3} == 7.2 ]]; then FILE_EXTENSION=''; fi

if [[ $APCU_VERSION != false ]]; then tpecl apcu-${APCU_VERSION} apcu${FILE_EXTENSION}; fi
if [[ $MEMCACHE_VERSION != false ]]; then tpecl memcache-${MEMCACHE_VERSION} memcache${FILE_EXTENSION}; fi
if [[ $MEMCACHED_VERSION != false ]]; then tpecl memcached-${MEMCACHED_VERSION} memcached${FILE_EXTENSION}; fi
if [[ $MONGODB_VERSION != false ]]; then tpecl mongodb-${MONGODB_VERSION} mongodb${FILE_EXTENSION}; fi
if [[ $REDIS_VERSION != false ]]; then tpecl redis-${REDIS_VERSION} redis${FILE_EXTENSION}; fi

if [[ $APCU_VERSION != false ]]; then phpenv config-add ./build/php/apc.ini; fi
if [[ $MEMCACHE_VERSION != false ]]; then phpenv config-add ./build/php/memcache.ini; fi

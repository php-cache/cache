#!/usr/bin/env bash

cd $1
SLUG=$(grep -o 'cache/[A-Za-z\-\.]*' composer.json | xargs | awk '{print $1}')

printf "\n\n\n\n\n\n"
printf "****************************************************************************************\n"
printf "****************************************************************************************\n"
printf "****************************************************************************************\n"
printf "***\n"
printf "***  Opening directory: $1 \n"
printf "***  Running tests for: $SLUG \n"
printf "***\n"
printf "****************************************************************************************\n"
printf "****************************************************************************************\n\n"

if [ "$SLUG" = 'cache/cache' ]; then composer require --no-update mongodb/mongodb:^1.0 predis/predis:^1.0; fi

composer update --no-interaction --prefer-stable --no-progress --prefer-dist || exit 1

sh -c "$TEST" || exit 1

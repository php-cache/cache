# Contributing to PHP Cache

Open pull requests against `master`. Keep each change focused, and add tests for changed behavior.

## Set up the repository

PHP Cache requires PHP 8.2 or later and Composer 2.

```bash
git clone git@github.com:php-cache/cache.git
cd cache
composer install
```

Some adapter tests need APCu, Memcache, Memcached, MongoDB, or Redis. Tests skip when their required extension or service is unavailable.

## Run the checks

Run the complete local check set before you open a pull request:

```bash
composer quality
```

You can also run each check on its own:

```bash
composer cs:check
composer phpstan
composer test
```

Run `composer cs:fix` to apply the Symfony coding standard with PHP-CS-Fixer. PHPStan runs at level 9. PHPUnit runs the unit and integration test suites.

## Add tests

Put package-specific tests in that package's `Tests` directory. Add backend-independent PSR behavior to [`cache/integration-tests`](https://github.com/php-cache/integration-tests).

Report project issues on the [GitHub issue tracker](https://github.com/php-cache/cache/issues).

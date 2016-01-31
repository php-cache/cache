# PSR-6 Integration tests 

To make sure your implementation of PSR-6 is correct you should use this test suite. 

### Usage

Install the dev-master version of this library.

```bash
composer require --dev cache/integration-tests:dev-master
```

Create a test that looks like this: 
```php
class PoolIntegrationTest extends CachePoolTest
{
    public function createCachePool()
    {
        return new CachePool();
    }
}
```

You could also test your tag implementation:
```php
class TagIntegrationTest extends TaggableCachePoolTest
{
    public function createCachePool()
    {
        return new CachePool();
    }
}
```
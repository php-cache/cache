# Hierarchical PSR-6 cache pool 
[![Build Status](https://travis-ci.org/php-cache/hierarchical-cache.svg?branch=master)](https://travis-ci.org/php-cache/hierarchical-cache) [![codecov.io](https://codecov.io/github/php-cache/hierarchical-cache/coverage.svg?branch=master)](https://codecov.io/github/php-cache/hierarchical-cache?branch=master)

This is a implementation for the PSR-6 for an hierarchical cache architecture. 

If you have a cache key like `|users|:uid|followers|:fid|likes` where `:uid` and `:fid` are arbitrary integers. You
 may flush all followers by flushing `|users|:uid|followers`.
 
```php
$user = 4711;
for ($i = 0; $i < 100; $i++) {
  $item = $pool->getItem(sprintf('|users|%d|followers|%d|likes', $user, $i));
  $item->set('Justin Bieber');
  $pool->save($item);
}

$pool->hasItem('|users|4711|followers|12|likes'); // True

$pool->deleteItem('|users|4711|followers');

$pool->hasItem('|users|4711|followers|12|likes'); // False
```

| Feature | Supported |
| ------- | --------- | 
| Flush everything | Yes 
| Expiration time | Yes
| Tagging | Yes

# Changelog

## Changes from 0.2 to 0.3

* The `AbstractCachePool` does not longer implement `TaggablePoolInterface`. However, the `CacheItem` does still implement `TaggableItemInterface`.
* `CacheItem::getKeyFromTaggedKey` has been removed
* The `CacheItem`'s second parameter is a callable that must return an array with 3 elements; [`hasValue`, `value`, `tags`]. 
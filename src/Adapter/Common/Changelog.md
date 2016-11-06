# Change Log

The change log describes what is "Added", "Removed", "Changed" or "Fixed" between each release. 

## UNRELEASED

## 0.3.3

### Fixed

* Bugfix when you fetch data from the cache storage that was saved as "non-tagging item" but fetch as a tagging item.

## 0.3.2

### Added

* Cache pools do implement `LoggerAwareInterface`

## 0.3.0

### Changed

* The `AbstractCachePool` does not longer implement `TaggablePoolInterface`. However, the `CacheItem` does still implement `TaggableItemInterface`.
* `CacheItem::getKeyFromTaggedKey` has been removed
* The `CacheItem`'s second parameter is a callable that must return an array with 3 elements; [`hasValue`, `value`, `tags`].
 
## 0.2.0
 
No changelog before this version

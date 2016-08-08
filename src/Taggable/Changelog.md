# Changelog

The change log describes what is "Added", "Removed", "Changed" or "Fixed" between each release. 

## UNRELEASED
## 0.4.3

### Fixed

* Do not lose the data when you start using the `TaggablePSR6PoolAdapter`

## 0.4.2

### Changed

* Updated version for integration tests
* Made `TaggablePSR6PoolAdapter::getTags` protected instead of private

## 0.4.1

### Fixed

* Saving an expired value should be the same as removing that value

## 0.4.0

This is a big BC break. The API is rewritten and how we store tags has changed. Each tag is a key to a list in the 
cache storage. The list contains keys to items that uses that tag. 

* The `TaggableItemInterface` is completely rewritten. It extends `CacheItemInterface` and has three methods: `getTags`, `setTags` and `addTag`.
* The `TaggablePoolInterface` is also rewritten. It has a new `clearTags` function. 
* The `TaggablePoolTrait` has new methods to manipulate the list of tags. 

## 0.3.1 

No changelog before this version

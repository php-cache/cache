# Changelog

## Changes from 0.3 to 0.4

This is a big BC break. The API is rewritten and how we store tags has changed. Each tag is a key to a list in the 
cache storage. The list contains keys to items that uses that tag. 

* The `TaggableItemInterface` is completely rewritten. It extends `CacheItemInterface` and has three methods: `getTags`, `setTags` and `addTag`.
* The `TaggablePoolInterface` is also rewritten. It has a new `clearTags` function. 
* The `TaggablePoolTrait` has new methods to manipulate the list of tags. 
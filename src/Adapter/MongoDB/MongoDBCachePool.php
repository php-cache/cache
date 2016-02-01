<?php

/*
 * This file is part of php-cache\mongodb-adapter package.
 *
 * (c) 2015-2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\MongoDB;

use Cache\Adapter\Common\AbstractCachePool;
use Cache\Adapter\Common\CacheItem;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Driver\Manager;
use Psr\Cache\CacheItemInterface;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class MongoDBCachePool extends AbstractCachePool
{
    /**
     * @type Collection
     */
    private $collection;

    /**
     * @param Collection $collection
     */
    public function __construct(Collection $collection)
    {
        $this->collection = $collection;
    }

    public static function createCollection(Manager $manager, $database, $collection)
    {
        $collection = new Collection($manager, $database, $collection);
        $collection->createIndex(['expireAt' => 1], ['expireAfterSeconds' => 0]);

        return $collection;
    }

    protected function fetchObjectFromCache($key)
    {
        $object = $this->collection->findOne(['_id' => $key]);

        if (!$object || !isset($object->data)) {
            return [false, null];
        }

        if (isset($object->expiresAt)) {
            if ($object->expiresAt < time()) {
                return [false, null];
            }
        }

        return [true, unserialize($object->data)];
    }

    protected function clearAllObjectsFromCache()
    {
        $this->collection->deleteMany([]);

        return true;
    }

    protected function clearOneObjectFromCache($key)
    {
        $this->collection->deleteOne(['_id' => $key]);

        return true;
    }

    protected function storeItemInCache($key, CacheItemInterface $item, $ttl)
    {
        $object = [
            '_id'  => $key,
            'data' => serialize($item->get()),
        ];

        if ($ttl) {
            $object['expiresAt'] = time() + $ttl;
        }

        $this->collection->updateOne(['_id' => $key], ['$set' => $object], ['upsert' => true]);

        return true;
    }
}

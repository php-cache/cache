<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\MongoDB;

use Cache\Adapter\Common\AbstractCachePool;
use Cache\Adapter\Common\JsonBinaryArmoring;
use Cache\Adapter\Common\PhpCacheItem;
use Cache\Adapter\Common\PhpUnserializer;
use Cache\Adapter\Common\TagSupportWithArray;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;
use MongoDB\Driver\Manager;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 * @author Magnus Nordlander
 */
class MongoDBCachePool extends AbstractCachePool
{
    use JsonBinaryArmoring;
    use TagSupportWithArray;

    private Collection $collection;

    public function __construct(Collection $collection)
    {
        $this->collection = $collection;
    }

    public static function createCollection(Manager $manager, string $database, string $collection): Collection
    {
        $collection = new Collection($manager, $database, $collection);
        $collection->createIndex(['expiresAt' => 1], ['expireAfterSeconds' => 0]);

        return $collection;
    }

    protected function fetchObjectFromCache(string $key): array
    {
        $document = $this->collection->findOne(['_id' => $key], ['typeMap' => ['root' => 'array']]);

        if (!\is_array($document) || !\array_key_exists('data', $document) || !\array_key_exists('tags', $document)) {
            return [false, null, [], null];
        }

        $expiresAt = $document['expiresAt'] ?? null;
        if ($expiresAt instanceof UTCDateTime) {
            $expiresAt = $expiresAt->toDateTime()->getTimestamp();
        }
        if (null !== $expiresAt && (!\is_int($expiresAt) || $expiresAt <= time())) {
            return [false, null, [], null];
        }

        if (!$this->thawValue($document['data'], $value) || !$this->thawValue($document['tags'], $tags)) {
            return [false, null, [], null];
        }

        if (!\is_array($tags)) {
            return [false, null, [], null];
        }

        $validTags = [];
        foreach ($tags as $tagVersion) {
            if (!\is_array($tagVersion) || !array_is_list($tagVersion) || 2 !== \count($tagVersion)) {
                return [false, null, [], null];
            }
            [$tag, $version] = $tagVersion;
            if (!\is_string($tag) || !\is_string($version)) {
                return [false, null, [], null];
            }

            $validTags[] = [$tag, $version];
        }

        $expirationTimestamp = $document['expirationTimestamp'] ?? null;
        if (null !== $expirationTimestamp && !\is_int($expirationTimestamp)) {
            return [false, null, [], null];
        }

        return [true, $value, $validTags, $expirationTimestamp];
    }

    protected function clearAllObjectsFromCache(): bool
    {
        $this->collection->deleteMany([]);

        return true;
    }

    protected function clearOneObjectFromCache(string $key): bool
    {
        $this->collection->deleteOne(['_id' => $key]);

        return true;
    }

    protected function storeItemInCache(PhpCacheItem $item, ?int $ttl): bool
    {
        $expirationTimestamp = $item->getExpirationTimestamp();
        $object = [
            '_id' => $item->getKey(),
            'data' => $this->freezeValue($item->get()),
            'tags' => $this->freezeValue($item->getTagVersions()),
            'expirationTimestamp' => $expirationTimestamp,
        ];

        $update = ['$set' => $object];
        if (null !== $ttl && $ttl > 0 && null !== $expirationTimestamp) {
            $update['$set']['expiresAt'] = new UTCDateTime($expirationTimestamp * 1000);
        } else {
            $update['$unset'] = ['expiresAt' => true];
        }

        $this->collection->updateOne(['_id' => $item->getKey()], $update, ['upsert' => true]);

        return true;
    }

    public function getDirectValue(string $name): mixed
    {
        $document = $this->collection->findOne(['_id' => $name], ['typeMap' => ['root' => 'array']]);
        if (!\is_array($document) || !\array_key_exists('data', $document)) {
            return null;
        }

        return $this->thawValue($document['data'], $value) ? $value : null;
    }

    public function setDirectValue(string $name, mixed $value): bool
    {
        $object = [
            '_id' => $name,
            'data' => $this->freezeValue($value),
        ];

        $this->collection->updateOne(['_id' => $name], ['$set' => $object], ['upsert' => true]);

        return true;
    }

    private function freezeValue(mixed $value): string
    {
        return static::jsonArmor(serialize($value));
    }

    private function thawValue(mixed $payload, mixed &$value): bool
    {
        if (!\is_string($payload)) {
            return false;
        }

        $serialized = static::jsonDeArmor($payload);
        if (!PhpUnserializer::unserialize($serialized, $value)) {
            return false;
        }

        return false !== $value || serialize(false) === $serialized;
    }
}

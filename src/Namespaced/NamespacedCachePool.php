<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Namespaced;

use Cache\Hierarchy\HierarchicalPoolInterface;
use Cache\Namespaced\Exception\CacheException;
use Cache\Namespaced\Exception\InvalidArgumentException;
use Cache\TagInterop\TaggableCacheItemInterface;
use Cache\TagInterop\TaggableCacheItemPoolInterface;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Prefix all the stored items with a namespace. Also make sure you can clear all items
 * in that namespace.
 *
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class NamespacedCachePool implements HierarchicalPoolInterface
{
    private const GENERATION_METADATA_TYPE = 'php-cache.namespaced-generation';

    private const GENERATION_METADATA_VERSION = 1;

    private const GENERATION_PROBE_LIMIT = 100;

    private const ENCODING_MARKER = '_x';

    private const HIERARCHY_MARKER = '_x7C_';

    private CacheItemPoolInterface $cachePool;

    private string $namespace;

    /** @var list<string> */
    private array $namespaceParts;

    private readonly object $owner;

    private readonly NamespacedTagMapper $tagMapper;

    private bool $usesHierarchy;

    /**
     * @return ($cachePool is TaggableCacheItemPoolInterface ? TaggableNamespacedCachePool : self)
     */
    public static function create(CacheItemPoolInterface $cachePool, string $namespace): self
    {
        if ($cachePool instanceof TaggableCacheItemPoolInterface) {
            return new TaggableNamespacedCachePool($cachePool, $namespace);
        }

        return new self($cachePool, $namespace);
    }

    public function __construct(CacheItemPoolInterface $cachePool, string $namespace)
    {
        if ('' === $namespace) {
            throw new InvalidArgumentException('Cache namespace cannot be an empty string.');
        }

        $namespace = $this->encodeNamespaceComponent($namespace);

        if ($cachePool instanceof self) {
            $this->cachePool = $cachePool->cachePool;
            $this->namespaceParts = [...$cachePool->namespaceParts, $namespace];
            $this->usesHierarchy = $cachePool->usesHierarchy;
        } else {
            $this->cachePool = $cachePool;
            $this->namespaceParts = [$namespace];
            $this->usesHierarchy = $cachePool instanceof HierarchicalPoolInterface;
        }

        $namespaceBoundary = str_repeat(HierarchicalPoolInterface::HIERARCHY_SEPARATOR, 2);
        $this->namespace = implode($namespaceBoundary, $this->namespaceParts);
        $this->owner = new \stdClass();
        $this->tagMapper = new NamespacedTagMapper($this->namespace);
    }

    /**
     * Add namespace prefix on the key.
     */
    private function prefixValue(string &$key): void
    {
        $key = $this->encodeKey($key);

        $this->prefixEncodedValue($key);
    }

    private function prefixEncodedValue(string &$key): void
    {
        if ($this->usesHierarchy) {
            $separator = HierarchicalPoolInterface::HIERARCHY_SEPARATOR;
            if ($this->isHierarchyKey($key)) {
                $key = self::HIERARCHY_MARKER.($separator === $key ? '' : $key);
            }
            $key = $separator.$this->namespace.$separator.$key;

            return;
        }

        $key = 'ns.i.'.sha1($this->getGenerationPath($key)."\0".$key);
    }

    private function getGenerationPath(string $key): string
    {
        $generations = [];
        foreach ($this->getGenerationPaths($key) as $path) {
            [$item, $generation] = $this->findGenerationMetadata($path);
            if (null === $generation) {
                $generation = bin2hex(random_bytes(16));
                if (!$this->cachePool->save($item->set($this->createGenerationMetadata($path, $generation)))) {
                    throw new CacheException('Could not persist namespace generation metadata.');
                }
            }
            $generations[] = $generation;
        }

        return $this->namespace."\0".implode("\0", $generations);
    }

    /**
     * @return list<string>
     */
    private function getGenerationPaths(string $key = ''): array
    {
        $path = '';
        $paths = [];
        foreach ($this->namespaceParts as $index => $namespace) {
            $path .= str_repeat(HierarchicalPoolInterface::HIERARCHY_SEPARATOR, 0 === $index ? 1 : 2).$namespace;
            $paths[] = $path;
        }

        if ($this->isHierarchyKey($key)) {
            $path .= HierarchicalPoolInterface::HIERARCHY_SEPARATOR.self::HIERARCHY_MARKER;
            $paths[] = $path;
            if (HierarchicalPoolInterface::HIERARCHY_SEPARATOR === $key) {
                return $paths;
            }

            foreach (\array_slice(explode(HierarchicalPoolInterface::HIERARCHY_SEPARATOR, $key), 1) as $component) {
                $path .= HierarchicalPoolInterface::HIERARCHY_SEPARATOR.$component;
                $paths[] = $path;
            }
        }

        return $paths;
    }

    private function getHierarchyGenerationPath(string $key): string
    {
        $path = HierarchicalPoolInterface::HIERARCHY_SEPARATOR.$this->namespace
            .HierarchicalPoolInterface::HIERARCHY_SEPARATOR.self::HIERARCHY_MARKER;
        if (HierarchicalPoolInterface::HIERARCHY_SEPARATOR === $key) {
            return $path;
        }

        foreach (\array_slice(explode(HierarchicalPoolInterface::HIERARCHY_SEPARATOR, $key), 1) as $component) {
            $path .= HierarchicalPoolInterface::HIERARCHY_SEPARATOR.$component;
        }

        return $path;
    }

    private function getGenerationKey(string $path, int $probe = 0): string
    {
        return 'ns.g.'.sha1(0 === $probe ? $path : $path."\0".$probe);
    }

    /**
     * @return array{CacheItemInterface, string|null}
     */
    private function findGenerationMetadata(string $path): array
    {
        $availableItem = null;
        for ($probe = 0; $probe < self::GENERATION_PROBE_LIMIT; ++$probe) {
            $item = $this->cachePool->getItem($this->getGenerationKey($path, $probe));
            if (!$item->isHit()) {
                $availableItem ??= $item;

                continue;
            }

            $generation = $this->readGenerationMetadata($item->get(), $path);
            if (null !== $generation) {
                return [$item, $generation];
            }
        }

        if (null !== $availableItem) {
            return [$availableItem, null];
        }

        throw new CacheException('Could not allocate namespace generation metadata without overwriting an existing cache item.');
    }

    /**
     * @return array{type: string, version: int, path: string, generation: string}
     */
    private function createGenerationMetadata(string $path, string $generation): array
    {
        return [
            'type' => self::GENERATION_METADATA_TYPE,
            'version' => self::GENERATION_METADATA_VERSION,
            'path' => $path,
            'generation' => $generation,
        ];
    }

    private function readGenerationMetadata(mixed $metadata, string $path): ?string
    {
        if (!\is_array($metadata)
            || self::GENERATION_METADATA_TYPE !== ($metadata['type'] ?? null)
            || self::GENERATION_METADATA_VERSION !== ($metadata['version'] ?? null)
            || $path !== ($metadata['path'] ?? null)
            || !\is_string($metadata['generation'] ?? null)
        ) {
            return null;
        }

        return $metadata['generation'];
    }

    private function encodeNamespaceComponent(string $component): string
    {
        $encoded = '';
        $length = \strlen($component);
        for ($index = 0; $index < $length; ++$index) {
            $byte = $component[$index];
            $ordinal = \ord($byte);
            $portable = ($ordinal >= 48 && $ordinal <= 57)
                || ($ordinal >= 65 && $ordinal <= 90)
                || ($ordinal >= 97 && $ordinal <= 122)
                || '_' === $byte
                || '.' === $byte;
            $startsMarker = '_' === $byte && 'x' === ($component[$index + 1] ?? null);

            $encoded .= $portable && !$startsMarker ? $byte : $this->encodeByte($byte);
        }

        return $encoded;
    }

    private function encodeKeyComponent(string $component): string
    {
        $encoded = '';
        $length = \strlen($component);
        for ($index = 0; $index < $length; ++$index) {
            $byte = $component[$index];
            $startsMarker = '_' === $byte && 'x' === ($component[$index + 1] ?? null);
            $encoded .= '|' !== $byte && '!' !== $byte && !$startsMarker ? $byte : $this->encodeByte($byte);
        }

        return $encoded;
    }

    private function encodeByte(string $byte): string
    {
        return self::ENCODING_MARKER.strtoupper(bin2hex($byte)).'_';
    }

    private function encodeKey(string $key): string
    {
        $this->validateKey($key);
        if (!$this->isHierarchyKey($key)) {
            return $this->encodeKeyComponent($key);
        }

        return implode(
            HierarchicalPoolInterface::HIERARCHY_SEPARATOR,
            array_map($this->encodeKeyComponent(...), explode(HierarchicalPoolInterface::HIERARCHY_SEPARATOR, $key))
        );
    }

    private function isHierarchyKey(string $key): bool
    {
        return HierarchicalPoolInterface::HIERARCHY_SEPARATOR === ($key[0] ?? null);
    }

    private function validateKey(string $key): void
    {
        if ('' === $key) {
            throw new InvalidArgumentException('Cache key cannot be an empty string');
        }

        if (preg_match('|[\{\}\(\)/\\\@\:]|', $key)) {
            throw new InvalidArgumentException(\sprintf('Invalid key: "%s". The key contains one or more characters reserved for future extension: {}()/\@:', $key));
        }
    }

    /**
     * @param array<array-key, mixed> $keys
     *
     * @return array<array-key, string>
     */
    private function prefixValues(array $keys): array
    {
        $prefixedKeys = [];
        foreach ($this->encodeValues($keys) as $index => $key) {
            $this->prefixEncodedValue($key);
            $prefixedKeys[$index] = $key;
        }

        return $prefixedKeys;
    }

    /**
     * @param array<array-key, mixed> $keys
     *
     * @return array<array-key, string>
     */
    private function encodeValues(array $keys): array
    {
        foreach ($keys as $index => $key) {
            if (!\is_string($key)) {
                throw new InvalidArgumentException(\sprintf('Cache key must be string, "%s" given', get_debug_type($key)));
            }

            $keys[$index] = $this->encodeKey($key);
        }

        return $keys;
    }

    public function getItem(string $key): CacheItemInterface
    {
        $originalKey = $key;
        $this->prefixValue($key);

        return $this->wrapItem($originalKey, $this->cachePool->getItem($key));
    }

    /**
     * @param array<array-key, string> $keys
     *
     * @return iterable<string, CacheItemInterface>
     */
    public function getItems(array $keys = []): iterable
    {
        $prefixedKeys = $this->prefixValues($keys);

        $originalKeys = [];
        foreach ($prefixedKeys as $index => $prefixedKey) {
            $originalKeys["\0".$prefixedKey] = $keys[$index];
        }

        return $this->wrapItems($prefixedKeys, $originalKeys);
    }

    /**
     * @param array<array-key, string> $prefixedKeys
     * @param array<string, string>    $originalKeys
     *
     * @return \Generator<string, CacheItemInterface>
     */
    private function wrapItems(array $prefixedKeys, array $originalKeys): \Generator
    {
        foreach ($this->cachePool->getItems($prefixedKeys) as $item) {
            $mappedKey = "\0".$item->getKey();
            if (!\array_key_exists($mappedKey, $originalKeys)) {
                continue;
            }

            $originalKey = $originalKeys[$mappedKey];
            yield $originalKey => $this->wrapItem($originalKey, $item);
        }
    }

    private function wrapItem(string $key, CacheItemInterface $item): NamespacedCacheItem
    {
        if ($item instanceof TaggableCacheItemInterface) {
            return new TaggableNamespacedCacheItem($key, $item, $this->owner, $this->tagMapper);
        }

        return new NamespacedCacheItem($key, $item, $this->owner);
    }

    protected function mapTag(string $tag): string
    {
        return $this->tagMapper->map($tag);
    }

    /**
     * @param array<array-key, string> $tags
     *
     * @return array<array-key, string>
     */
    protected function mapTags(array $tags): array
    {
        return $this->tagMapper->mapTags($tags);
    }

    public function hasItem(string $key): bool
    {
        $this->prefixValue($key);

        return $this->cachePool->hasItem($key);
    }

    public function clear(): bool
    {
        if ($this->usesHierarchy) {
            return $this->cachePool->deleteItem(HierarchicalPoolInterface::HIERARCHY_SEPARATOR.$this->namespace);
        }

        return $this->advanceGeneration(HierarchicalPoolInterface::HIERARCHY_SEPARATOR.$this->namespace);
    }

    public function deleteItem(string $key): bool
    {
        if (!$this->usesHierarchy) {
            $key = $this->encodeKey($key);
            if ($this->isHierarchyKey($key)) {
                return $this->advanceGeneration($this->getHierarchyGenerationPath($key));
            }

            $this->prefixEncodedValue($key);

            return $this->cachePool->deleteItem($key);
        }

        $this->prefixValue($key);

        return $this->cachePool->deleteItem($key);
    }

    public function deleteItems(array $keys): bool
    {
        if (!$this->usesHierarchy) {
            $encodedKeys = $this->encodeValues($keys);
            $backendKeys = [];
            $generationPaths = [];
            foreach ($encodedKeys as $key) {
                if ($this->isHierarchyKey($key)) {
                    $generationPath = $this->getHierarchyGenerationPath($key);
                    $generationPaths["\0".$generationPath] = $generationPath;

                    continue;
                }

                $this->prefixEncodedValue($key);
                $backendKeys[] = $key;
            }

            $deleted = true;
            foreach ($generationPaths as $path) {
                $deleted = $this->advanceGeneration($path) && $deleted;
            }

            return ([] === $backendKeys || $this->cachePool->deleteItems($backendKeys)) && $deleted;
        }

        $keys = $this->prefixValues($keys);

        return $this->cachePool->deleteItems($keys);
    }

    private function advanceGeneration(string $path): bool
    {
        [$item] = $this->findGenerationMetadata($path);
        $generation = bin2hex(random_bytes(16));

        return $this->cachePool->save($item->set($this->createGenerationMetadata($path, $generation)));
    }

    public function save(CacheItemInterface $item): bool
    {
        if (!$item instanceof NamespacedCacheItem || !$item->isOwnedBy($this->owner)) {
            throw new InvalidArgumentException('Cache items are not transferable between pools.');
        }

        return $this->cachePool->save($item->unwrap());
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        if (!$item instanceof NamespacedCacheItem || !$item->isOwnedBy($this->owner)) {
            throw new InvalidArgumentException('Cache items are not transferable between pools.');
        }

        return $this->cachePool->saveDeferred($item->unwrap());
    }

    public function commit(): bool
    {
        return $this->cachePool->commit();
    }
}

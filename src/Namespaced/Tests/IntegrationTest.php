<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Namespaced\Tests;

use Cache\Adapter\PHPArray\ArrayCachePool;
use Cache\Namespaced\NamespacedCachePool;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class IntegrationTest extends TestCase
{
    private ArrayCachePool $cache;

    protected function setUp(): void
    {
        $this->cache = new ArrayCachePool();
    }

    protected function tearDown(): void
    {
        $this->cache->clear();
    }

    public function testGetItem(): void
    {
        $namespace = 'ns';
        $nsPool = new NamespacedCachePool($this->cache, $namespace);

        $item = $nsPool->getItem('key');
        $this->assertSame('key', $item->getKey());
    }

    public function testGetItems(): void
    {
        $namespace = 'ns';
        $nsPool = new NamespacedCachePool($this->cache, $namespace);

        $items = iterator_to_array($nsPool->getItems(['key0', 'key1']));

        $this->assertTrue(isset($items['key0']));
        $this->assertSame('key0', $items['key0']->getKey());

        $this->assertTrue(isset($items['key1']));
        $this->assertSame('key1', $items['key1']->getKey());
    }

    public function testSave(): void
    {
        $namespace = 'ns';
        $nsPool = new NamespacedCachePool($this->cache, $namespace);

        $item = $nsPool->getItem('key');
        $item->set('foo');
        $nsPool->save($item);

        $this->assertTrue($nsPool->hasItem('key'));
        $this->assertFalse($this->cache->hasItem('key'));
    }

    public function testSaveDeferred(): void
    {
        $namespace = 'ns';
        $nsPool = new NamespacedCachePool($this->cache, $namespace);

        $item = $nsPool->getItem('key');
        $item->set('foo');
        $nsPool->saveDeferred($item);

        $this->assertTrue($nsPool->hasItem('key'));
        $this->assertFalse($this->cache->hasItem('key'));

        $nsPool->commit();
        $this->assertTrue($nsPool->hasItem('key'));
        $this->assertFalse($this->cache->hasItem('key'));
    }

    public function testNestedNamespaceClearDoesNotDeleteOuterItems(): void
    {
        $outerPool = new NamespacedCachePool($this->cache, 'outer');
        $innerPool = new NamespacedCachePool($outerPool, 'inner');

        $item = $innerPool->getItem('key');
        $item->set('value');
        $this->assertTrue($innerPool->save($item));

        $sibling = $outerPool->getItem('sibling');
        $sibling->set('value');
        $this->assertTrue($outerPool->save($sibling));

        $this->assertTrue($innerPool->clear());
        $this->assertFalse($innerPool->hasItem('key'));
        $this->assertTrue($outerPool->hasItem('sibling'));
    }

    public function testNestedNamespaceReadsEntriesWrittenBeforeVersionFour(): void
    {
        $this->assertTrue($this->cache->save($this->cache->getItem('|outer||inner|key')->set('legacy value')));

        $outerPool = new NamespacedCachePool($this->cache, 'outer');
        $innerPool = new NamespacedCachePool($outerPool, 'inner');

        $this->assertSame('legacy value', $innerPool->getItem('key')->get());
    }

    public function testNestedNamespaceDoesNotCollideWithOuterKey(): void
    {
        $outerPool = new NamespacedCachePool($this->cache, 'outer');
        $outerItem = $outerPool->getItem('inner|key');
        $outerItem->set('outer');
        $this->assertTrue($outerPool->save($outerItem));

        $innerPool = new NamespacedCachePool($outerPool, 'inner');
        $innerItem = $innerPool->getItem('key');
        $innerItem->set('inner');
        $this->assertTrue($innerPool->save($innerItem));

        $this->assertSame('outer', $outerPool->getItem('inner|key')->get());
        $this->assertSame('inner', $innerPool->getItem('key')->get());
    }

    public function testNestedNamespaceDoesNotCollideWithOuterHierarchyKey(): void
    {
        $outerPool = new NamespacedCachePool($this->cache, 'outer');
        $innerPool = new NamespacedCachePool($outerPool, 'inner');

        $this->assertTrue($innerPool->save($innerPool->getItem('key')->set('inner')));
        $this->assertTrue($outerPool->save($outerPool->getItem('|inner|key')->set('outer hierarchy')));
        $this->assertTrue($this->cache->hasItem('|outer|_x7C_|inner|key'));

        $this->assertSame('inner', $innerPool->getItem('key')->get());
        $this->assertSame('outer hierarchy', $outerPool->getItem('|inner|key')->get());

        $this->assertTrue($innerPool->clear());
        $this->assertFalse($innerPool->hasItem('key'));
        $this->assertSame('outer hierarchy', $outerPool->getItem('|inner|key')->get());
    }

    public function testNestedNamespaceDoesNotCollideWithSeparatorInNamespace(): void
    {
        $flatPool = new NamespacedCachePool($this->cache, 'outer|inner');
        $flatItem = $flatPool->getItem('key')->set('flat');
        $this->assertTrue($flatPool->save($flatItem));

        $outerPool = new NamespacedCachePool($this->cache, 'outer');
        $nestedPool = new NamespacedCachePool($outerPool, 'inner');
        $nestedItem = $nestedPool->getItem('key')->set('nested');
        $this->assertTrue($nestedPool->save($nestedItem));

        $this->assertSame('flat', $flatPool->getItem('key')->get());
        $this->assertSame('nested', $nestedPool->getItem('key')->get());
    }

    public function testReservedCharactersInNamespaceAreEncoded(): void
    {
        $pool = new NamespacedCachePool($this->cache, '{}()/\\@:%|!');

        $this->assertTrue($pool->save($pool->getItem('key')->set('value')));
        $this->assertTrue($this->cache->hasItem('|_x7B__x7D__x28__x29__x2F__x5C__x40__x3A__x25__x7C__x21_|key'));
        $this->assertSame('value', $pool->getItem('key')->get());
        $this->assertTrue($pool->clear());
        $this->assertFalse($pool->hasItem('key'));
    }

    public function testNamespaceEncodingUsesOnlyPortablePsrCharacters(): void
    {
        $pool = new NamespacedCachePool($this->cache, "billing-prod\u{00E9}");

        $this->assertTrue($pool->save($pool->getItem('key')->set('value')));
        $this->assertTrue($this->cache->hasItem('|billing_x2D_prod_xC3__xA9_|key'));
    }

    public function testLiteralEncodingMarkerDoesNotCollideWithEncodedNamespace(): void
    {
        $encoded = new NamespacedCachePool($this->cache, '%');
        $literal = new NamespacedCachePool($this->cache, '_x25_');

        $this->assertTrue($encoded->save($encoded->getItem('key')->set('encoded')));
        $this->assertTrue($literal->save($literal->getItem('key')->set('literal')));

        $this->assertSame('encoded', $encoded->getItem('key')->get());
        $this->assertSame('literal', $literal->getItem('key')->get());
    }

    public function testHierarchyControlCharactersRemainPartOfThePublicKey(): void
    {
        $pool = new NamespacedCachePool($this->cache, 'namespace');

        $values = [
            'key' => 'plain',
            '|key' => 'hierarchy separator',
            '%7Ckey' => 'literal percent encoding',
            '!key' => 'tag separator',
            '_x21_key' => 'literal encoding marker',
            'hyphen-key' => 'backend-supported punctuation',
            "cl\u{00E9}" => 'unicode',
        ];
        foreach ($values as $key => $value) {
            $this->assertTrue($pool->save($pool->getItem($key)->set($value)));
        }

        foreach ($values as $key => $value) {
            $this->assertSame($value, $pool->getItem($key)->get());
        }

        $this->assertTrue($this->cache->hasItem('|namespace|%7Ckey'));
        $this->assertTrue($this->cache->hasItem('|namespace|hyphen-key'));
        $this->assertTrue($this->cache->hasItem("|namespace|cl\u{00E9}"));
    }

    public function testNamespacesRemainIsolatedOnAGenericPsr6Pool(): void
    {
        $cache = new GenericCachePool(new ArrayCachePool());
        $first = new NamespacedCachePool($cache, 'first');
        $second = new NamespacedCachePool($cache, 'second');

        $this->assertTrue($first->save($first->getItem('key')->set('first value')));
        $this->assertTrue($second->save($second->getItem('key')->set('second value')));
        $this->assertSame('first value', $first->getItem('key')->get());
        $this->assertSame('second value', $second->getItem('key')->get());
        $this->assertTrue($first->clear());

        $this->assertFalse($first->hasItem('key'));
        $this->assertSame('second value', $second->getItem('key')->get());
    }

    public function testNestedNamespaceAndOuterHierarchyRemainIsolatedOnAGenericPool(): void
    {
        $cache = new GenericCachePool(new ArrayCachePool());
        $outer = new NamespacedCachePool($cache, 'outer');
        $nested = new NamespacedCachePool($outer, 'inner');

        $this->assertTrue($nested->save($nested->getItem('key')->set('nested')));
        $this->assertTrue($outer->save($outer->getItem('|inner|key')->set('outer hierarchy')));

        $this->assertTrue($nested->clear());
        $this->assertFalse($nested->hasItem('key'));
        $this->assertSame('outer hierarchy', $outer->getItem('|inner|key')->get());

        $this->assertTrue($nested->save($nested->getItem('key')->set('nested again')));
        $this->assertTrue($outer->deleteItem('|inner'));
        $this->assertFalse($outer->hasItem('|inner|key'));
        $this->assertSame('nested again', $nested->getItem('key')->get());
    }

    public function testEmptyNamespaceCannotClearAHierarchicalPoolRoot(): void
    {
        $this->assertTrue($this->cache->save($this->cache->getItem('|outside|child')->set('outside')));

        try {
            (new NamespacedCachePool($this->cache, ''))->clear();
            self::fail('An empty namespace must be rejected.');
        } catch (\Psr\Cache\InvalidArgumentException) {
        }

        $this->assertTrue($this->cache->hasItem('|outside|child'));
    }

    public function testEmptyNamespaceIsRejectedForAGenericPool(): void
    {
        $backend = new ArrayCachePool();
        $this->assertTrue($backend->save($backend->getItem('outside')->set('outside')));

        try {
            (new NamespacedCachePool(new GenericCachePool($backend), ''))->clear();
            self::fail('An empty namespace must be rejected.');
        } catch (\Psr\Cache\InvalidArgumentException) {
        }

        $this->assertTrue($backend->hasItem('outside'));
    }

    public function testHierarchyDeletionInvalidatesDescendantsOnAHierarchicalPool(): void
    {
        $this->assertHierarchyDeletion(new NamespacedCachePool($this->cache, 'namespace'), false);
    }

    public function testHierarchyDeletionInvalidatesDescendantsOnAGenericPool(): void
    {
        $cache = new GenericCachePool(new ArrayCachePool());

        $this->assertHierarchyDeletion(new NamespacedCachePool($cache, 'namespace'), true);
    }

    public function testEvictedGenerationMetadataDoesNotResurrectClearedValues(): void
    {
        $backend = new ArrayCachePool();
        $pool = new NamespacedCachePool(new GenericCachePool($backend), 'namespace');

        $this->assertTrue($pool->save($pool->getItem('key')->set('old value')));
        $this->assertTrue($pool->clear());
        $this->assertFalse($pool->hasItem('key'));
        $this->assertTrue($backend->deleteItem('ns.g.'.sha1('|namespace')));

        $this->assertFalse($pool->hasItem('key'));
    }

    public function testHierarchyRootDeletionInvalidatesDescendantsOnAHierarchicalPool(): void
    {
        $pool = new NamespacedCachePool($this->cache, 'namespace');
        $this->assertTrue($pool->save($pool->getItem('|parent|child')->set('value')));
        $this->assertTrue($pool->save($pool->getItem('plain')->set('plain value')));

        $this->assertTrue($pool->deleteItem('|'));
        $this->assertFalse($pool->hasItem('|parent|child'));
        $this->assertSame('plain value', $pool->getItem('plain')->get());
    }

    public function testHierarchyRootDeletionInvalidatesDescendantsOnAGenericPool(): void
    {
        $pool = new NamespacedCachePool(new GenericCachePool(new ArrayCachePool()), 'namespace');
        $this->assertTrue($pool->save($pool->getItem('|parent|child')->set('value')));
        $this->assertTrue($pool->save($pool->getItem('plain')->set('plain value')));

        $this->assertTrue($pool->deleteItems(['|']));
        $this->assertFalse($pool->hasItem('|parent|child'));
        $this->assertSame('plain value', $pool->getItem('plain')->get());
    }

    private function assertHierarchyDeletion(NamespacedCachePool $pool, bool $bulk): void
    {
        foreach ([
            '|parent' => 'parent',
            '|parent|child' => 'child',
            '|sibling|child' => 'sibling',
            'parent|child' => 'literal separator',
        ] as $key => $value) {
            $this->assertTrue($pool->save($pool->getItem($key)->set($value)));
        }

        $this->assertTrue($bulk ? $pool->deleteItems(['|parent']) : $pool->deleteItem('|parent'));
        $this->assertFalse($pool->hasItem('|parent'));
        $this->assertFalse($pool->hasItem('|parent|child'));
        $this->assertSame('sibling', $pool->getItem('|sibling|child')->get());
        $this->assertSame('literal separator', $pool->getItem('parent|child')->get());
    }
}

final class GenericCachePool implements CacheItemPoolInterface
{
    public function __construct(private readonly CacheItemPoolInterface $pool)
    {
    }

    public function getItem(string $key): CacheItemInterface
    {
        return $this->pool->getItem($key);
    }

    public function getItems(array $keys = []): iterable
    {
        return $this->pool->getItems($keys);
    }

    public function hasItem(string $key): bool
    {
        return $this->pool->hasItem($key);
    }

    public function clear(): bool
    {
        return $this->pool->clear();
    }

    public function deleteItem(string $key): bool
    {
        return $this->pool->deleteItem($key);
    }

    public function deleteItems(array $keys): bool
    {
        return $this->pool->deleteItems($keys);
    }

    public function save(CacheItemInterface $item): bool
    {
        return $this->pool->save($item);
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->pool->saveDeferred($item);
    }

    public function commit(): bool
    {
        return $this->pool->commit();
    }
}

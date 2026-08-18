<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Common\Tests;

use Cache\Adapter\Common\TagSupportWithArray;
use PHPUnit\Framework\TestCase;

final class TagSupportWithArrayTest extends TestCase
{
    public function testListOperationsNormalizeStoredValues(): void
    {
        $store = new InMemoryTagListStore();

        self::assertSame([], $store->readList('tag'));

        $store->values['tag'] = 'malformed';
        $store->append('tag', 'first');
        $store->append('tag', 'second');
        self::assertSame(['first', 'second'], $store->readList('tag'));

        $store->values['tag'] = ['first', 123, 'second', 'first'];
        self::assertSame(['first', 'second', 'first'], $store->readList('tag'));

        $store->remove('tag', 'first');
        self::assertSame(['second'], $store->readList('tag'));
        self::assertTrue($store->removeAll('tag'));
        self::assertSame([], $store->readList('tag'));
    }

    public function testListOperationsReportStorageFailures(): void
    {
        $store = new InMemoryTagListStore();
        $store->writeSucceeds = false;

        self::assertFalse($store->append('tag', 'key'));
        self::assertFalse($store->remove('tag', 'key'));
        self::assertFalse($store->removeAll('tag'));
    }
}

final class InMemoryTagListStore
{
    use TagSupportWithArray;

    /** @var array<string, mixed> */
    public array $values = [];

    public bool $writeSucceeds = true;

    public function getDirectValue(string $name): mixed
    {
        return $this->values[$name] ?? null;
    }

    public function setDirectValue(string $name, mixed $value): bool
    {
        if (!$this->writeSucceeds) {
            return false;
        }

        $this->values[$name] = $value;

        return true;
    }

    public function append(string $name, string $value): bool
    {
        return $this->appendListItem($name, $value);
    }

    /** @return list<string> */
    public function readList(string $name): array
    {
        return $this->getList($name);
    }

    public function removeAll(string $name): bool
    {
        return $this->removeList($name);
    }

    public function remove(string $name, string $key): bool
    {
        return $this->removeListItem($name, $key);
    }
}

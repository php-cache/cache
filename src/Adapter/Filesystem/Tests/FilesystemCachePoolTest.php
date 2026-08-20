<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Adapter\Filesystem\Tests;

use Cache\Adapter\Filesystem\FilesystemCachePool;
use League\Flysystem\DirectoryListing;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToDeleteFile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Cache\InvalidArgumentException;

/**
 * @author Tobias Nyholm <tobias.nyholm@gmail.com>
 */
class FilesystemCachePoolTest extends TestCase
{
    use CreatePoolTrait;

    #[DataProvider('dotKeyProvider')]
    public function testRoundTripsDotKeys(string $key)
    {
        $pool = $this->createCachePool();

        self::assertTrue($pool->save($pool->getItem($key)->set('value')));
        self::assertTrue($this->getFilesystem()->fileExists($this->storagePath($key)));
        self::assertSame('value', $pool->getItem($key)->get());
        self::assertTrue($pool->deleteItem($key));
        self::assertFalse($pool->hasItem($key));
    }

    #[DataProvider('portableStorageKeyProvider')]
    public function testUsesPortableShardedPathsByDefault(string $key)
    {
        $pool = $this->createCachePool();
        $path = $this->storagePath($key);

        self::assertTrue($pool->save($pool->getItem($key)->set('value')));
        self::assertTrue($this->getFilesystem()->fileExists($path));
        self::assertSame('value', $pool->getItem($key)->get());
    }

    public function testSubclassCanShardCacheFiles()
    {
        $pool = new ShardedFilesystemCachePool($this->getFilesystem());

        self::assertTrue($pool->save($pool->getItem('key')->set('value')));
        self::assertTrue($this->getFilesystem()->fileExists($this->storagePath('key', 'cache/keys')));
        self::assertSame('value', $pool->getItem('key')->get());
    }

    public function testClearRemovesShardedCacheFiles()
    {
        $pool = new ShardedFilesystemCachePool($this->getFilesystem());
        self::assertTrue($pool->save($pool->getItem('first')->set('first')));
        self::assertTrue($pool->save($pool->getItem('second')->set('second')));

        self::assertTrue($pool->clear());

        self::assertFalse($pool->hasItem('first'));
        self::assertFalse($pool->hasItem('second'));
    }

    public function testDeleteRemovesShardedCacheFile()
    {
        $pool = new ShardedFilesystemCachePool($this->getFilesystem());
        self::assertTrue($pool->save($pool->getItem('key')->set('value')));

        self::assertTrue($pool->deleteItem('key'));

        self::assertFalse($pool->hasItem('key'));
        self::assertFalse($this->getFilesystem()->fileExists($this->storagePath('key', 'cache/keys')));
    }

    public function testTagInvalidationRemovesShardedCacheFile()
    {
        $pool = new ShardedFilesystemCachePool($this->getFilesystem());
        $item = $pool->getItem('key')->set('value')->setTags(['group']);
        self::assertTrue($pool->save($item));

        self::assertTrue($pool->invalidateTag('group'));

        self::assertFalse($pool->hasItem('key'));
        self::assertFalse($this->getFilesystem()->fileExists($this->storagePath('key', 'cache/keys')));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function dotKeyProvider(): iterable
    {
        yield 'current directory' => ['.'];
        yield 'parent directory' => ['..'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function portableStorageKeyProvider(): iterable
    {
        yield 'Windows reserved name' => ['NUL'];
        yield 'uppercase key' => ['ABC'];
        yield 'lowercase key' => ['abc'];
        yield 'filesystem punctuation' => ['key-with-dash'];
    }

    private function storagePath(string $key, string $folder = 'cache'): string
    {
        $digest = hash('sha256', $key);

        return $folder.'/'.substr($digest, 0, 2).'/'.substr($digest, 2);
    }

    public function testCleanupOnExpire()
    {
        $pool = $this->createCachePool();

        $path = $this->storagePath('test_ttl_null');
        $this->getFilesystem()->write($path, serialize(['data', [], time()]));
        $this->assertTrue($this->getFilesystem()->fileExists($path));

        $item = $pool->getItem('test_ttl_null');
        $this->assertFalse($item->isHit());
        $this->assertFalse($this->getFilesystem()->fileExists($path));
    }

    public function testClearIgnoresFileRemovedByAnotherProcess()
    {
        $filesystem = $this->createMock(FilesystemOperator::class);
        $filesystem->method('listContents')->willReturn(new DirectoryListing(new \ArrayIterator([new FileAttributes('cache/key')])));
        $filesystem->method('fileExists')->with('cache/key')->willReturn(false);
        $filesystem->method('delete')->with('cache/key')->willThrowException(UnableToDeleteFile::atLocation('cache/key'));

        self::assertTrue((new FilesystemCachePool($filesystem))->clear());
    }

    public function testChangeFolder()
    {
        $pool = $this->createCachePool();
        $pool->setFolder('foobar');

        $pool->save($pool->getItem('test_path'));
        $this->assertTrue($this->getFilesystem()->fileExists($this->storagePath('test_path', 'foobar')));
    }

    public function testFilesystemAndFolderCanBeReconfigured()
    {
        $filesystem = $this->createMock(FilesystemOperator::class);
        $replacement = $this->createMock(FilesystemOperator::class);
        $replacement->expects(self::once())->method('createDirectory')->with('tenant/cache/items');

        $pool = new FilesystemCachePool($filesystem);

        self::assertSame($filesystem, $pool->getFilesystem());
        self::assertSame('cache', $pool->getFolder());

        $pool->setFolder('/tenant//./cache\\items/');
        $pool->setFilesystem($replacement);

        self::assertSame($replacement, $pool->getFilesystem());
        self::assertSame('tenant/cache/items', $pool->getFolder());
    }

    #[DataProvider('unsafeFolderProvider')]
    public function testRejectsUnsafeRootFolder(string $folder)
    {
        $this->expectException(InvalidArgumentException::class);

        new FilesystemCachePool($this->createMock(FilesystemOperator::class), $folder);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsafeFolderProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'root' => ['/'];
        yield 'repeated root' => ['///'];
        yield 'current directory' => ['.'];
        yield 'current directory with slash' => ['./'];
        yield 'parent directory' => ['..'];
        yield 'parent directory prefix' => ['../cache'];
        yield 'nested parent directory' => ['cache/..'];
        yield 'nested parent directory with slash' => ['cache/../'];
        yield 'nested parent directory with backslashes' => ['cache\\..'];
        yield 'nested parent directory with trailing backslash' => ['cache\\..\\'];
        yield 'backslash root' => ['\\'];
    }

    public function testCorruptedCacheFileHandledNicely()
    {
        $pool = $this->createCachePool();

        $path = $this->storagePath('corrupt');
        $this->getFilesystem()->write($path, 'corrupt data');

        $item = $pool->getItem('corrupt');
        $this->assertFalse($item->isHit());

        $this->getFilesystem()->delete($path);
    }

    public function testMalformedSerializedCacheFileIsMiss()
    {
        $pool = $this->createCachePool();

        $this->getFilesystem()->write($this->storagePath('malformed'), serialize([]));

        $this->assertFalse($pool->getItem('malformed')->isHit());
    }

    public function testThrowingUnserializeCacheFileIsMiss()
    {
        $pool = $this->createCachePool();

        $this->getFilesystem()->write($this->storagePath('throwing'), serialize([new ThrowingSerializedValue(), [], null]));

        self::assertFalse($pool->getItem('throwing')->isHit());
    }

    public function testIncompleteClassCacheFileIsMiss()
    {
        $pool = $this->createCachePool();
        $payload = str_replace('stdClass', 'GoneType', serialize([new \stdClass(), [], null]));

        $this->getFilesystem()->write($this->storagePath('incomplete'), $payload);

        self::assertFalse($pool->getItem('incomplete')->isHit());
    }

    public function testCorruptedTagListIsEmpty()
    {
        $pool = $this->createCachePool();
        $tag = 'corrupt_tag';
        $tagKey = 'tag!'.substr(hash('sha256', $tag), 0, 60);

        $this->getFilesystem()->write($this->storagePath($tagKey), 'corrupt data');

        $this->assertTrue($pool->invalidateTag($tag));
    }

    public function testThrowingUnserializeTagListIsEmpty()
    {
        $pool = $this->createCachePool();
        $tag = 'throwing';
        $tagKey = 'tag!'.substr(hash('sha256', $tag), 0, 60);

        $this->getFilesystem()->write($this->storagePath($tagKey), serialize([new ThrowingSerializedValue()]));

        self::assertTrue($pool->invalidateTag($tag));
    }

    public function testClearKeepsCacheDirectory()
    {
        $pool = $this->createCachePool();
        $pool->save($pool->getItem('before_clear')->set('value'));
        $inode = fileinode($this->getRootPath().'/cache');

        $this->assertTrue($pool->clear());
        clearstatcache(true, $this->getRootPath().'/cache');
        $this->assertSame($inode, fileinode($this->getRootPath().'/cache'));
        $this->assertFalse($pool->hasItem('before_clear'));
    }
}

final class ThrowingSerializedValue
{
    public function __serialize(): array
    {
        return [];
    }

    public function __unserialize(array $data)
    {
        throw new \RuntimeException();
    }
}

final class ShardedFilesystemCachePool extends FilesystemCachePool
{
    protected function getFilePath(string $key): string
    {
        $path = parent::getFilePath($key);

        return $this->getFolder().'/keys/'.substr($path, \strlen($this->getFolder()) + 1);
    }
}

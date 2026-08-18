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

    public function testInvalidKey()
    {
        $this->expectException(InvalidArgumentException::class);

        $pool = $this->createCachePool();

        $pool->getItem('test%string')->get();
    }

    public function testCleanupOnExpire()
    {
        $pool = $this->createCachePool();

        $this->getFilesystem()->write(
            'cache/test_ttl_null',
            serialize(['data', [], time()])
        );
        $this->assertTrue($this->getFilesystem()->fileExists('cache/test_ttl_null'));

        $item = $pool->getItem('test_ttl_null');
        $this->assertFalse($item->isHit());
        $this->assertFalse($this->getFilesystem()->fileExists('cache/test_ttl_null'));
    }

    public function testClearIgnoresFileRemovedByAnotherProcess(): void
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
        $this->assertTrue($this->getFilesystem()->fileExists('foobar/test_path'));
    }

    public function testFilesystemAndFolderCanBeReconfigured(): void
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
    public function testRejectsUnsafeRootFolder(string $folder): void
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

        $this->getFilesystem()->write('cache/corrupt', 'corrupt data');

        $item = $pool->getItem('corrupt');
        $this->assertFalse($item->isHit());

        $this->getFilesystem()->delete('cache/corrupt');
    }

    public function testMalformedSerializedCacheFileIsMiss(): void
    {
        $pool = $this->createCachePool();

        $this->getFilesystem()->write('cache/malformed', serialize([]));

        $this->assertFalse($pool->getItem('malformed')->isHit());
    }

    public function testCorruptedTagListIsEmpty(): void
    {
        $pool = $this->createCachePool();

        $this->getFilesystem()->write('cache/tag!corrupt_tag', 'corrupt data');

        $this->assertTrue($pool->invalidateTag('corrupt_tag'));
    }

    public function testClearKeepsCacheDirectory(): void
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

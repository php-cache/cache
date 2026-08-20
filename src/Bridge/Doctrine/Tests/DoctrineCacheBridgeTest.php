<?php

/*
 * This file is part of php-cache organization.
 *
 * (c) 2015 Aaron Scherer <aequasi@gmail.com>, Tobias Nyholm <tobias.nyholm@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Cache\Bridge\Doctrine\Tests;

use Cache\Adapter\Chain\CachePoolChain;
use Cache\Adapter\Filesystem\FilesystemCachePool;
use Cache\Adapter\PHPArray\ArrayCachePool;
use Cache\Bridge\Doctrine\DoctrineCacheBridge;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery as m;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException as CacheInvalidArgumentException;

/**
 * @author Aaron Scherer <aequasi@gmail.com>
 */
class DoctrineCacheBridgeTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private DoctrineCacheBridge $bridge;

    private MockInterface&CacheItemPoolInterface $mock;

    private MockInterface&CacheItemInterface $itemMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock = m::mock(CacheItemPoolInterface::class);

        $itemMock = m::mock(CacheItemInterface::class);
        $itemMock->shouldReceive('isHit')->andReturn(false);
        $this->mock->shouldReceive('getItem')->withArgs(['DoctrineNamespaceCacheKey[]'])->andReturn($itemMock);

        $this->bridge = new DoctrineCacheBridge($this->mock);

        $this->itemMock = m::mock(CacheItemInterface::class);
    }

    public function testConstructor()
    {
        $this->assertInstanceOf(DoctrineCacheBridge::class, $this->bridge);
    }

    public function testFetch()
    {
        $this->itemMock->shouldReceive('isHit')->times(1)->andReturn(true);
        $this->itemMock->shouldReceive('get')->times(1)->andReturn('some_value');

        $this->mock->shouldReceive('getItem')->withArgs(['[some_item][1]'])->andReturn($this->itemMock);

        $this->assertEquals('some_value', $this->bridge->fetch('some_item'));
    }

    public function testFetchMiss()
    {
        $this->itemMock->shouldReceive('isHit')->times(1)->andReturn(false);

        $this->mock->shouldReceive('getItem')->withArgs(['[no_item][1]'])->andReturn($this->itemMock);

        $this->assertFalse($this->bridge->fetch('no_item'));
    }

    public function testContains()
    {
        $this->mock->shouldReceive('hasItem')->withArgs(['[no_item][1]'])->andReturn(false);
        $this->mock->shouldReceive('hasItem')->withArgs(['[some_item][1]'])->andReturn(true);

        $this->assertFalse($this->bridge->contains('no_item'));
        $this->assertTrue($this->bridge->contains('some_item'));
    }

    public function testSave()
    {
        $this->itemMock->shouldReceive('set')->twice()->with('dummy_data');
        $this->itemMock->shouldReceive('expiresAfter')->once()->with(2);
        $this->mock->shouldReceive('getItem')->twice()->with('[some_item][1]')->andReturn($this->itemMock);
        $this->mock->shouldReceive('save')->twice()->with($this->itemMock)->andReturn(true);

        $this->assertTrue($this->bridge->save('some_item', 'dummy_data'));
        $this->assertTrue($this->bridge->save('some_item', 'dummy_data', 2));
    }

    public function testDelete()
    {
        $this->mock->shouldReceive('deleteItem')->once()->with('[some_item][1]')->andReturn(true);

        $this->assertTrue($this->bridge->delete('some_item'));
    }

    public function testGetCache()
    {
        $this->assertInstanceOf(CacheItemPoolInterface::class, $this->bridge->getCachePool());
    }

    public function testGetStats()
    {
        $this->assertNull($this->bridge->getStats());
    }

    public function testFilesystemPoolRoundTripsDoctrineKeys()
    {
        $this->assertDoctrineKeysRoundTripThroughFilesystem(false);
    }

    public function testChainWithFilesystemPoolRoundTripsDoctrineKeys()
    {
        $this->assertDoctrineKeysRoundTripThroughFilesystem(true);
    }

    private function assertDoctrineKeysRoundTripThroughFilesystem(bool $useChain)
    {
        $rootPath = sys_get_temp_dir().'/php-cache-doctrine-bridge-'.bin2hex(random_bytes(8));
        $filesystem = new Filesystem(new LocalFilesystemAdapter($rootPath));
        $filesystemPool = new FilesystemCachePool($filesystem);
        $pool = $filesystemPool;
        if ($useChain) {
            $arrayPool = new ArrayCachePool();
            $arrayPool->save($arrayPool->getItem('DoctrineNamespaceCacheKey[]')->set(1));
            $arrayPool->save($arrayPool->getItem('[metadata][1]')->set('legacy value'));
            $pool = new CachePoolChain([$arrayPool, $filesystemPool]);
        }
        $bridge = new DoctrineCacheBridge($pool);
        $keys = ['metadata', 'id-with-hyphen', str_repeat('a', 400)];

        try {
            if ($useChain) {
                self::assertTrue($bridge->contains('metadata'));
                self::assertSame('legacy value', $bridge->fetch('metadata'));
                self::assertTrue($bridge->delete('metadata'));
            }

            foreach ($keys as $key) {
                self::assertFalse($bridge->contains($key));
                self::assertTrue($bridge->save($key, $key));
                self::assertTrue($bridge->contains($key));
                self::assertSame($key, $bridge->fetch($key));
                self::assertTrue($bridge->delete($key));
                self::assertFalse($bridge->contains($key));
                self::assertTrue($bridge->save($key, $key));
            }

            self::assertTrue($bridge->deleteAll());
            foreach ($keys as $key) {
                self::assertFalse($bridge->contains($key));
            }
        } finally {
            $filesystem->deleteDirectory('cache');
            if (is_dir($rootPath)) {
                rmdir($rootPath);
            }
        }
    }

    public function testSaveDoesNotRetryAnUnrelatedInvalidArgumentException()
    {
        $exception = new class('save failed') extends \InvalidArgumentException implements CacheInvalidArgumentException {
        };
        $this->itemMock->shouldReceive('set')->once()->with('dummy_data');
        $this->mock->shouldReceive('getItem')->once()->with('[some_item][1]')->andReturn($this->itemMock);
        $this->mock->shouldReceive('save')->once()->with($this->itemMock)->andThrow($exception);

        $this->expectExceptionObject($exception);

        $this->bridge->save('some_item', 'dummy_data');
    }

    public function testRejectedLegacyKeyUsesSha256Fallback()
    {
        $exception = new class('invalid key') extends \InvalidArgumentException implements CacheInvalidArgumentException {
        };
        $doctrineKey = '[some{item][1]';
        $legacyKey = '[some_item][1]';
        $this->itemMock->shouldReceive('isHit')->once()->andReturn(false);
        $this->mock->shouldReceive('getItem')->once()->with($legacyKey)->andThrow($exception);
        $this->mock->shouldReceive('getItem')->once()->with(hash('sha256', $doctrineKey))->andReturn($this->itemMock);

        self::assertFalse($this->bridge->fetch('some{item'));
    }

    #[DataProvider('invalidKeys')]
    public function testInvalidKeys(string $key, string $normalizedKey)
    {
        $normalizedKey = \sprintf('[%s][1]', $normalizedKey);
        $this->itemMock->shouldReceive('isHit')->andReturn(false);
        $this->itemMock->shouldReceive('set');

        $this->mock->shouldReceive('getItem')->withArgs([$normalizedKey])->andReturn($this->itemMock);
        $this->mock->shouldReceive('hasItem')->withArgs([$normalizedKey])->andReturn(false);
        $this->mock->shouldReceive('deleteItem')->withArgs([$normalizedKey]);
        $this->mock->shouldReceive('save');

        self::assertFalse($this->bridge->contains($key));
        self::assertFalse($this->bridge->save($key, 'foo'));
        self::assertFalse($this->bridge->fetch($key));
        self::assertFalse($this->bridge->delete($key));
    }

    /**
     * @return list<array{string, string}>
     */
    public static function invalidKeys(): array
    {
        return [
            ['{str', '_str'],
            ['rand{', 'rand_'],
            ['rand{str', 'rand_str'],
            ['rand}str', 'rand_str'],
            ['rand(str', 'rand_str'],
            ['rand)str', 'rand_str'],
            ['rand/str', 'rand_str'],
            ['rand\\str', 'rand_str'],
            ['rand@str', 'rand_str'],
            ['rand:str', 'rand_str'],
        ];
    }
}

<?php

namespace Cache\Adapter\PHPArray\Tests;

use Cache\Adapter\PHPArray\SimpleArrayCache;

/**
 * @covers \Cache\Adapter\PHPArray\SimpleArrayCache
 */
class SimpleArrayCacheTest extends \PHPUnit_Framework_TestCase
{
    /** @var SimpleArrayCache */
    private $sut;
    
    protected function setUp()
    {
        $this->sut = new SimpleArrayCache();
    }
    
    public function testGetValue()
    {
        self::assertTrue($this->sut->set('Fiz', 'Buz'));
        
        self::assertSame('Buz', $this->sut->get('Fiz'));
    }
    
    public function testGetDefaultValue()
    {
        $default = new \Exception();
        
        self::assertSame($default, $this->sut->get('Fiz', $default));
    }
    
    public function testSetValue()
    {
        self::assertFalse($this->sut->has('Fiz'));
        
        self::assertTrue($this->sut->set('Fiz', 'Buz'));
        
        self::assertTrue($this->sut->has('Fiz'));
    }
    
    public function testDeleteValue()
    {
        self::assertTrue($this->sut->set('Fiz', 'Buz'));
        
        self::assertTrue($this->sut->has('Fiz'));
    
        self::assertTrue($this->sut->delete('Fiz'));
        
        self::assertFalse($this->sut->has('Fiz'));
    }
    
    public function testClear()
    {
        self::assertTrue($this->sut->set('Fiz', 'Buz'));
        self::assertTrue($this->sut->set('Foo', 'Bar'));
        
        self::assertTrue($this->sut->has('Fiz'));
        self::assertTrue($this->sut->has('Foo'));
    
        self::assertTrue($this->sut->clear());
        
        self::assertFalse($this->sut->has('Fiz'));
        self::assertFalse($this->sut->has('Foo'));
    }
    
    public function testGetMultiple()
    {
        self::assertTrue($this->sut->set('Fiz', 'Buz'));
        self::assertTrue($this->sut->set('Foo', 'Bar'));
        
        self::assertTrue($this->sut->has('Fiz'));
        self::assertTrue($this->sut->has('Foo'));
        
        $actual = $this->sut->getMultiple([
            'Fiz',
            'Foo',
        ]);
        
        $expected = [
            'Fiz' => 'Buz',
            'Foo' => 'Bar',
        ];
        
        self::assertArraySubset($expected, $actual);
    }
    
    public function testGetMultipleSubset()
    {
        self::assertTrue($this->sut->set('Foo', 'Bar'));
        
        self::assertFalse($this->sut->has('Fiz'));
        self::assertTrue($this->sut->has('Foo'));
        
        $actual = $this->sut->getMultiple([
            'Fiz',
            'Foo',
        ]);
        
        $expected = [
            'Fiz' => null,
            'Foo' => 'Bar',
        ];
        
        self::assertArraySubset($expected, $actual);
    }
    
    public function testSetMultiple()
    {
        self::assertFalse($this->sut->has('Fiz'));
        self::assertFalse($this->sut->has('Foo'));
        
        self::assertTrue($this->sut->setMultiple([
            'Fiz' => 'Buz',
            'Foo' => 'Bar',
        ]));
        
        self::assertTrue($this->sut->has('Fiz'));
        self::assertTrue($this->sut->has('Foo'));
    }
    
    public function testDeleteMultiple()
    {
        self::assertFalse($this->sut->has('Fiz'));
        self::assertFalse($this->sut->has('Foo'));
        
        self::assertTrue($this->sut->setMultiple([
            'Fiz' => 'Buz',
            'Foo' => 'Bar',
        ]));
        
        self::assertTrue($this->sut->has('Fiz'));
        self::assertTrue($this->sut->has('Foo'));
        
        self::assertTrue($this->sut->deleteMultiple([
            'Fiz',
            'Foo',
        ]));
        
        self::assertFalse($this->sut->has('Fiz'));
        self::assertFalse($this->sut->has('Foo'));
    }
}

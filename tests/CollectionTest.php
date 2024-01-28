<?php

declare(strict_types=1);

namespace tests;

use flight\util\Collection;
use PHPUnit\Framework\TestCase;

class CollectionTest extends TestCase
{
    private Collection $collection;

    protected function setUp(): void
    {
        $this->collection = new Collection(['a' => 1, 'b' => 2]);
    }

    // Get an item
    public function testGet()
    {
        $this->assertEquals(1, $this->collection->a);
    }

    // Set an item
    public function testSet()
    {
        $this->collection->c = 3;
        $this->assertEquals(3, $this->collection->c);
    }

    // Check if an item exists
    public function testExists()
    {
        $this->assertTrue(isset($this->collection->a));
    }

    // Unset an item
    public function testUnset()
    {
        unset($this->collection->a);
        $this->assertFalse(isset($this->collection->a));
    }

    // Count items
    public function testCount()
    {
        $this->assertEquals(2, count($this->collection));
    }

    // Iterate through items
    public function testIterate()
    {
        $items = [];
        foreach ($this->collection as $key => $value) {
            $items[$key] = $value;
        }

        $this->assertEquals(['a' => 1, 'b' => 2], $items);
    }

    public function testJsonSerialize()
    {
        $this->assertEquals(['a' => 1, 'b' => 2], $this->collection->jsonSerialize());
    }

    public function testOffsetSetWithNullOffset()
    {
        $this->collection->offsetSet(null, 3);
        $this->assertEquals(3, $this->collection->offsetGet(0));
    }

    public function testOffsetExists()
    {
        $this->collection->a = 1;
        $this->assertTrue($this->collection->offsetExists('a'));
    }

    public function testOffsetUnset()
    {
        $this->collection->a = 1;
        $this->assertTrue($this->collection->offsetExists('a'));
        $this->collection->offsetUnset('a');
        $this->assertFalse($this->collection->offsetExists('a'));
    }

    public function testKeys()
    {
        $this->collection->a = 1;
        $this->collection->b = 2;
        $this->assertEquals(['a', 'b'], $this->collection->keys());
    }

    public function testClear()
    {
        $this->collection->a = 1;
        $this->collection->b = 2;
        $this->assertEquals(['a', 'b'], $this->collection->keys());
        $this->collection->clear();
        $this->assertEquals(0, $this->collection->count());
    }
}

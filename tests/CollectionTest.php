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
    public function testGet(): void
    {
        $this->assertEquals(1, $this->collection->a);
    }

    // Set an item
    public function testSet(): void
    {
        $this->collection->c = 3;
        $this->assertEquals(3, $this->collection->c);
    }

    // Check if an item exists
    public function testExists(): void
    {
        $this->assertTrue(isset($this->collection->a));
    }

    // Unset an item
    public function testUnset(): void
    {
        unset($this->collection->a);
        $this->assertFalse(isset($this->collection->a));
    }

    // Count items
    public function testCount(): void
    {
        $this->assertEquals(2, count($this->collection));
    }

    // Iterate through items
    public function testIterate(): void
    {
        $items = [];
        foreach ($this->collection as $key => $value) {
            $items[$key] = $value;
        }

        $this->assertEquals(['a' => 1, 'b' => 2], $items);
    }

    public function testJsonSerialize(): void
    {
        $this->assertEquals(['a' => 1, 'b' => 2], $this->collection->jsonSerialize());
    }

    public function testOffsetSetWithNullOffset(): void
    {
        $this->collection->offsetSet(null, 3);
        $this->assertEquals(3, $this->collection->offsetGet(0));
    }

    public function testOffsetExists(): void
    {
        $this->collection->a = 1;
        $this->assertTrue($this->collection->offsetExists('a'));
    }

    public function testOffsetUnset(): void
    {
        $this->collection->a = 1;
        $this->assertTrue($this->collection->offsetExists('a'));
        $this->collection->offsetUnset('a');
        $this->assertFalse($this->collection->offsetExists('a'));
    }

    public function testKeys(): void
    {
        $this->collection->a = 1;
        $this->collection->b = 2;
        $this->assertEquals(['a', 'b'], $this->collection->keys());
    }

    public function testClear(): void
    {
        $this->collection->a = 1;
        $this->collection->b = 2;
        $this->assertEquals(['a', 'b'], $this->collection->keys());
        $this->collection->clear();
        $this->assertEquals(0, $this->collection->count());
    }
}

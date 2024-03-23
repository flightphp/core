<?php

declare(strict_types=1);

namespace flight\util;

use ArrayAccess;
use Countable;
use Iterator;
use JsonSerializable;

/**
 * The Collection class allows you to access a set of data
 * using both array and object notation.
 *
 * @license MIT, http://flightphp.com/license
 * @copyright Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 * @implements ArrayAccess<string, mixed>
 * @implements Iterator<string, mixed>
 */
class Collection implements ArrayAccess, Iterator, Countable, JsonSerializable
{
    /**
     * Collection data.
     *
     * @var array<string, mixed>
     */
    private array $data;

    /**
     * Constructor.
     *
     * @param array<string, mixed> $data Initial data
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Gets an item.
     *
     * @return mixed Value if `$key` exists in collection data, otherwise returns `NULL`
     */
    public function __get(string $key)
    {
        return $this->data[$key] ?? null;
    }

    /**
     * Set an item.
     *
     * @param mixed  $value Value
     */
    public function __set(string $key, $value): void
    {
        $this->data[$key] = $value;
    }

    /**
     * Checks if an item exists.
     */
    public function __isset(string $key): bool
    {
        return isset($this->data[$key]);
    }

    /**
     * Removes an item.
     */
    public function __unset(string $key): void
    {
        unset($this->data[$key]);
    }

    /**
     * Gets an item at the offset.
     *
     * @param string $offset Offset
     *
     * @return mixed Value
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->data[$offset] ?? null;
    }

    /**
     * Sets an item at the offset.
     *
     * @param ?string $offset Offset
     * @param mixed  $value  Value
     */
    #[\ReturnTypeWillChange]
    public function offsetSet($offset, $value): void
    {
        if (null === $offset) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }

    /**
     * Checks if an item exists at the offset.
     *
     * @param string $offset
     */
    public function offsetExists($offset): bool
    {
        return isset($this->data[$offset]);
    }

    /**
     * Removes an item at the offset.
     *
     * @param string $offset
     */
    public function offsetUnset($offset): void
    {
        unset($this->data[$offset]);
    }

    /**
     * Resets the collection.
     */
    public function rewind(): void
    {
        reset($this->data);
    }

    /**
     * Gets current collection item.
     *
     * @return mixed Value
     */
    #[\ReturnTypeWillChange]
    public function current()
    {
        return current($this->data);
    }

    /**
     * Gets current collection key.
     *
     * @return mixed Value
     */
    #[\ReturnTypeWillChange]
    public function key()
    {
        return key($this->data);
    }

    /**
     * Gets the next collection value.
     */
    #[\ReturnTypeWillChange]
    public function next(): void
    {
        next($this->data);
    }

    /**
     * Checks if the current collection key is valid.
     */
    public function valid(): bool
    {
        $key = key($this->data);

        return null !== $key;
    }

    /**
     * Gets the size of the collection.
     */
    public function count(): int
    {
        return \count($this->data);
    }

    /**
     * Gets the item keys.
     *
     * @return array<int, string> Collection keys
     */
    public function keys(): array
    {
        return array_keys($this->data);
    }

    /**
     * Gets the collection data.
     *
     * @return array<string, mixed> Collection data
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Sets the collection data.
     *
     * @param array<string, mixed> $data New collection data
     */
    public function setData(array $data): void
    {
        $this->data = $data;
    }

    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        return $this->data;
    }

    /**
     * Removes all items from the collection.
     */
    public function clear(): void
    {
        $this->data = [];
    }
}

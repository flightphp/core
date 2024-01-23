<?php

declare(strict_types=1);
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 * @license     MIT, http://flightphp.com/license
 */

namespace flight\util;

use ArrayAccess;
use Countable;
use Iterator;
use JsonSerializable;

/**
 * The Collection class allows you to access a set of data
 * using both array and object notation.
 * @implements ArrayAccess<string, mixed>
 * @implements Iterator<string, mixed>
 */
class Collection implements ArrayAccess, Iterator, Countable, JsonSerializable
{
    /**
     * Collection data.
     * @var array<string, mixed>
     */
    private $data;

    /**
     * Constructor.
     *
     * @param array<string, mixed> $data Initial data
     */
    public function __construct($data = [])
    {
        $this->data = $data;
    }

    /**
     * Gets an item.
     *
     * @param string $key Key
     *
     * @return mixed Value
     */
    public function __get($key)
    {
        return $this->data[$key] ?? null;
    }

    /**
     * Set an item.
     *
     * @param string $key   Key
     * @param mixed  $value Value
     */
    public function __set($key, $value)
    {
        $this->data[$key] = $value;
    }

    /**
     * Checks if an item exists.
     *
     * @param string $key
     *
     * @return bool Item status
     */
    public function __isset($key)
    {
        return isset($this->data[$key]);
    }

    /**
     * Removes an item.
     *
     * @param string $key Key
     */
    public function __unset($key)
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
    public function offsetSet($offset, $value)
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
     * @param string $offset Offset
     *
     * @return bool Item status
     */
    #[\ReturnTypeWillChange]
    public function offsetExists($offset)
    {
        return isset($this->data[$offset]);
    }

    /**
     * Removes an item at the offset.
     *
     * @param string $offset Offset
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        unset($this->data[$offset]);
    }

    /**
     * Resets the collection.
     */
    #[\ReturnTypeWillChange]
    public function rewind()
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
    public function next()
    {
        next($this->data);
    }

    /**
     * Checks if the current collection key is valid.
     *
     * @return bool Key status
     */
    #[\ReturnTypeWillChange]
    public function valid()
    {
        $key = key($this->data);

        return null !== $key;
    }

    /**
     * Gets the size of the collection.
     *
     * @return int Collection size
     */
    #[\ReturnTypeWillChange]
    public function count()
    {
        return \count($this->data);
    }

    /**
     * Gets the item keys.
     *
     * @return array<int, string> Collection keys
     */
    public function keys()
    {
        return array_keys($this->data);
    }

    /**
     * Gets the collection data.
     *
     * @return array<string, mixed> Collection data
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Sets the collection data.
     *
     * @param array<string, mixed> $data New collection data
     */
    public function setData($data)
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
    public function clear()
    {
        $this->data = [];
    }
}

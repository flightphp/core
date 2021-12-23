<?php

declare(strict_types=1);
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 * @license     MIT, http://flightphp.com/license
 */

namespace flight\core;

use Exception;
use InvalidArgumentException;

/**
 * The Dispatcher class is responsible for dispatching events. Events
 * are simply aliases for class methods or functions. The Dispatcher
 * allows you to hook other functions to an event that can modify the
 * input parameters and/or the output.
 */
class Dispatcher
{
    /**
     * Mapped events.
     */
    protected array $events = [];

    /**
     * Method filters.
     */
    protected array $filters = [];

    /**
     * Dispatches an event.
     *
     * @param string $name   Event name
     * @param array  $params Callback parameters
     *
     *@throws Exception
     *
     * @return mixed|null Output of callback
     */
    final public function run(string $name, array $params = [])
    {
        $output = '';

        // Run pre-filters
        if (!empty($this->filters[$name]['before'])) {
            $this->filter($this->filters[$name]['before'], $params, $output);
        }

        // Run requested method
        $output = self::execute($this->get($name), $params);

        // Run post-filters
        if (!empty($this->filters[$name]['after'])) {
            $this->filter($this->filters[$name]['after'], $params, $output);
        }

        return $output;
    }

    /**
     * Assigns a callback to an event.
     *
     * @param string   $name     Event name
     * @param callback $callback Callback function
     */
    final public function set(string $name, callable $callback): void
    {
        $this->events[$name] = $callback;
    }

    /**
     * Gets an assigned callback.
     *
     * @param string $name Event name
     *
     * @return callback $callback Callback function
     */
    final public function get(string $name): ?callable
    {
        return $this->events[$name] ?? null;
    }

    /**
     * Checks if an event has been set.
     *
     * @param string $name Event name
     *
     * @return bool Event status
     */
    final public function has(string $name): bool
    {
        return isset($this->events[$name]);
    }

    /**
     * Clears an event. If no name is given,
     * all events are removed.
     *
     * @param string|null $name Event name
     */
    final public function clear(?string $name = null): void
    {
        if (null !== $name) {
            unset($this->events[$name]);
            unset($this->filters[$name]);
        } else {
            $this->events = [];
            $this->filters = [];
        }
    }

    /**
     * Hooks a callback to an event.
     *
     * @param string   $name     Event name
     * @param string   $type     Filter type
     * @param callback $callback Callback function
     */
    final public function hook(string $name, string $type, callable $callback): void
    {
        $this->filters[$name][$type][] = $callback;
    }

    /**
     * Executes a chain of method filters.
     *
     * @param array $filters Chain of filters
     * @param array $params  Method parameters
     * @param mixed $output  Method output
     *
     * @throws Exception
     */
    final public function filter(array $filters, array &$params, &$output): void
    {
        $args = [&$params, &$output];
        foreach ($filters as $callback) {
            $continue = self::execute($callback, $args);
            if (false === $continue) {
                break;
            }
        }
    }

    /**
     * Executes a callback function.
     *
     * @param array|callback $callback Callback function
     * @param array          $params   Function parameters
     *
     *@throws Exception
     *
     * @return mixed Function results
     */
    public static function execute($callback, array &$params = [])
    {
        if (\is_callable($callback)) {
            return \is_array($callback) ?
                self::invokeMethod($callback, $params) :
                self::callFunction($callback, $params);
        }

        throw new InvalidArgumentException('Invalid callback specified.');
    }

    /**
     * Calls a function.
     *
     * @param callable|string $func   Name of function to call
     * @param array           $params Function parameters
     *
     * @return mixed Function results
     */
    public static function callFunction($func, array &$params = [])
    {
        return \call_user_func_array($func, $params);
    }

    /**
     * Invokes a method.
     *
     * @param mixed $func   Class method
     * @param array $params Class method parameters
     *
     * @return mixed Function results
     */
    public static function invokeMethod($func, array &$params = [])
    {
        return \call_user_func_array($func, $params);
    }

    /**
     * Resets the object to the initial state.
     */
    final public function reset(): void
    {
        $this->events = [];
        $this->filters = [];
    }
}

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
     * @var array<string, callable>
     */
    protected $events = [];

    /**
     * Method filters.
     * @var array<string, array<'before'|'after', array<int, callable>>>
     */
    protected $filters = [];

    /**
     * Dispatches an event.
     *
     * @param string $name   Event name
     * @param array<int, mixed>  $params Callback parameters
     *
     * @return mixed|null Output of callback
     * @throws Exception
     */
    public function run($name, $params = [])
    {
        $output = '';

        // Run pre-filters
        if (!empty($this->filters[$name]['before'])) {
            $this->filter($this->filters[$name]['before'], $params, $output);
        }

        // Run requested method
		$callback = $this->get($name);
        $output = $callback(...$params);

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
     * @param callable $callback Callback function
     */
    public function set($name, $callback): void
    {
        $this->events[$name] = $callback;
    }

    /**
     * Gets an assigned callback.
     *
     * @param string $name Event name
     *
     * @return ?callable $callback Callback function
     */
    public function get($name)
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
    public function has($name)
    {
        return isset($this->events[$name]);
    }

    /**
     * Clears an event. If no name is given, all events are removed.
     *
     * @param ?string $name Event name
     *
     * @return void
     */
    public function clear($name = null)
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
     * @param callable $callback Callback function
     *
     * @return void
     */
    public function hook($name, $type, $callback)
    {
        $this->filters[$name][$type][] = $callback;
    }

    /**
     * Executes a chain of method filters.
     *
     * @param array<int, callable> $filters Chain of filters
     * @param array<int, mixed> $params  Method parameters
     * @param mixed $output  Method output
     *
     * @return void
     * @throws Exception
     */
    public function filter($filters, &$params, &$output)
    {
        $args = [&$params, &$output];
        foreach ($filters as $callback) {
            $continue = $callback(...$args);
            if (false === $continue) {
                break;
            }
        }
    }

    /**
     * Executes a callback function.
     *
     * @param callable|array<class-string|object, string> $callback Callback function
     * @param array<int, mixed>          $params   Function parameters
     *
     * @return mixed Function results
     * @throws Exception
     */
    public static function execute($callback, &$params = [])
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
     * @param array<int, mixed>           $params Function parameters
     *
     * @return mixed Function results
     */
    public static function callFunction($func, &$params = [])
    {
		return call_user_func_array($func, $params);
    }

    /**
     * Invokes a method.
     *
     * @param mixed $func   Class method
     * @param array<int, mixed> $params Class method parameters
     *
     * @return mixed Function results
     */
    public static function invokeMethod($func, &$params = [])
    {
        [$class, $method] = $func;

        $instance = \is_object($class);

		return ($instance) ?
			$class->$method(...$params) :
			$class::$method();
    }

    /**
     * Resets the object to the initial state.
     *
     * @return void
     */
    public function reset()
    {
        $this->events = [];
        $this->filters = [];
    }
}

<?php

declare(strict_types=1);

namespace flight\core;

use Closure;
use Exception;
use InvalidArgumentException;
use ReflectionClass;
use TypeError;

/**
 * The Dispatcher class is responsible for dispatching events. Events
 * are simply aliases for class methods or functions. The Dispatcher
 * allows you to hook other functions to an event that can modify the
 * input parameters and/or the output.
 *
 * @license MIT, http://flightphp.com/license
 * @copyright Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 */
class Dispatcher
{
    public const FILTER_BEFORE = 'before';
    public const FILTER_AFTER = 'after';
    private const FILTER_TYPES = [self::FILTER_BEFORE, self::FILTER_AFTER];

    /** @var array<string, Closure(): (void|mixed)> Mapped events. */
    protected array $events = [];

    /**
     * Method filters.
     *
     * @var array<string, array<'before'|'after', array<int, Closure(array<int, mixed> &$params, mixed &$output): (void|false)>>>
     */
    protected array $filters = [];

    /**
     * Dispatches an event.
     *
     * @param string $name Event name
     * @param array<int, mixed> $params Callback parameters.
     *
     * @return mixed Output of callback
     * @throws Exception If event name isn't found or if event throws an `Exception`
     */
    public function run(string $name, array $params = [])
    {
        $this->runPreFilters($name, $params);
        $output = $this->runEvent($name, $params);

        return $this->runPostFilters($name, $output);
    }

    /**
     * @param array<int, mixed> &$params
     *
     * @return $this
     * @throws Exception
     */
    protected function runPreFilters(string $eventName, array &$params): self
    {
        $thereAreBeforeFilters = !empty($this->filters[$eventName][self::FILTER_BEFORE]);

        if ($thereAreBeforeFilters) {
            $this->filter($this->filters[$eventName][self::FILTER_BEFORE], $params, $output);
        }

        return $this;
    }

    /**
     * @param array<int, mixed> &$params
     *
     * @return void|mixed
     * @throws Exception
     */
    protected function runEvent(string $eventName, array &$params)
    {
        $requestedMethod = $this->get($eventName);

        if ($requestedMethod === null) {
            throw new Exception("Event '$eventName' isn't found.");
        }

        return $requestedMethod(...$params);
    }

    /**
     * @param mixed &$output
     *
     * @return mixed
     * @throws Exception
     */
    protected function runPostFilters(string $eventName, &$output)
    {
        static $params = [];

        $thereAreAfterFilters = !empty($this->filters[$eventName][self::FILTER_AFTER]);

        if ($thereAreAfterFilters) {
            $this->filter($this->filters[$eventName][self::FILTER_AFTER], $params, $output);
        }

        return $output;
    }

    /**
     * Assigns a callback to an event.
     *
     * @param string $name Event name
     * @param Closure(): (void|mixed) $callback Callback function
     *
     * @return $this
     */
    public function set(string $name, callable $callback): self
    {
        $this->events[$name] = $callback;

        return $this;
    }

    /**
     * Gets an assigned callback.
     *
     * @param string $name Event name
     *
     * @return null|(Closure(): (void|mixed)) $callback Callback function
     */
    public function get(string $name): ?callable
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
    public function has(string $name): bool
    {
        return isset($this->events[$name]);
    }

    /**
     * Clears an event. If no name is given, all events will be removed.
     *
     * @param ?string $name Event name
     */
    public function clear(?string $name = null): void
    {
        if ($name !== null) {
            unset($this->events[$name]);
            unset($this->filters[$name]);

            return;
        }

        $this->events = [];
        $this->filters = [];
    }

    /**
     * Hooks a callback to an event.
     *
     * @param string $name Event name
     * @param 'before'|'after' $type Filter type
     * @param Closure(array<int, mixed> &$params, string &$output): (void|false) $callback
     *
     * @return $this
     */
    public function hook(string $name, string $type, callable $callback): self
    {
        if (!in_array($type, self::FILTER_TYPES, true)) {
            $noticeMessage = "Invalid filter type '$type', use " . join('|', self::FILTER_TYPES);

            trigger_error($noticeMessage, E_USER_NOTICE);
        }

        $this->filters[$name][$type][] = $callback;

        return $this;
    }

    /**
     * Executes a chain of method filters.
     *
     * @param array<int, Closure(array<int, mixed> &$params, mixed &$output): (void|false)> $filters
     * Chain of filters-
     * @param array<int, mixed> $params Method parameters
     * @param mixed $output Method output
     *
     * @throws Exception If an event throws an `Exception` or if `$filters` contains an invalid filter.
     */
    public static function filter(array $filters, array &$params, &$output): void
    {
        foreach ($filters as $key => $callback) {
            if (!is_callable($callback)) {
                throw new InvalidArgumentException("Invalid callable \$filters[$key].");
            }

            $continue = $callback($params, $output);

            if ($continue === false) {
                break;
            }
        }
    }

    /**
     * Executes a callback function.
     *
     * @param callable-string|(Closure(): mixed)|array{class-string|object, string} $callback
     * Callback function
     * @param array<int, mixed> $params Function parameters
     *
     * @return mixed Function results
     * @throws Exception If `$callback` also throws an `Exception`.
     */
    public static function execute($callback, array &$params = [])
    {
        $isInvalidFunctionName = (
            is_string($callback)
            && !function_exists($callback)
        );

        if ($isInvalidFunctionName) {
            throw new InvalidArgumentException('Invalid callback specified.');
        }

        if (is_array($callback)) {
            return self::invokeMethod($callback, $params);
        }

        return self::callFunction($callback, $params);
    }

    /**
     * Calls a function.
     *
     * @param callable $func Name of function to call
     * @param array<int, mixed> &$params Function parameters
     *
     * @return mixed Function results
     */
    public static function callFunction(callable $func, array &$params = [])
    {
        return call_user_func_array($func, $params);
    }

    /**
     * Invokes a method.
     *
     * @param array{class-string|object, string} $func Class method
     * @param array<int, mixed> &$params Class method parameters
     *
     * @return mixed Function results
     * @throws TypeError For unexistent class name.
     */
    public static function invokeMethod(array $func, array &$params = [])
    {
        [$class, $method] = $func;

        if (is_string($class) && class_exists($class)) {
            $constructor = (new ReflectionClass($class))->getConstructor();
            $constructorParamsNumber = 0;

            if ($constructor !== null) {
                $constructorParamsNumber = count($constructor->getParameters());
            }

            if ($constructorParamsNumber > 0) {
                $exceptionMessage = "Method '$class::$method' cannot be called statically. ";
                $exceptionMessage .= sprintf(
                    "$class::__construct require $constructorParamsNumber parameter%s",
                    $constructorParamsNumber > 1 ? 's' : ''
                );

                throw new InvalidArgumentException($exceptionMessage, E_ERROR);
            }

            $class = new $class();
        }

        return call_user_func_array([$class, $method], $params);
    }

    /**
     * Resets the object to the initial state.
     *
     * @return $this
     */
    public function reset(): self
    {
        $this->events = [];
        $this->filters = [];

        return $this;
    }
}

<?php

declare(strict_types=1);

namespace flight\core;

use Closure;
use Exception;
use flight\Engine;
use InvalidArgumentException;
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

    /** @var mixed $containerException Exception message if thrown by setting the container as a callable method */
    protected $containerException = null;

    /** @var ?Engine $engine Engine instance */
    protected ?Engine $engine = null;

    /** @var array<string, Closure(): (void|mixed)> Mapped events. */
    protected array $events = [];

    /**
     * Method filters.
     *
     * @var array<string, array<'before'|'after', array<int, Closure(array<int, mixed> &$params, mixed &$output): (void|false)>>>
     */
    protected array $filters = [];

    /**
     * This is a container for the dependency injection.
     *
     * @var callable|object|null
     */
    protected $containerHandler = null;

    /**
     * Sets the dependency injection container handler.
     *
     * @param callable|object $containerHandler Dependency injection container
     *
     * @return void
     */
    public function setContainerHandler($containerHandler): void
    {
        $this->containerHandler = $containerHandler;
    }

    public function setEngine(Engine $engine): void
    {
        $this->engine = $engine;
    }

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
            throw new Exception("Event '{$eventName}' isn't found.");
        }

        return $this->execute($requestedMethod, $params);
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
    public function filter(array $filters, array &$params, &$output): void
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
    public function execute($callback, array &$params = [])
    {
        if (is_string($callback) === true && (strpos($callback, '->') !== false || strpos($callback, '::') !== false)) {
            $callback = $this->parseStringClassAndMethod($callback);
        }

        return $this->invokeCallable($callback, $params);
    }

    /**
     * Parses a string into a class and method.
     *
     * @param string $classAndMethod Class and method
     *
     * @return array{class-string|object, string} Class and method
     */
    public function parseStringClassAndMethod(string $classAndMethod): array
    {
        $class_parts = explode('->', $classAndMethod);
        if (count($class_parts) === 1) {
            $class_parts = explode('::', $class_parts[0]);
        }

        $class = $class_parts[0];
        $method = $class_parts[1];

        return [ $class, $method ];
    }

    /**
     * Calls a function.
     *
     * @param callable $func Name of function to call
     * @param array<int, mixed> &$params Function parameters
     *
     * @return mixed Function results
     * @deprecated 3.7.0 Use invokeCallable instead
     */
    public function callFunction(callable $func, array &$params = [])
    {
        return $this->invokeCallable($func, $params);
    }

    /**
     * Invokes a method.
     *
     * @param array{class-string|object, string} $func Class method
     * @param array<int, mixed> &$params Class method parameters
     *
     * @return mixed Function results
     * @throws TypeError For nonexistent class name.
     * @deprecated 3.7.0 Use invokeCallable instead
     */
    public function invokeMethod(array $func, array &$params = [])
    {
        return $this->invokeCallable($func, $params);
    }

    /**
     * Invokes a callable (anonymous function or Class->method).
     *
     * @param array{class-string|object, string}|Callable $func Class method
     * @param array<int, mixed> &$params Class method parameters
     *
     * @return mixed Function results
     * @throws TypeError For nonexistent class name.
     * @throws InvalidArgumentException If the constructor requires parameters
     * @version 3.7.0
     */
    public function invokeCallable($func, array &$params = [])
    {
        // If this is a directly callable function, call it
        if (is_array($func) === false) {
            $this->verifyValidFunction($func);
            return call_user_func_array($func, $params);
        }

        [$class, $method] = $func;
        $resolvedClass = null;

        // Only execute the container handler if it's not a Flight class
        if (
            $this->containerHandler !== null &&
            (
                (
                    is_object($class) === true &&
                    strpos(get_class($class), 'flight\\') === false
                ) ||
                is_string($class) === true
            )
        ) {
            $containerHandler = $this->containerHandler;
            $resolvedClass = $this->resolveContainerClass($containerHandler, $class, $params);
            if ($resolvedClass !== null) {
                $class = $resolvedClass;
            }
        }

        $this->verifyValidClassCallable($class, $method, $resolvedClass);

        // Class is a string, and method exists, create the object by hand and inject only the Engine
        if (is_string($class) === true) {
            $class = new $class($this->engine);
        }

        return call_user_func_array([ $class, $method ], $params);
    }

    /**
     * Handles invalid callback types.
     *
     * @param callable-string|(Closure(): mixed)|array{class-string|object, string} $callback
     * Callback function
     *
     * @throws InvalidArgumentException If `$callback` is an invalid type
     */
    protected function verifyValidFunction($callback): void
    {
        $isInvalidFunctionName = (
            is_string($callback)
            && !function_exists($callback)
        );

        if ($isInvalidFunctionName) {
            throw new InvalidArgumentException('Invalid callback specified.');
        }
    }


    /**
     * Verifies if the provided class and method are valid callable.
     *
     * @param string|object $class The class name.
     * @param string $method The method name.
     * @param object|null $resolvedClass The resolved class.
     *
     * @throws Exception If the class or method is not found.
     *
     * @return void
     */
    protected function verifyValidClassCallable($class, $method, $resolvedClass): void
    {
        $final_exception = null;

        // Final check to make sure it's actually a class and a method, or throw an error
        if (is_object($class) === false && class_exists($class) === false) {
            $final_exception = new Exception("Class '$class' not found. Is it being correctly autoloaded with Flight::path()?");

        // If this tried to resolve a class in a container and failed somehow, throw the exception
        } elseif (isset($resolvedClass) === false && $this->containerException !== null) {
            $final_exception = $this->containerException;

        // Class is there, but no method
        } elseif (is_object($class) === true && method_exists($class, $method) === false) {
            $final_exception = new Exception("Class found, but method '" . get_class($class) . "::$method' not found.");
        }

        if ($final_exception !== null) {
            $this->fixOutputBuffering();
            throw $final_exception;
        }
    }

    /**
     * Resolves the container class.
     *
     * @param callable|object $container_handler Dependency injection container
     * @param class-string $class Class name
     * @param array<int, mixed> &$params Class constructor parameters
     *
     * @return object Class object
     */
    protected function resolveContainerClass($container_handler, $class, array &$params)
    {
        $class_object = null;

        // PSR-11
        if (
            is_object($container_handler) === true &&
            method_exists($container_handler, 'has') === true &&
            $container_handler->has($class)
        ) {
            $class_object = call_user_func([$container_handler, 'get'], $class);

        // Just a callable where you configure the behavior (Dice, PHP-DI, etc.)
        } elseif (is_callable($container_handler) === true) {
            // This is to catch all the error that could be thrown by whatever container you are using
            try {
                $class_object = call_user_func($container_handler, $class, $params);
            } catch (Exception $e) {
                // could not resolve a class for some reason
                $class_object = null;

                // If the container throws an exception, we need to catch it
                // and store it somewhere. If we just let it throw itself, it
                // doesn't properly close the output buffers and can cause other
                // issues.
                // This is thrown in the verifyValidClassCallable method
                $this->containerException = $e;
            }
        }

        return $class_object;
    }

    /**
     * Because this could throw an exception in the middle of an output buffer,
     *
     * @return void
     */
    protected function fixOutputBuffering(): void
    {
        // Cause PHPUnit has 1 level of output buffering by default
        if (ob_get_level() > (getenv('PHPUNIT_TEST') ? 1 : 0)) {
            ob_end_clean();
        }
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

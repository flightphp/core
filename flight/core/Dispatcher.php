<?php

declare(strict_types=1);

namespace flight\core;

use Exception;
use flight\Engine;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use ReflectionFunction;
use Throwable;
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

    /** Exception message if thrown by setting the container as a callable method. */
    protected ?Throwable $containerException = null;

    /** @var ?Engine $engine Engine instance. */
    protected ?Engine $engine = null;

    /** @var array<string, callable(): (void|mixed)> Mapped events. */
    protected array $events = [];

    /**
     * Method filters.
     *
     * @var array<string, array<'before'|'after', array<int, callable(array<int, mixed> &$params, mixed &$output): (void|false)>>>
     */
    protected array $filters = [];

    /**
     * This is a container for the dependency injection.
     *
     * @var null|ContainerInterface|(callable(string $classString, array<int, mixed> $params): (null|object))
     */
    protected $containerHandler = null;

    /**
     * Sets the dependency injection container handler.
     *
     * @param ContainerInterface|(callable(class-string<T> $classString, array<int, mixed> $params): ?T) $containerHandler
     * Dependency injection container.
     *
     * @template T of object
     *
     * @throws InvalidArgumentException If $containerHandler is not a `callable` or instance of `Psr\Container\ContainerInterface`.
     */
    public function setContainerHandler($containerHandler): void
    {
        $containerInterfaceNS = '\Psr\Container\ContainerInterface';

        if (
            is_a($containerHandler, $containerInterfaceNS)
            || is_callable($containerHandler)
        ) {
            $this->containerHandler = $containerHandler;

            return;
        }

        throw new InvalidArgumentException(
            "\$containerHandler must be of type callable or instance $containerInterfaceNS"
        );
    }

    public function setEngine(Engine $engine): void
    {
        $this->engine = $engine;
    }

    /**
     * Dispatches an event.
     *
     * @param string $name Event name.
     * @param array<int, mixed> $params Callback parameters.
     *
     * @return mixed Output of callback
     * @throws Exception If event name isn't found or if event throws an `Exception`.
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
     * @param string $name Event name.
     * @param callable(): (void|mixed) $callback Callback function.
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
     * @param string $name Event name.
     *
     * @return null|(callable(): (void|mixed)) $callback Callback function.
     */
    public function get(string $name): ?callable
    {
        return $this->events[$name] ?? null;
    }

    /**
     * Checks if an event has been set.
     *
     * @param string $name Event name.
     *
     * @return bool If event exists or doesn't exists.
     */
    public function has(string $name): bool
    {
        return isset($this->events[$name]);
    }

    /**
     * Clears an event. If no name is given, all events will be removed.
     *
     * @param ?string $name Event name.
     */
    public function clear(?string $name = null): void
    {
        if ($name !== null) {
            unset($this->events[$name]);
            unset($this->filters[$name]);

            return;
        }

        $this->reset();
    }

    /**
     * Hooks a callback to an event.
     *
     * @param string $name Event name
     * @param 'before'|'after' $type Filter type.
     * @param callable(array<int, mixed> &$params, mixed &$output): (void|false)|callable(mixed &$output): (void|false) $callback
     *
     * @return $this
     */
    public function hook(string $name, string $type, callable $callback): self
    {
        static $filterTypes = [self::FILTER_BEFORE, self::FILTER_AFTER];

        if (!in_array($type, $filterTypes, true)) {
            $noticeMessage = "Invalid filter type '$type', use " . join('|', $filterTypes);

            trigger_error($noticeMessage, E_USER_NOTICE);
        }

        if ($type === self::FILTER_AFTER) {
            $callbackInfo = new ReflectionFunction($callback);
            $parametersNumber = $callbackInfo->getNumberOfParameters();

            if ($parametersNumber === 1) {
                /** @disregard &$params in after filters are deprecated. */
                $callback = fn(array &$params, &$output) => $callback($output);
            }
        }

        $this->filters[$name][$type][] = $callback;

        return $this;
    }

    /**
     * Executes a chain of method filters.
     *
     * @param array<int, callable(array<int, mixed> &$params, mixed &$output): (void|false)> $filters
     * Chain of filters.
     * @param array<int, mixed> $params Method parameters.
     * @param mixed $output Method output.
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
     * @param callable-string|(callable(): mixed)|array{class-string|object, string} $callback
     * Callback function.
     * @param array<int, mixed> $params Function parameters.
     *
     * @return mixed Function results.
     * @throws Exception If `$callback` also throws an `Exception`.
     */
    public function execute($callback, array &$params = [])
    {
        if (
            is_string($callback) === true
            && (strpos($callback, '->') !== false || strpos($callback, '::') !== false)
        ) {
            $callback = $this->parseStringClassAndMethod($callback);
        }

        return $this->invokeCallable($callback, $params);
    }

    /**
     * Parses a string into a class and method.
     *
     * @param string $classAndMethod Class and method
     *
     * @return array{0: class-string|object, 1: string} Class and method
     */
    public function parseStringClassAndMethod(string $classAndMethod): array
    {
        $classParts = explode('->', $classAndMethod);

        if (count($classParts) === 1) {
            $classParts = explode('::', $classParts[0]);
        }

        return $classParts;
    }

    /**
     * Calls a function.
     *
     * @param callable $func Name of function to call.
     * @param array<int, mixed> &$params Function parameters.
     *
     * @return mixed Function results.
     * @deprecated 3.7.0 Use invokeCallable instead
     */
    public function callFunction(callable $func, array &$params = [])
    {
        return $this->invokeCallable($func, $params);
    }

    /**
     * Invokes a method.
     *
     * @param array{0: class-string|object, 1: string} $func Class method.
     * @param array<int, mixed> &$params Class method parameters.
     *
     * @return mixed Function results.
     * @throws TypeError For nonexistent class name.
     * @deprecated 3.7.0 Use invokeCallable instead.
     */
    public function invokeMethod(array $func, array &$params = [])
    {
        return $this->invokeCallable($func, $params);
    }

    /**
     * Invokes a callable (anonymous function or Class->method).
     *
     * @param array{0: class-string|object, 1: string}|callable $func Class method.
     * @param array<int, mixed> &$params Class method parameters.
     *
     * @return mixed Function results.
     * @throws TypeError For nonexistent class name.
     * @throws InvalidArgumentException If the constructor requires parameters.
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

        $mustUseTheContainer = $this->mustUseContainer($class);

        if ($mustUseTheContainer === true) {
            $resolvedClass = $this->resolveContainerClass($class, $params);

            if ($resolvedClass) {
                $class = $resolvedClass;
            }
        }

        $this->verifyValidClassCallable($class, $method, $resolvedClass ?? null);

        // Class is a string, and method exists, create the object by hand and inject only the Engine
        if (is_string($class)) {
            $class = new $class($this->engine);
        }

        return call_user_func_array([$class, $method], $params);
    }

    /**
     * Handles invalid callback types.
     *
     * @param callable-string|(callable(): mixed)|array{0: class-string|object, 1: string} $callback
     * Callback function.
     *
     * @throws InvalidArgumentException If `$callback` is an invalid type.
     */
    protected function verifyValidFunction($callback): void
    {
        if (is_string($callback) && !function_exists($callback)) {
            throw new InvalidArgumentException('Invalid callback specified.');
        }
    }


    /**
     * Verifies if the provided class and method are valid callable.
     *
     * @param class-string|object $class The class name.
     * @param string $method The method name.
     * @param object|null $resolvedClass The resolved class.
     *
     * @throws Exception If the class or method is not found.
     */
    protected function verifyValidClassCallable($class, $method, $resolvedClass): void
    {
        $exception = null;

        // Final check to make sure it's actually a class and a method, or throw an error
        if (is_object($class) === false && class_exists($class) === false) {
            $exception = new Exception("Class '$class' not found. Is it being correctly autoloaded with Flight::path()?");

            // If this tried to resolve a class in a container and failed somehow, throw the exception
        } elseif (!$resolvedClass && $this->containerException !== null) {
            $exception = $this->containerException;

            // Class is there, but no method
        } elseif (is_object($class) === true && method_exists($class, $method) === false) {
            $classNamespace = get_class($class);
            $exception = new Exception("Class found, but method '$classNamespace::$method' not found.");
        }

        if ($exception !== null) {
            $this->fixOutputBuffering();

            throw $exception;
        }
    }

    /**
     * Resolves the container class.
     *
     * @param class-string $class Class name.
     * @param array<int, mixed> &$params Class constructor parameters.
     *
     * @return ?object Class object.
     */
    public function resolveContainerClass(string $class, array &$params)
    {
        // PSR-11
        if (is_a($this->containerHandler, '\Psr\Container\ContainerInterface')) {
            try {
                return $this->containerHandler->get($class);
            } catch (Throwable $exception) {
                return null;
            }
        }

        // Just a callable where you configure the behavior (Dice, PHP-DI, etc.)
        if (is_callable($this->containerHandler)) {
            /* This is to catch all the error that could be thrown by whatever
            container you are using */
            try {
                return ($this->containerHandler)($class, $params);

                // could not resolve a class for some reason
            } catch (Exception $exception) {
                // If the container throws an exception, we need to catch it
                // and store it somewhere. If we just let it throw itself, it
                // doesn't properly close the output buffers and can cause other
                // issues.
                // This is thrown in the verifyValidClassCallable method.
                $this->containerException = $exception;
            }
        }

        return null;
    }

    /**
     * Checks to see if a container should be used or not.
     *
     * @param string|object $class the class to verify
     *
     * @return boolean
     */
    public function mustUseContainer($class): bool
    {
        return $this->containerHandler !== null && (
            (is_object($class) === true && strpos(get_class($class), 'flight\\') === false)
            || is_string($class)
        );
    }

    /** Because this could throw an exception in the middle of an output buffer, */
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

<?php

declare(strict_types=1);

namespace flight\core;

use Exception;
use flight\Engine;
use InvalidArgumentException;
use OutOfBoundsException;
use Psr\Container\ContainerInterface as Container;
use ReflectionFunction;
use Throwable;
use TypeError;

/**
 * Responsible for dispatching named callables.
 *
 * The Dispatcher allows you to add filters to a named callable that can modify
 * the named callable `input` and/or `output`.
 *
 * - The `input` is the arguments passed to the named callable.
 * - The `output` is the return value of the named callable.
 *
 * @license MIT, http://flightphp.com/license
 * @copyright Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 */
class Dispatcher
{
    public const FILTER_BEFORE = 'before';
    public const FILTER_AFTER = 'after';
    private const CALLABLE_STRING_OPERATORS = ['->', '::'];

    protected ?Throwable $containerException = null;
    protected ?Engine $engine = null;

    /**
     * @deprecated Don't use this property directly, use `set()`, `get()` and `has()` instead.
     * @var array<string, callable>
     */
    protected array $events = [];

    /** @var array<string, FilteredCallable> */
    private array $namedFilteredCallables = [];

    /**
     * @deprecated Don't use this property, use `hook()` instead.
     * @var array<string, array{
     *   before?: (callable(mixed[] &$params): (void|never|false))[],
     *   after?: (callable(mixed &$output): (void|never|false))[],
     * }>
     */
    protected array $filters = [];

    /** @var null|Container|(callable(class-string<object> $classString, mixed[] $params): ?object) */
    protected $containerHandler = null;

    /**
     * @param Container|(callable(class-string<object> $classString, mixed[] $params): ?object) $containerHandler
     * @throws InvalidArgumentException
     * If $containerHandler is not a `callable` or instance of `Psr\Container\ContainerInterface`.
     */
    public function setContainerHandler($containerHandler): void
    {
        if (!$containerHandler instanceof Container && !is_callable($containerHandler)) {
            $message = "\$containerHandler must be of type callable or instance \\" . Container::class;

            throw new InvalidArgumentException($message);
        }

        $this->containerHandler = $containerHandler;
    }

    public function setEngine(Engine $engine): void
    {
        $this->engine = $engine;
    }

    /**
     * Runs a named callable and its filters.
     *
     * @param string $name Callable name.
     * @param mixed[] $params Callable input.
     * @return void|never|mixed Callable output.
     * @throws Throwable If the callable or its filters throw an `Throwable`.
     * @throws OutOfBoundsException If callable name is not found.
     */
    public function run(string $name, array $params = [])
    {
        if (get_called_class() !== self::class) {
            /* If dispatcher was extended, use the possibly overridden methods
            for pre/post filters and event execution. */
            $this->runPreFilters($name, $params);
        $output = $this->runEvent($name, $params);

        return $this->runPostFilters($name, $output);
    }

        // Executes the FilteredCallable, responsible of running its filters.
        $filteredCallable = $this->get($name);

        if (!$filteredCallable) {
            throw new OutOfBoundsException("Event '$name' isn't found.");
        }

        return $filteredCallable(...$params);
    }

    /**
     * @deprecated Don't override this method.
     * @param string $eventName Callable name.
     * @param mixed[] &$params Callable input.
     * @throws Throwable If any of the callable filters throw an `Throwable`.
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
     * @deprecated Don't override this method.
     * @param string $eventName Callable name.
     * @param mixed[] &$params Callable input.
     * @return void|never|mixed
     * @throws Throwable If the callable or its filters throw an `Throwable`.
     * @throws OutOfBoundsException If callable name is not found.
     */
    protected function runEvent(string $eventName, array &$params)
    {
        $requestedMethod = $this->get($eventName);

        if ($requestedMethod === null) {
            throw new OutOfBoundsException("Event '$eventName' isn't found.");
        }

        return $this->execute($requestedMethod, $params);
    }

    /**
     * @deprecated Don't override this method.
     * @template Output of mixed
     * @param Output &$output Callable output.
     * @return Output Callable output.
     * @throws Throwable If any of the callable filters throw an `Throwable`.
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
     * Assigns a name to a callable.
     *
     * @param string $name Callable name.
     * @param callable $callback Callable.
     */
    public function set(string $name, callable $callback): self
    {
        $this->events[$name] = $callback;
        $this->namedFilteredCallables[$name] = new FilteredCallable($callback);

        return $this;
    }

    /**
     * Returns a callable by its name.
     *
     * @param string $name Callable name.
     * @return ?FilteredCallable
     */
    public function get(string $name): ?callable
    {
        return $this->namedFilteredCallables[$name] ?? $this->events[$name] ?? null;
    }

    /**
     * Checks if a callable exists by its name.
     *
     * @param string $name Callable name.
     */
    public function has(string $name): bool
    {
        return $this->get($name) !== null;
    }

    /**
     * Clears a callable and its filters by its name.
     *
     * If no name is provided, clears all callables names and theirs filters.
     *
     * @param ?string $name Callable name.
     */
    public function clear(?string $name = null): void
    {
        if ($name !== null) {
            unset($this->events[$name]);
            unset($this->filters[$name]);
            unset($this->namedFilteredCallables[$name]);

            return;
        }

        $this->reset();
    }

    /**
     * Adds a filter to a callable.
     *
     * @param string $name Callable name.
     * @param 'before'|'after' $type Filter type.
     * @param callable(mixed[] &$params): (void|never|false)|callable(mixed &$output): (void|never|false) $callback
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

        $filteredCallable = $this->get($name);

        if ($filteredCallable) {
            if ($type === self::FILTER_BEFORE) {
                $filteredCallable->pushBeforeFilter($callback);
            }

            if ($type === self::FILTER_AFTER) {
                $filteredCallable->pushAfterFilter($callback);
            }
        }

        return $this;
    }

    /**
     * Executes a list of callable filters.
     *
     * @deprecated This method will be removed.
     * @param (callable(mixed[] &$params, mixed &$output): (void|never|false))[] $filters Callable filters.
     * @param mixed[] $params Callable input.
     * @param mixed $output Callable output.
     * @throws Throwable If any of the callable filters throw an `Throwable`.
     * @throws InvalidArgumentException If any of the callable filters is not a `callable`.
     */
    public function filter(array $filters, array &$params, &$output): void
    {
        foreach ($filters as $key => $filter) {
            if (!is_callable($filter)) {
                throw new InvalidArgumentException("Invalid callable \$filters[$key].");
            }

            $continue = $filter($params, $output);

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
        if (is_string($callback) === true && (strpos($callback, '->') !== false || strpos($callback, '::') !== false)) {
            $callback = $this->parseStringClassAndMethod($callback);
        }

        return $this->invokeCallable($callback, $params);
    }

    /**
     * Parses a string with an unloaded class and method into an array.
     *
     * @deprecated Use `execute()` instead.
     * @param string $classAndMethod An string with an unloaded class and method,
     * like `ClassName::method` or `ClassName->method`.
     * @return array{class-string<object>, string}
     * @throws InvalidArgumentException If the string is not in a valid format.
     */
    public function parseStringClassAndMethod(string $classAndMethod): array
    {
        foreach (self::CALLABLE_STRING_OPERATORS as $operator) {
            $classAndMethod = explode($operator, $classAndMethod);

            if (count($classAndMethod) === 2) {
                return [$classAndMethod[0], $classAndMethod[1]];
            }

            [$classAndMethod] = $classAndMethod;
        }

        $message = "Invalid string format '$classAndMethod', use 'ClassName::method' or 'ClassName->method'.";

        throw new InvalidArgumentException($message);
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
            $exception = new Exception(
                "Class '$class' not found. Is it being correctly autoloaded with Flight::path()?"
            );

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

    /** Resets the dispatcher state by clearing all events, filters, and named filtered callables. */
    public function reset(): self
    {
        $this->events = [];
        $this->filters = [];
        $this->namedFilteredCallables = [];

        return $this;
    }
}

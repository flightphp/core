<?php

declare(strict_types=1);

namespace flight\core;

use flight\Engine;
use InvalidArgumentException;
use OutOfBoundsException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface as Container;
use ReflectionFunction;
use Throwable;

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
     * @param mixed[] $params Callable input.
     * @return void|never|mixed
     * @throws Throwable If the callable or its filters throw an `Throwable`.
     * @throws OutOfBoundsException If callable name is not found.
     */
    protected function runEvent(string $eventName, array $params)
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
     * Executes a callable.
     *
     * @param callable|array{class-string<object>|object, string}|string $callback Callable.
     * @param mixed[] $params Callable input.
     * @return mixed Callable output.
     * @throws Throwable If the callable throws an `Throwable`.
     */
    public function execute($callback, array $params = [])
    {
        $container = $this->containerHandler;

        $this->verifyValidFunction($callback);

        if (is_string($callback)) {
            $callback = $this->parseStringClassAndMethod($callback);
        }

        if (is_callable($callback) && !is_array($callback)) {
            return $callback(...$params);
        }

        [$class, $method] = $callback;
        $object = null;

        if (is_object($class)) {
            return $class->$method(...$params);
        }

        if ($this->mustUseContainer($class)) {
            $object = $this->resolveContainerClass($class, $params);

            if (is_object($object)) {
                $class = $object;
            }
        }

        $this->verifyValidClassCallable($class, $method, $object);

        // Class is a string, and method exists, create the object by hand and inject only the Engine
        if (is_string($class)) {
            $class = new $class($this->engine);
        }

        return call_user_func_array([$class, $method], $params);
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
     * Executes a callable.
     *
     * @deprecated Use execute instead.
     * @param callable $func Callable.
     * @param mixed[] $params Callable input.
     * @return mixed Callable output.
     * @throws Throwable If the callable throws an `Throwable`.
     */
    public function callFunction(callable $func, array $params = [])
    {
        return $this->execute($func, $params);
    }

    /**
     * Executes a callable.
     *
     * @deprecated Use execute instead.
     * @param array{class-string<object>|object, string} $func Callable.
     * @param mixed[] $params Callable input.
     * @return mixed Callable output.
     * @throws Throwable If the callable throws an `Throwable`.
     */
    public function invokeMethod(array $func, array $params = [])
    {
        return $this->execute($func, $params);
    }

    /**
     * Executes a callable.
     *
     * @deprecated Use execute instead.
     * @param callable|array{class-string<object>|object, string}|string $func Callable.
     * @param mixed[] $params Callable input.
     * @return mixed Callable output.
     * @throws Throwable If the callable throws an `Throwable`.
     */
    public function invokeCallable($func, array $params = [])
    {
        return $this->execute($func, $params);
    }

    /**
     * Verifies if the provided function is valid callable.
     *
     * @deprecated This method will be removed.
     * @param callable|array{class-string<object>|object, string}|string $callback Callable.
     * @throws InvalidArgumentException If the function is not valid callable.
     */
    protected function verifyValidFunction($callback): void
    {
        /*
            ✔️ function () {}
            ✔️ Closure
            ✔️ Object that implements __invoke
            ✔️ 'existingFunction'
            ✔️ 'ExistingClass::existingAccessibleStaticMethod'
            ✔️ ['ExistingClass', 'existingAccessibleStaticMethod']
            ✔️ [$object, 'existingAccessibleMethod']
            ✔️ [$object, 'existingAccessibleStaticMethod']
        */
        if (is_callable($callback)) {
            return;
        }

        /*
            ✔️ ['UnloadedClass', 'method']
            ✔️ ['UnloadedClass', 'staticMethod']
        */
        if (
            is_array($callback)
            && count($callback) === 2
            && is_string($callback[0])
            && is_string($callback[1])
        ) {
            return;
        }

        /*
            ✔️ 'UnloadedClass::method'
            ✔️ 'UnloadedClass->method'
        */
        if (is_string($callback)) {
            foreach (self::CALLABLE_STRING_OPERATORS as $operator) {
                $callback = explode($operator, $callback);

                if (count($callback) === 2) {
                    return;
                }

                [$callback] = $callback;
            }
        }

        throw new InvalidArgumentException('Invalid callback specified.');
    }


    /**
     * @deprecated This method will be removed.
     * @template T of object
     * @param class-string<T>|T $class The class name or object.
     * @param ?T $resolvedClass A class instance.
     * @return void|never
     * @throws InvalidArgumentException If the class or method is not found.
     * @throws Throwable If the container throws an exception.
     */
    protected function verifyValidClassCallable($class, string $method, ?object $resolvedClass): void
    {
        $exception = null;

        // Final check to make sure it's actually a class and a method, or throw an error
        if (!is_object($class) && !class_exists($class)) {
            $message = "Class '$class' not found. Is it being correctly autoloaded with Flight::path()?";
            $exception = new InvalidArgumentException($message);
        } elseif ($this->containerException) {
            $exception = $this->containerException;
        } elseif (is_object($class) && !method_exists($class, $method)) {
            $fqcn = get_class($class);
            $exception = new InvalidArgumentException("Class found, but method '$fqcn::$method' not found.");
        }

        if ($exception) {
            $this->fixOutputBuffering();

            throw $exception;
        }
    }

    /**
     * Resolves a class from the container.
     *
     * @deprecated This method will be removed.
     * @template T of object
     * @param class-string<T> $class The class name.
     * @param mixed[] $params Class constructor arguments.
     * @return ?T The resolved class instance, or null if not found.
     */
    public function resolveContainerClass(string $class, array $params): ?object
    {
        $container = $this->containerHandler;

        if ($container instanceof Container) {
            try {
                return $container->get($class);
            } catch (ContainerExceptionInterface $exception) {
                $this->containerException = $exception;

                return null;
            }
        }

        if (is_callable($container)) {
            try {
                return $container($class, $params);
            } catch (Throwable $throwable) {
                // If the container throws an exception, we need to catch it
                // and store it somewhere. If we just let it throw itself, it
                // doesn't properly close the output buffers and can cause other
                // issues.
                // This is thrown in the verifyValidClassCallable method.
                $this->containerException = $throwable;
            }
        }

        return null;
    }

    /**
     * Checks if the class must be resolved by the container.
     *
     * @deprecated This method will be removed.
     * @param class-string<object>|object $class Class name or object.
     */
    public function mustUseContainer($class): bool
    {
        $container = $this->containerHandler;

        if (is_object($class)) {
            $class = get_class($class);
        }

        if ($container instanceof Container && $container->has($class)) {
            return true;
        }

        return is_callable($container);
    }

    /** Fixes output buffering issues when an exception is thrown. */
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

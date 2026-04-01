<?php

declare(strict_types=1);

namespace flight\core;

use Throwable;

/**
 * The Loader class is responsible for loading objects. It maintains a list of
 * reusable class instances and can generate a new class instances with custom
 * initialization parameters.
 * @license MIT, https://docs.flightphp.com/license/
 * @copyright Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 */
class Loader
{
    /** @var array<string, array{class-string|callable(): object, array<int, mixed>, ?callable(object): void}> */
    protected array $classes = [];

    /** @var array<string, object> */
    protected array $instances = [];

    /**
     * @template T of object
     * @param class-string<T>|callable(): T $class Class name or function to instantiate class
     * @param array<int, mixed> $params Class initialization parameters
     * @param ?callable(T): void $callback $callback Function to call after object instantiation
     */
    public function register(
        string $alias,
        $class,
        array $params = [],
        ?callable $callback = null
    ): void {
        $this->classes[$alias] = [$class, $params, $callback];
        unset($this->instances[$alias]);
    }

    public function unregister(string $alias): void
    {
        unset($this->classes[$alias]);
    }

    /**
     * @throws Throwable
     * @return ?object Class instance
     */
    public function load(string $alias, bool $shared = true): ?object
    {
        if (!key_exists($alias, $this->classes)) {
            return null;
        }

        [$class, $params, $callback] = $this->classes[$alias];
        $instanceExists = key_exists($alias, $this->instances);

        $obj = $shared && $instanceExists
            ? $this->instances[$alias] ?? null
            : $this->instances[$alias] = $this->newInstance($class, $params);

        if ($callback && (!$shared || !$instanceExists)) {
            $callback($obj);
        }

        return $obj;
    }

    /**
     * Gets a new instance of a class
     * @template T of object
     * @param class-string<T>|callable(): T $class Class name or callback function to instantiate class
     * @param array<int, string> $params Class initialization parameters
     * @throws Throwable
     * @return T Class instance
     */
    public function newInstance($class, array $params = []): object
    {
        return is_callable($class) ? $class(...$params) : new $class(...$params);
    }


    /**
     * Gets a registered callable
     * @return ?array{class-string|callable(): object, array<int, mixed>, ?callable(object): void}
     * Class information or null if not registered
     */
    public function get(string $alias): ?array
    {
        return $this->classes[$alias] ?? null;
    }

    /** Resets the object to the initial state */
    public function reset(): self
    {
        $this->classes = [];
        $this->instances = [];

        return $this;
    }
}

<?php

declare(strict_types=1);

namespace flight\core;

use Exception;

/**
 * The Loader class is responsible for loading objects. It maintains a list of
 * reusable class instances and can generate a new class instances with custom
 * initialization parameters. It also performs class autoloading.
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
     * Registers a class.
     * @template T of object
     * @param string $name Registry name
     * @param class-string<T>|callable(): T $class Class name or function to instantiate class
     * @param array<int, mixed> $params Class initialization parameters
     * @param ?callable(T): void $callback $callback Function to call after object instantiation
     */
    public function register(
        string $name,
        $class,
        array $params = [],
        ?callable $callback = null
    ): void {
        unset($this->instances[$name]);
        $this->classes[$name] = [$class, $params, $callback];
    }

    /**
     * Unregisters a class.
     * @param string $name Registry name
     */
    public function unregister(string $name): void
    {
        unset($this->classes[$name]);
    }

    /**
     * Loads a registered class.
     * @param string $name Method name
     * @param bool $shared Shared instance
     * @throws Exception
     * @return ?object Class instance
     */
    public function load(string $name, bool $shared = true): ?object
    {
        $obj = null;

        if (isset($this->classes[$name])) {
            [$class, $params, $callback] = $this->classes[$name];
            $exists = isset($this->instances[$name]);

            if ($shared) {
                $obj = $exists
                    ? $this->getInstance($name)
                    : $this->newInstance($class, $params);

                if (!$exists) {
                    $this->instances[$name] = $obj;
                }
            } else {
                $obj = $this->newInstance($class, $params);
            }

            if ($callback && (!$shared || !$exists)) {
                call_user_func_array($callback, [$obj]);
            }
        }

        return $obj;
    }

    /**
     * Gets a single instance of a class.
     * @param string $name Instance name
     * @return ?object Class instance
     */
    public function getInstance(string $name): ?object
    {
        return $this->instances[$name] ?? null;
    }

    /**
     * Gets a new instance of a class.
     * @template T of object
     * @param class-string<T>|callable(): T $class Class name or callback function to instantiate class
     * @param array<int, string> $params Class initialization parameters
     * @throws Exception
     * @return T Class instance
     */
    public function newInstance($class, array $params = []): object
    {
        if (is_callable($class)) {
            return call_user_func_array($class, $params);
        }

        return new $class(...$params);
    }

    /**
     * Gets a registered callable.
     * @param string $name Registry name
     * @return ?array{class-string|callable(): object, array<int, mixed>, ?callable(object): void}
     * Class information or null if not registered
     */
    public function get(string $name): ?array
    {
        return $this->classes[$name] ?? null;
    }

    /** Resets the object to the initial state */
    public function reset(): void
    {
        $this->classes = [];
        $this->instances = [];
    }
}

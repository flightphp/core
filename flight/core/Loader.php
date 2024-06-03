<?php

declare(strict_types=1);

namespace flight\core;

use Closure;
use Exception;

/**
 * The Loader class is responsible for loading objects. It maintains
 * a list of reusable class instances and can generate a new class
 * instances with custom initialization parameters. It also performs
 * class autoloading.
 *
 * @license MIT, http://flightphp.com/license
 * @copyright Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 */
class Loader
{
    /**
     * Registered classes.
     *
     * @var array<string, array{class-string|Closure(): object, array<int, mixed>, ?callable}> $classes
     */
    protected array $classes = [];

    /**
     * If this is disabled, classes can load with underscores
     */
    protected static bool $v2ClassLoading = true;

    /**
     * Class instances.
     *
     * @var array<string, object>
     */
    protected array $instances = [];

    /**
     * Autoload directories.
     *
     * @var array<int, string>
     */
    protected static array $dirs = [];

    /**
     * Registers a class.
     *
     * @param string $name Registry name
     * @param class-string<T>|Closure(): T $class Class name or function to instantiate class
     * @param array<int, mixed> $params Class initialization parameters
     * @param ?Closure(T $instance): void $callback $callback Function to call after object instantiation
     *
     * @template T of object
     */
    public function register(string $name, $class, array $params = [], ?callable $callback = null): void
    {
        unset($this->instances[$name]);

        $this->classes[$name] = [$class, $params, $callback];
    }

    /**
     * Unregisters a class.
     *
     * @param string $name Registry name
     */
    public function unregister(string $name): void
    {
        unset($this->classes[$name]);
    }

    /**
     * Loads a registered class.
     *
     * @param string $name   Method name
     * @param bool   $shared Shared instance
     *
     * @throws Exception
     *
     * @return ?object Class instance
     */
    public function load(string $name, bool $shared = true): ?object
    {
        $obj = null;

        if (isset($this->classes[$name])) {
            [0 => $class, 1 => $params, 2 => $callback] = $this->classes[$name];

            $exists = isset($this->instances[$name]);

            if ($shared) {
                $obj = ($exists) ?
                    $this->getInstance($name) :
                    $this->newInstance($class, $params);

                if (!$exists) {
                    $this->instances[$name] = $obj;
                }
            } else {
                $obj = $this->newInstance($class, $params);
            }

            if ($callback && (!$shared || !$exists)) {
                $ref = [&$obj];
                \call_user_func_array($callback, $ref);
            }
        }

        return $obj;
    }

    /**
     * Gets a single instance of a class.
     *
     * @param string $name Instance name
     *
     * @return ?object Class instance
     */
    public function getInstance(string $name): ?object
    {
        return $this->instances[$name] ?? null;
    }

    /**
     * Gets a new instance of a class.
     *
     * @param class-string<T>|Closure(): class-string<T> $class  Class name or callback function to instantiate class
     * @param array<int, string>           $params Class initialization parameters
     *
     * @template T of object
     *
     * @throws Exception
     *
     * @return T Class instance
     */
    public function newInstance($class, array $params = [])
    {
        if (\is_callable($class)) {
            return \call_user_func_array($class, $params);
        }

        return new $class(...$params);
    }

    /**
     * Gets a registered callable
     *
     * @param string $name Registry name
     *
     * @return mixed Class information or null if not registered
     */
    public function get(string $name)
    {
        return $this->classes[$name] ?? null;
    }

    /**
     * Resets the object to the initial state.
     */
    public function reset(): void
    {
        $this->classes = [];
        $this->instances = [];
    }

    // Autoloading Functions

    /**
     * Starts/stops autoloader.
     *
     * @param bool  $enabled Enable/disable autoloading
     * @param string|iterable<int, string> $dirs    Autoload directories
     */
    public static function autoload(bool $enabled = true, $dirs = []): void
    {
        if ($enabled) {
            spl_autoload_register([__CLASS__, 'loadClass']);
        } else {
            spl_autoload_unregister([__CLASS__, 'loadClass']); // @codeCoverageIgnore
        }

        if (!empty($dirs)) {
            self::addDirectory($dirs);
        }
    }

    /**
     * Autoloads classes.
     *
     * Classes are not allowed to have underscores in their names.
     *
     * @param string $class Class name
     */
    public static function loadClass(string $class): void
    {
        $replace_chars = self::$v2ClassLoading === true ? ['\\', '_'] : ['\\'];
        $classFile = str_replace($replace_chars, '/', $class) . '.php';

        foreach (self::$dirs as $dir) {
            $filePath = "$dir/$classFile";

            if (file_exists($filePath)) {
                require_once $filePath;
                return;
            }
        }
    }

    /**
     * Adds a directory for autoloading classes.
     *
     * @param string|iterable<int, string> $dir Directory path
     */
    public static function addDirectory($dir): void
    {
        if (\is_array($dir) || \is_object($dir)) {
            foreach ($dir as $value) {
                self::addDirectory($value);
            }
        } elseif (\is_string($dir)) {
            if (!\in_array($dir, self::$dirs, true)) {
                self::$dirs[] = $dir;
            }
        }
    }


    /**
     * Sets the value for V2 class loading.
     *
     * @param bool $value The value to set for V2 class loading.
     *
     * @return void
     */
    public static function setV2ClassLoading(bool $value): void
    {
        self::$v2ClassLoading = $value;
    }
}

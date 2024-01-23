<?php

declare(strict_types=1);
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 * @license     MIT, http://flightphp.com/license
 */

namespace flight\core;

use Closure;
use Exception;

/**
 * The Loader class is responsible for loading objects. It maintains
 * a list of reusable class instances and can generate a new class
 * instances with custom initialization parameters. It also performs
 * class autoloading.
 */
class Loader
{
    /**
     * Registered classes.
     * @var array<string, array{class-string, array<int, mixed>, ?callable}> $classes
     */
    protected $classes = [];

    /**
     * Class instances.
     * @var array<string, object>
     */
    protected $instances = [];

    /**
     * Autoload directories.
     * @var array<int, string>
     */
    protected static array $dirs = [];

    /**
     * Registers a class.
     * @template T of object
     *
     * @param string          $name     Registry name
     * @param class-string<T> $class    Class name or function to instantiate class
     * @param array<int, mixed>           $params   Class initialization parameters
     * @param ?callable(T $instance): void   $callback $callback Function to call after object instantiation
     *
     * @return void
     */
    public function register($name, $class, $params = [], $callback = null)
    {
        unset($this->instances[$name]);

        $this->classes[$name] = [$class, $params, $callback];
    }

    /**
     * Unregisters a class.
     *
     * @param string $name Registry name
     *
     * @return void
     */
    public function unregister($name)
    {
        unset($this->classes[$name]);
    }

    /**
     * Loads a registered class.
     *
     * @param string $name   Method name
     * @param bool   $shared Shared instance
     *
     * @return ?object Class instance
     * @throws Exception
     */
    public function load($name, $shared = true)
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
    public function getInstance($name)
    {
        return $this->instances[$name] ?? null;
    }

    /**
     * Gets a new instance of a class.
     * @template T of object
     *
     * @param class-string<T>|Closure(): class-string<T> $class  Class name or callback function to instantiate class
     * @param array<int, string>           $params Class initialization parameters
     *
     * @return T Class instance
     * @throws Exception
     */
    public function newInstance($class, $params = [])
    {
        if (\is_callable($class)) {
            return \call_user_func_array($class, $params);
        }

		return new $class(...$params);
    }

    /**
     * @param string $name Registry name
     *
     * @return mixed Class information or null if not registered
     */
    public function get($name)
    {
        return $this->classes[$name] ?? null;
    }

    /**
     * Resets the object to the initial state.
     *
     * @return void
     */
    public function reset()
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
     *
     * @return void
     */
    public static function autoload($enabled = true, $dirs = [])
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
     *
     * @return void
     */
    public static function loadClass($class)
    {
        $class_file = str_replace(['\\', '_'], '/', $class) . '.php';

        foreach (self::$dirs as $dir) {
            $file = $dir . '/' . $class_file;
            if (file_exists($file)) {
                require $file;

                return;
            }
        }
    }

    /**
     * Adds a directory for autoloading classes.
     *
     * @param string|iterable<int, string> $dir Directory path
     *
     * @return void
     */
    public static function addDirectory($dir)
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
}

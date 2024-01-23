<?php

declare(strict_types=1);
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 * @license     MIT, http://flightphp.com/license
 */

use flight\core\Dispatcher;
use flight\Engine;
use flight\net\Request;
use flight\net\Response;
use flight\net\Router;
use flight\template\View;
use flight\net\Route;

/**
 * The Flight class is a static representation of the framework.
 *
 * @method  static void start() Starts the framework.
 * @method  static void path(string $path) Adds a path for autoloading classes.
 * @method  static void stop() Stops the framework and sends a response.
 * @method  static void halt(int $code = 200, string $message = '') Stop the framework with an optional status code and message.
 *
 * @method  static Route route(string $pattern, callable $callback, bool $pass_route = false, string $alias = '') Maps a URL pattern to a callback with all applicable methods.
 * @method  static void  group(string $pattern, callable $callback, array $group_middlewares = []) Groups a set of routes together under a common prefix.
 * @method  static Route post(string $pattern, callable $callback, bool $pass_route = false, string $alias = '') Routes a POST URL to a callback function.
 * @method  static Route put(string $pattern, callable $callback, bool $pass_route = false, string $alias = '') Routes a PUT URL to a callback function.
 * @method  static Route patch(string $pattern, callable $callback, bool $pass_route = false, string $alias = '') Routes a PATCH URL to a callback function.
 * @method  static Route delete(string $pattern, callable $callback, bool $pass_route = false, string $alias = '') Routes a DELETE URL to a callback function.
 * @method  static Router router() Returns Router instance.
 * @method  static string getUrl(string $alias) Gets a url from an alias
 *
 * @method  static void map(string $name, callable $callback) Creates a custom framework method.
 *
 * @method  static void before(string $name, callable(array &$params, string &$output): void|false $callback) Adds a filter before a framework method.
 * @method  static void after(string $name, callable(array &$params, string &$output): void|false $callback) Adds a filter after a framework method.
 *
 * @method  static void set(string $key, mixed $value) Sets a variable.
 * @method  static mixed get(string $key) Gets a variable.
 * @method  static bool has(string $key) Checks if a variable is set.
 * @method  static void clear(?string $key = null) Clears a variable.
 *
 * @method  static void render(string $file, array $data = null, ?string $key = null) Renders a template file.
 * @method  static View view() Returns View instance.
 *
 * @method  static Request request() Returns Request instance.
 * @method  static Response response() Returns Response instance.
 * @method  static void redirect(string $url, int $code = 303) Redirects to another URL.
 * @method  static void json(mixed $data, int $code = 200, bool $encode = true, string $charset = "utf8", int $encodeOption = 0, int $encodeDepth = 512) Sends a JSON response.
 * @method  static void jsonp(mixed $data, string $param = 'jsonp', int $code = 200, bool $encode = true, string $charset = "utf8", int $encodeOption = 0, int $encodeDepth = 512) Sends a JSONP response.
 * @method  static void error(Throwable $exception) Sends an HTTP 500 response.
 * @method  static void notFound() Sends an HTTP 404 response.
 *
 * @method  static void etag(string $id, string $type = 'strong') Performs ETag HTTP caching.
 * @method  static void lastModified(int $time) Performs last modified HTTP caching.
 */
class Flight
{
    /**
     * Framework engine.
	 * 
	 * @var Engine $engine
     */
    private static Engine $engine;

	/**
	 * Whether or not the app has been initialized
	 *
	 * @var boolean
	 */
	private static bool $initialized = false;

	/**
	 * Don't allow object instantiation
	 * 
	 * @codeCoverageIgnore
	 * @return void
	 */
    private function __construct()
    {
    }

	/**
	 * Forbid cloning the class
	 *
	 * @codeCoverageIgnore
	 * @return void
	 */
    private function __clone()
    {
    }

    /**
     * Registers a class to a framework method.
     * @template T of object
     * @param  string $name Static method name
     * ```
     * Flight::register('user', User::class);
     *
     * Flight::user(); # <- Return a User instance
     * ```
     * @param  class-string<T> $class Fully Qualified Class Name
     * @param  array<int, mixed>  $params   Class constructor params
     * @param  ?Closure(T $instance): void $callback Perform actions with the instance
     * @return void
     */
    public static function register($name, $class, $params = [], $callback = null)
    {
        static::__callStatic('register', func_get_args());
    }

    /**
     * Handles calls to static methods.
     *
     * @param string $name   Method name
     * @param array<int, mixed>  $params Method parameters
     *
     * @throws Exception
     *
     * @return mixed Callback results
     */
    public static function __callStatic(string $name, array $params)
    {
        $app = self::app();

        return Dispatcher::invokeMethod([$app, $name], $params);
    }

    /**
     * @return Engine Application instance
     */
    public static function app(): Engine
    {
        if (!self::$initialized) {
            require_once __DIR__ . '/autoload.php';

            self::setEngine(new Engine());

            self::$initialized = true;
        }

        return self::$engine;
    }

	/**
	 * Set the engine instance
	 *
	 * @param Engine $engine Vroom vroom!
	 * @return void
	 */
	public static function setEngine(Engine $engine): void
	{
		self::$engine = $engine;
	}
}

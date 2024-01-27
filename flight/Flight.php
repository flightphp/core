<?php

declare(strict_types=1);

use flight\core\Dispatcher;
use flight\Engine;
use flight\net\Request;
use flight\net\Response;
use flight\net\Router;
use flight\template\View;
use flight\net\Route;

/**
 * The Flight class is a static representation of the framework.
 * @license MIT, http://flightphp.com/license
 * @copyright Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 *
 * # Core methods
 * @method  static void start() Starts the framework.
 * @method  static void path(string $path) Adds a path for autoloading classes.
 * @method  static void stop() Stops the framework and sends a response.
 * @method  static void halt(int $code = 200, string $message = '')
 * Stop the framework with an optional status code and message.
 *
 * # Routing
 * @method  static Route route(string $pattern, callable $callback, bool $pass_route = false, string $alias = '')
 * Maps a URL pattern to a callback with all applicable methods.
 * @method  static void  group(string $pattern, callable $callback, array $group_middlewares = [])
 * Groups a set of routes together under a common prefix.
 * @method  static Route post(string $pattern, callable $callback, bool $pass_route = false, string $alias = '')
 * Routes a POST URL to a callback function.
 * @method  static Route put(string $pattern, callable $callback, bool $pass_route = false, string $alias = '')
 * Routes a PUT URL to a callback function.
 * @method  static Route patch(string $pattern, callable $callback, bool $pass_route = false, string $alias = '')
 * Routes a PATCH URL to a callback function.
 * @method  static Route delete(string $pattern, callable $callback, bool $pass_route = false, string $alias = '')
 * Routes a DELETE URL to a callback function.
 * @method  static Router router() Returns Router instance.
 * @method  static string getUrl(string $alias) Gets a url from an alias
 *
 * @method  static void map(string $name, callable $callback) Creates a custom framework method.
 *
 * @method  static void before($name, $callback) Adds a filter before a framework method.
 * @method  static void after($name, $callback) Adds a filter after a framework method.
 *
 * @method  static void set($key, $value) Sets a variable.
 * @method  static mixed get($key) Gets a variable.
 * @method  static bool has($key) Checks if a variable is set.
 * @method  static void clear($key = null) Clears a variable.
 *
 * # Views
 * @method  static void render($file, array $data = null, $key = null) Renders a template file.
 * @method  static View view() Returns View instance.
 *
 * # Request-Response
 * @method  static Request request() Returns Request instance.
 * @method  static Response response() Returns Response instance.
 * @method  static void redirect($url, $code = 303) Redirects to another URL.
 * @method  static void json($data, $code = 200, $encode = true, $charset = "utf8", $encodeOption = 0, $encodeDepth = 512) Sends a JSON response.
 * @method  static void jsonp($data, $param = 'jsonp', $code = 200, $encode = true, $charset = "utf8", $encodeOption = 0, $encodeDepth = 512) Sends a JSONP response.
 * @method  static void error($exception) Sends an HTTP 500 response.
 * @method  static void notFound() Sends an HTTP 404 response.
 *
 * # HTTP caching
 * @method  static void etag($id, $type = 'strong') Performs ETag HTTP caching.
 * @method  static void lastModified($time) Performs last modified HTTP caching.
 */
// phpcs:ignoreFile Generic.Files.LineLength.TooLong, PSR1.Classes.ClassDeclaration.MissingNamespace
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

    /** Unregisters a class. */
    public static function unregister(string $methodName): void
    {
        static::__callStatic('unregister', func_get_args());
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
     */
    public static function setEngine(Engine $engine): void
    {
        self::$engine = $engine;
    }
}
